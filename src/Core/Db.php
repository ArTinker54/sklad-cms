<?php

declare(strict_types=1);

namespace Sklad\Core;

use PDO;
use PDOStatement;
use RuntimeException;

/**
 * Работа с базой.
 *
 * Тонкая обёртка над PDO, а не свой слой запросов: писать SQL руками
 * здесь честнее. Каталог складской техники — это несколько десятков
 * запросов, и прятать их за конструктором значило бы усложнять ради
 * усложнения.
 *
 * Исключения включены намеренно: молчаливый false от PDO уже стоил
 * мне на этом проекте нескольких часов разбирательств с пустыми
 * списками в чужой админке.
 */
final class Db
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'] ?? 'localhost',
            (int) ($config['port'] ?? 3306),
            $config['name'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );

        $this->pdo = new PDO($dsn, $config['user'] ?? '', $config['pass'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Настоящие подготовленные запросы, а не подстановка на
            // стороне драйвера: с ней LIMIT ? приходит строкой и MySQL
            // ругается.
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function run(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /** Одна строка или null. */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** Все строки. */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** Первое поле первой строки. */
    public function value(string $sql, array $params = [])
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** Список значений одного столбца. */
    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Ключ => значение из двух первых столбцов. */
    public function pairs(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = 'INSERT INTO ' . $this->quoteName($table)
            . ' (' . implode(', ', array_map([$this, 'quoteName'], $columns)) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $this->run($sql, array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $params = []): int
    {
        if (!$data) {
            return 0;
        }
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = $this->quoteName($column) . ' = ?';
        }
        $sql = 'UPDATE ' . $this->quoteName($table) . ' SET ' . implode(', ', $set) . ' WHERE ' . $where;

        return $this->run($sql, array_merge(array_values($data), $params))->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->run('DELETE FROM ' . $this->quoteName($table) . ' WHERE ' . $where, $params)->rowCount();
    }

    /**
     * Несколько связанных изменений одним куском.
     *
     * Нужна там, где половина работы хуже, чем ничего: сменить адрес
     * товара и завести редирект на старый — либо оба, либо ни одного.
     */
    /**
     * Работа одной сделкой.
     *
     * Вложенный вызов не начинает вторую сделку, а просто выполняет
     * работу внутри уже открытой: PDO вложенных не умеет и отвечает
     * ошибкой «сделка уже открыта». Кто открыл первым, тот и решает,
     * закрепить или откатить — так у изменений остаётся одна граница, а
     * не две вложенные с разной судьбой.
     */
    public function transaction(callable $work)
    {
        if ($this->pdo->inTransaction()) {
            return $work($this);
        }

        $this->pdo->beginTransaction();
        try {
            $result = $work($this);
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Имя таблицы или столбца в обратных кавычках.
     *
     * В схеме есть option, key, group — это зарезервированные слова
     * MySQL, и без кавычек запросы к ним не разбираются.
     */
    public function quoteName(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException('Недопустимое имя в запросе: ' . $name);
        }

        return '`' . $name . '`';
    }
}
