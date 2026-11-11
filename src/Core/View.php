<?php

declare(strict_types=1);

namespace Sklad\Core;

use RuntimeException;

/**
 * Шаблоны.
 *
 * Обычный PHP, без своего языка разметки. Свой язык пришлось бы
 * документировать и объяснять каждому, кто придёт после, а выигрыш на
 * витрине из пяти типов страниц — нулевой.
 *
 * Экранирование не автоматическое, но вызов короткий: e(). Все места,
 * где выводится текст из базы, обязаны через него проходить.
 */
final class View
{
    private array $shared = [];

    public function __construct(private string $directory)
    {
    }

    /** Значения, доступные во всех шаблонах: настройки сайта, текущий человек. */
    public function share(string $key, $value): void
    {
        $this->shared[$key] = $value;
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->resolve($template);
        if ($file === null) {
            throw new RuntimeException('Нет шаблона ' . $template);
        }

        $scope = array_merge($this->shared, $data);
        $view = $this;

        ob_start();
        try {
            (static function (string $__file, array $__scope, View $view): void {
                extract($__scope, EXTR_SKIP);
                require $__file;
            })($file, $scope, $view);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Путь к файлу шаблона, с откатом на базовую тему.
     *
     * Тема сайта может состоять из одного файла: остальное берётся из
     * default. Иначе новая площадка сети начиналась бы с копирования
     * тринадцати шаблонов, двенадцать из которых потом никто не правит, —
     * и правка общей ошибки требовала бы обхода всех тем.
     *
     * Откат работает только для site/<тема>/…: админка одна на все сайты,
     * и подменять её шаблоны нечем.
     */
    private function resolve(string $template): ?string
    {
        $path = ltrim($template, '/');
        $file = $this->directory . '/' . $path . '.php';
        if (is_file($file)) {
            return $file;
        }

        if (preg_match('~^site/([^/]+)/(.+)$~', $path, $m) && $m[1] !== 'default') {
            $fallback = $this->directory . '/site/default/' . $m[2] . '.php';
            if (is_file($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Шаблон внутри обёртки. Обёртка получает готовое содержимое в
     * переменной content.
     */
    public function renderIn(string $layout, string $template, array $data = []): string
    {
        $content = $this->render($template, $data);

        return $this->render($layout, array_merge($data, ['content' => $content]));
    }

    public static function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Цена как её читают люди: 1 250 000 ₽. */
    public static function price($value): string
    {
        return number_format((float) $value, 0, ',', ' ') . ' ₽';
    }

    /**
     * Часы работы в том виде, в каком их понимает поисковик.
     *
     * «Пн–Вс, 9:00–18:00» человеку понятно, а робот ждёт
     * «Mo-Su 09:00-18:00» и показывает это прямо в выдаче. Держать две
     * настройки — значит однажды поменять одну и забыть другую, поэтому
     * второй вид собирается из первого.
     *
     * Не разобралось — возвращаем пустое: неверные часы в выдаче хуже,
     * чем никаких. Человек в этом случае всё равно видит строку как есть.
     */
    public static function schemaHours(string $human): string
    {
        $days = ['пн' => 'Mo', 'вт' => 'Tu', 'ср' => 'We', 'чт' => 'Th',
                 'пт' => 'Fr', 'сб' => 'Sa', 'вс' => 'Su'];

        $text = mb_strtolower(str_replace(['–', '—', '−'], '-', $human));
        if (!preg_match('/([а-я]{2})\s*-\s*([а-я]{2}).*?(\d{1,2})[:.](\d{2})\s*-\s*(\d{1,2})[:.](\d{2})/u', $text, $m)) {
            return '';
        }
        if (!isset($days[$m[1]], $days[$m[2]])) {
            return '';
        }

        return sprintf(
            '%s-%s %02d:%02d-%02d:%02d',
            $days[$m[1]],
            $days[$m[2]],
            (int) $m[3],
            (int) $m[4],
            (int) $m[5],
            (int) $m[6]
        );
    }

    /** Дата в привычном виде, а не как её хранит база. */
    public static function date(?string $value, string $format = 'd.m.Y'): string
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000')) {
            return '';
        }
        $time = strtotime($value);

        return $time === false ? '' : date($format, $time);
    }
}
