<?php

declare(strict_types=1);

namespace Sklad\Model;

/**
 * Варианты снимка.
 *
 * Media кладёт каждую фотографию четырьмя файлами: большой и плиточный,
 * каждый в jpeg и webp. В базе лежит один путь — большой jpeg, остальные
 * получаются из него по имени. Хранить четыре пути в четырёх колонках
 * незачем: они всегда меняются вместе.
 */
final class Photo
{
    public const PLACEHOLDER = '/assets/img/placeholder.svg';

    /** Плитка в списке: 640 точек по длинной стороне. */
    public static function card(string $file): string
    {
        return $file === '' ? self::PLACEHOLDER : self::suffix($file, '-card');
    }

    public static function cardWebp(string $file): string
    {
        return $file === '' ? self::PLACEHOLDER : self::webp(self::suffix($file, '-card'));
    }

    public static function full(string $file): string
    {
        return $file === '' ? self::PLACEHOLDER : $file;
    }

    public static function fullWebp(string $file): string
    {
        return $file === '' ? self::PLACEHOLDER : self::webp($file);
    }

    /**
     * Снимка нет — показываем заглушку, а не битую картинку.
     * Пустая рамка в списке читается как «товара нет», а его просто
     * не успели сфотографировать.
     */
    public static function isEmpty(?string $file): bool
    {
        return $file === null || trim($file) === '';
    }

    private static function suffix(string $file, string $suffix): string
    {
        $dot = strrpos($file, '.');

        return $dot === false ? $file . $suffix : substr($file, 0, $dot) . $suffix . substr($file, $dot);
    }

    private static function webp(string $file): string
    {
        return preg_replace('/\.(jpe?g|png)$/i', '.webp', $file) ?? $file;
    }
}
