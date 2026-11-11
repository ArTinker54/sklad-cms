<?php

declare(strict_types=1);

namespace Sklad\Core;

use RuntimeException;

/**
 * Накат миграций.
 *
 * Сайтов будет несколько, и обновляться они будут вразнобой. Система
 * должна сама понимать, чего в конкретной базе ещё нет, — иначе на
 * пятом сайте кто-нибудь забудет выполнить очередной файл, и разница
 * всплывёт через месяц в виде непонятной ошибки.
 *
 * Миграции только вперёд, отката нет. Откат в живом каталоге почти
 * всегда означает потерю данных, и честнее написать следующую миграцию,
 * чем делать вид, что назад безопасно.
 */
final class Migrator
{
    public function __construct(
        private Db $db,
        private string $directory
    ) {
    }

    /** @return string[] названия применённых на этом заходе миграций */
    public function migrate(): array
    {
        $this->ensureTable();

        $applied = [];
        foreach ($this->pending() as $name) {
            $sql = file_get_contents($this->directory . '/' . $name);
            if ($sql === false) {
                throw new RuntimeException('Не читается миграция ' . $name);
            }

            /*
             * Если запрос посреди файла не прошёл, всё до него уже
             * выполнено: MySQL завершает изменение схемы сразу и назад
             * не отматывает. Файл при этом в применённые не попадает —
             * и следующий заход начнёт его сначала, спотыкаясь на
             * «столбец уже существует», причём совсем в другом месте.
             *
             * Разобрать это можно только зная, где именно встали.
             * Поэтому говорим номер запроса и его начало — дальше
             * человек дописывает недостающее руками или правит файл,
             * убирая уже выполненное.
             */
            $number = 0;
            foreach ($this->split($sql) as $statement) {
                $number++;
                try {
                    $this->db->pdo()->exec($statement);
                } catch (\PDOException $e) {
                    throw new RuntimeException(
                        'Миграция ' . $name . ' встала на запросе №' . $number . ': ' . $e->getMessage()
                        . "\nЗапрос: " . mb_substr(preg_replace('/\s+/', ' ', $statement) ?? '', 0, 200)
                        . "\nПредыдущие " . ($number - 1) . ' уже выполнены — база на середине файла.'
                        . ' Отката нет: доведите вручную или уберите из файла выполненное и запустите снова.',
                        0,
                        $e
                    );
                }
            }

            $this->db->insert('migration', [
                'name' => $name,
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
            $applied[] = $name;
        }

        return $applied;
    }

    /** @return string[] */
    public function pending(): array
    {
        $this->ensureTable();

        $done = $this->db->column('SELECT name FROM migration');
        $files = [];
        foreach (scandir($this->directory) ?: [] as $file) {
            if (substr($file, -4) === '.sql' && !in_array($file, $done, true)) {
                $files[] = $file;
            }
        }
        // Порядок задаётся номером в имени: 001-schema, 002-seed.
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Таблицу учёта заводим отдельно от миграций: она нужна до того, как
     * применится первая из них.
     */
    private function ensureTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migration ('
            . ' name VARCHAR(190) NOT NULL,'
            . ' applied_at DATETIME NOT NULL,'
            . ' PRIMARY KEY (name)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * Разбивает файл на отдельные запросы.
     *
     * PDO::exec выполняет только один запрос за раз, а миграция — это
     * файл из двух десятков.
     *
     * Идём по всему тексту одним проходом и по одному символу, потому
     * что точка с запятой значит конец запроса только вне кавычек и вне
     * комментария. Разбор по строкам, который был здесь раньше, ломался
     * на комментариях в конце строки: они оставались в тексте, и первый
     * же апостроф внутри них сбивал разбор до конца файла.
     *
     * Байтовый проход безопасен для русского текста: у UTF-8 старший
     * бит продолжающих байт всегда взведён, и ни один из них не совпадёт
     * с кавычкой, дефисом или точкой с запятой.
     *
     * @return string[]
     */
    private function split(string $sql): array
    {
        $statements = [];
        $current = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($quote !== null) {
                $current .= $char;
                // Экранированный символ внутри строки — вместе со слэшем.
                if ($char === '\\' && $next !== '') {
                    $current .= $next;
                    $i++;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            // Комментарий до конца строки: -- и #
            if (($char === '-' && $next === '-') || $char === '#') {
                $break = strpos($sql, "\n", $i);
                $i = $break === false ? $length : $break;
                $current .= "\n";
                continue;
            }

            // Блочный комментарий
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);

                // Скобка с восклицательным знаком — не комментарий, а
                // запрос: так MySQL записывает то, что выполняют только
                // его версии. Этим начинается почти любой дамп — «SET
                // NAMES utf8», выбор движка, — и такие куски попадаются
                // внутри CREATE TABLE. Выбрасывая их наравне с обычными
                // комментариями, мы молча меняли смысл запроса, и
                // никакого сообщения об этом не было.
                if (($sql[$i + 2] ?? '') === '!') {
                    $current .= $end === false
                        ? substr($sql, $i)
                        : substr($sql, $i, $end + 2 - $i);
                    $i = $end === false ? $length : $end + 1;
                    continue;
                }

                $i = $end === false ? $length : $end + 1;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }

            if ($char === ';') {
                $statement = trim($current);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $tail = trim($current);
        if ($tail !== '') {
            $statements[] = $tail;
        }

        return $statements;
    }
}
