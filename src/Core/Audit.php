<?php

declare(strict_types=1);

namespace Sklad\Core;

/**
 * Журнал изменений.
 *
 * Записываем только то, что действительно поменялось, а не всю форму.
 * Иначе каждое сохранение товара порождало бы запись на сорок полей, и
 * найти в этом «кто уронил цену вдвое» было бы нельзя.
 *
 * Когда с каталогом работают двое, это единственный способ разобраться,
 * откуда взялась цифра.
 */
final class Audit
{
    /**
     * Поля, которые меняются сами при каждом сохранении.
     *
     * Метка правки отличается всегда, поэтому запись о ней появлялась бы
     * и там, где человек ничего не тронул. Правку цены в таком журнале
     * уже не найти — она лежит среди сотни пустышек.
     */
    private const SERVICE_FIELDS = ['created_at', 'updated_at'];

    public function __construct(private Db $db, private Auth $auth)
    {
    }

    public function write(string $entity, ?int $entityId, string $action, array $before = [], array $after = []): void
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if (in_array($field, self::SERVICE_FIELDS, true)) {
                continue;
            }
            $old = $before[$field] ?? null;
            if (!$this->same($old, $value)) {
                $changes[$field] = ['было' => $old, 'стало' => $value];
            }
        }

        if ($action === 'update' && !$changes) {
            return;
        }

        $this->db->insert('audit', [
            'user_id' => $this->auth->id(),
            'entity' => $entity,
            'entity_id' => $entityId,
            'action' => $action,
            'changes' => $changes ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            'ip' => $this->ip(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** История одной сущности — для вкладки в карточке. */
    public function history(string $entity, int $entityId, int $limit = 50): array
    {
        return $this->db->all(
            'SELECT a.*, u.name AS user_name FROM audit a'
            . ' LEFT JOIN user u ON u.id = a.user_id'
            . ' WHERE a.entity = ? AND a.entity_id = ?'
            . ' ORDER BY a.id DESC LIMIT ' . max(1, $limit),
            [$entity, $entityId]
        );
    }

    /**
     * Одно ли это значение по сути.
     *
     * Числа сравниваем числами: цена лежит в базе как '1100000.00', а из
     * формы приходит как '1100000', и строками они не равны никогда. От
     * этого простое открытие карточки с нажатием «Сохранить» писало в
     * журнал выдуманную правку цены. Всё остальное — по-прежнему
     * строками: из формы всё приходит строкой.
     */
    private function same(mixed $old, mixed $new): bool
    {
        if (is_numeric($old) && is_numeric($new)) {
            return (float) $old === (float) $new;
        }

        return (string) $old === (string) $new;
    }

    private function ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        return $forwarded !== ''
            ? trim(explode(',', $forwarded)[0])
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
