<?php

declare(strict_types=1);

namespace Sklad\Core;

/**
 * Разбор адреса.
 *
 * Своих маршрутов немного — админка и служебные адреса витрины. Всё
 * остальное решается по таблице: адрес товара, раздела или страницы
 * лежит в базе целиком, и подбирать его правилами не нужно.
 *
 * Поэтому здесь нет ни групп, ни промежуточных обработчиков: список
 * шаблонов и обход сверху вниз. Когда маршрутов два десятка, этого
 * достаточно, а читается оно без документации.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:mixed}> */
    private array $routes = [];

    public function add(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function get(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Ищет обработчик под адрес.
     *
     * @return array{handler:mixed, params:array}|null
     */
    public function match(string $method, string $path): ?array
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $params = $this->matchPattern($route['pattern'], $path);
            if ($params !== null) {
                return ['handler' => $route['handler'], 'params' => $params];
            }
        }

        return null;
    }

    /**
     * Шаблон вида /admin/product/{id}. Число в {id} не проверяем здесь —
     * это дело обработчика, который всё равно обязан не доверять вводу.
     */
    private function matchPattern(string $pattern, string $path): ?array
    {
        $pattern = '/' . trim($pattern, '/');
        if ($pattern === $path) {
            return [];
        }
        if (strpos($pattern, '{') === false) {
            return null;
        }

        $names = [];
        $regex = preg_replace_callback(
            '/\{([a-z_]+)\}/i',
            static function (array $m) use (&$names): string {
                $names[] = $m[1];

                return '([^/]+)';
            },
            $pattern
        );

        if (!preg_match('#^' . $regex . '$#u', $path, $matches)) {
            return null;
        }

        array_shift($matches);

        return array_combine($names, $matches) ?: [];
    }
}
