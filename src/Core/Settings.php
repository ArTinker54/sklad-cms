<?php

declare(strict_types=1);

namespace Sklad\Core;

/**
 * Настройки сайта из базы.
 *
 * Телефон, почта, реквизиты, номер счётчика, формулировка согласия —
 * всё, что меняется чаще кода. Читаются одним запросом на страницу и
 * держатся в памяти: обращений к ним за отрисовку десятки.
 */
final class Settings
{
    private ?array $values = null;

    public function __construct(private Db $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $this->load();

        return $this->values[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, '');

        return $value === '' ? $default : (int) $value;
    }

    public function flag(string $key): bool
    {
        return in_array($this->get($key), ['1', 'yes', 'true'], true);
    }

    public function set(string $key, string $value): void
    {
        $this->db->run(
            'INSERT INTO setting (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$key, $value]
        );
        $this->values[$key] = $value;
    }

    /** Все настройки по разделам — для формы в админке. */
    public function grouped(): array
    {
        $rows = $this->db->all('SELECT * FROM setting ORDER BY `group`, sort, `key`');
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['group']][] = $row;
        }

        return $grouped;
    }

    private function load(): void
    {
        if ($this->values === null) {
            $this->values = $this->db->pairs('SELECT `key`, value FROM setting');
        }
    }
}
