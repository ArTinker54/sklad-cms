<?php

declare(strict_types=1);

namespace Sklad\Model;

use Sklad\Core\Db;

/**
 * Свои тексты разделов и страниц для городских поддоменов.
 *
 * Метки вида «{городе}» подставляют город в общий текст — этого хватает,
 * чтобы посетитель видел своё название, но поисковику страницы городов
 * остаются копиями друг друга. Здесь хранится то, что для города переписали
 * по существу: своя доставка, свои сроки, свои местные объекты.
 *
 * Правило одно: пустое поле означает «взять общее». Пустой текст на витрине
 * не показывается никогда — заполнили один город, остальные работают как
 * работали.
 */
final class CityContent
{
    /** Поля, которые можно переопределить для города. */
    public const FIELDS = ['meta_title', 'meta_description', 'intro', 'body'];

    public function __construct(private Db $db)
    {
    }

    /**
     * Накладываем городской текст на общий.
     *
     * Возвращаем запись целиком, а не разницу: витрина работает с ней как
     * с обычным разделом и о городах не знает вовсе. Так подмена не забудется
     * в новом шаблоне — забыть можно то, что нужно вызывать, а не то, что
     * уже лежит в данных.
     *
     * @param array<string, mixed> $row
     * @param array{slug: string}|null $city null на основном адресе
     * @return array<string, mixed>
     */
    public function apply(array $row, string $entity, ?array $city): array
    {
        if ($city === null || empty($row['id'])) {
            return $row;
        }

        $over = $this->db->one(
            'SELECT ' . implode(', ', self::FIELDS) . ' FROM city_content'
            . ' WHERE entity = ? AND entity_id = ? AND city = ?',
            [$entity, (int) $row['id'], (string) $city['slug']]
        );
        if ($over === null) {
            return $row;
        }

        foreach (self::FIELDS as $f) {
            // Именно trim: редактор часто оставляет в поле перевод строки или
            // пробел, и без обрезки такое поле считалось бы заполненным — на
            // витрине вместо общего текста зияла бы пустота.
            if (array_key_exists($f, $row) && trim((string) ($over[$f] ?? '')) !== '') {
                $row[$f] = $over[$f];
            }
        }

        return $row;
    }

    /**
     * Городские тексты одной записи — для админки.
     *
     * @return array<string, array<string, string>> слаг города => поля
     */
    public function forEntity(string $entity, int $id): array
    {
        $out = [];
        foreach ($this->db->all(
            'SELECT city, ' . implode(', ', self::FIELDS) . ' FROM city_content'
            . ' WHERE entity = ? AND entity_id = ?',
            [$entity, $id]
        ) as $row) {
            $city = (string) $row['city'];
            unset($row['city']);
            $out[$city] = array_map(static fn ($v): string => (string) $v, $row);
        }

        return $out;
    }

    /**
     * Сохраняем текст города.
     *
     * Когда все поля пусты, запись удаляем, а не храним пустой. Иначе в
     * списке «города, где текст переписан» висели бы пустышки, и понять,
     * где работа сделана, а где нет, стало бы нельзя.
     *
     * @param array<string, string> $values
     */
    public function save(string $entity, int $id, string $city, array $values, ?string $who): void
    {
        $data = [];
        $filled = false;
        foreach (self::FIELDS as $f) {
            $v = trim((string) ($values[$f] ?? ''));
            $data[$f] = $v;
            $filled = $filled || $v !== '';
        }

        if (!$filled) {
            $this->db->delete(
                'city_content',
                'entity = ? AND entity_id = ? AND city = ?',
                [$entity, $id, $city]
            );
            return;
        }

        $data['entity'] = $entity;
        $data['entity_id'] = $id;
        $data['city'] = $city;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $who;

        $exists = $this->db->value(
            'SELECT id FROM city_content WHERE entity = ? AND entity_id = ? AND city = ?',
            [$entity, $id, $city]
        );
        if ($exists !== null) {
            $this->db->update('city_content', $data, 'id = ?', [(int) $exists]);
        } else {
            $this->db->insert('city_content', $data);
        }
    }

    /**
     * Города, для которых текст уже написан, — чтобы отметить их в админке.
     *
     * @return array<int, string>
     */
    public function filledCities(string $entity, int $id): array
    {
        return array_column(
            $this->db->all(
                'SELECT city FROM city_content WHERE entity = ? AND entity_id = ? ORDER BY city',
                [$entity, $id]
            ),
            'city'
        );
    }
}
