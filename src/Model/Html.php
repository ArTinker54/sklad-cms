<?php

declare(strict_types=1);

namespace Sklad\Model;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Очистка разметки, пришедшей из редактора.
 *
 * Нужна не от злого умысла, а по устройству прав: тексты правит
 * контент-менеджер, а читает их страница, открытая в том числе
 * администратором. Без очистки достаточно вписать скрипт в «О компании»,
 * и он выполнится в браузере администратора с его сессией — то есть
 * контент-менеджер получит доступ ко всему.
 *
 * Список разрешённого, а не запрещённого. Запретный список всегда
 * неполон: обойти его можно новым атрибутом, о котором никто не подумал,
 * а разрешительный при том же незнании просто выбросит незнакомое.
 *
 * Администратора не касается: он и так может всё, а служебные страницы —
 * корзина, сравнение, поиск — держат внутри рабочие скрипты, и чистить
 * их значило бы их сломать.
 */
final class Html
{
    /** Тег => разрешённые в нём атрибуты. */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'hr' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [],
        'th' => ['scope', 'colspan', 'rowspan'],
        'td' => ['colspan', 'rowspan'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [],
        'div' => ['class'], 'span' => ['class'],
    ];

    /** Классы, которые оставляем: остальные — чужие, из старой вёрстки. */
    private const CLASSES = ['prose', 'note', 'specs', 'lead', 'muted'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');

        /*
         * Разметка приходит куском, а не документом: оборачиваем, иначе
         * DOMDocument достроит html и body сам и они окажутся в выводе.
         *
         * Обёртка — выдуманный тег, а не <div>. Причина такая: в текстах,
         * перенесённых со старого сайта, попадается лишний закрывающий
         * </div>. В редакторе его не видно, и человек о нём не знает, а
         * разборщик читает его как «закрыли обёртку» — всё, что дальше,
         * оказывается снаружи, и при первом же сохранении хвост страницы
         * молча пропадал. Пропажа обнаруживается не в редакторе, а на
         * сайте, когда её уже нечем объяснить.
         *
         * Закрыть выдуманный тег чужим закрывающим нельзя: имена не
         * совпадают, и разборщик просто выбрасывает лишний. Заодно на
         * всякий случай убираем из текста само это имя.
         */
        $html = str_ireplace('sklad-root', '', $html);
        $wrapped = '<?xml encoding="UTF-8"><sklad-root id="sklad-root">' . $html . '</sklad-root>';

        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('sklad-root');
        if ($root === null) {
            return '';
        }

        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * Обходим дерево снизу вверх.
     *
     * Именно снизу: убирая узел, мы сдвигаем список соседей, и обход
     * сверху пропускал бы каждый второй.
     */
    private static function walk(DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child === null) {
                continue;
            }

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) {
                continue;                       // текст оставляем как есть
            }

            self::walk($child);

            $tag = strtolower($child->nodeName);
            if (!isset(self::ALLOWED[$tag])) {
                // Тег не разрешён — выбрасываем его, но не содержимое:
                // текст внутри чужой обёртки писал человек, и терять его
                // из-за обёртки незачем. Скрипт и стили — исключение:
                // там весь смысл в содержимом.
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED[$tag];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attribute = $element->attributes->item($i);
            if ($attribute === null) {
                continue;
            }
            $name = strtolower($attribute->nodeName);

            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            // Адрес вида javascript:… выполняет код по нажатию ссылки.
            if (($name === 'href' || $name === 'src') && !self::safeUrl($attribute->nodeValue ?? '')) {
                $element->removeAttribute($attribute->nodeName);
                continue;
            }

            if ($name === 'class') {
                $keep = array_intersect(
                    preg_split('/\s+/', (string) $attribute->nodeValue) ?: [],
                    self::CLASSES
                );
                $keep ? $element->setAttribute('class', implode(' ', $keep)) : $element->removeAttribute('class');
            }
        }

        // Ссылка в новое окно без rel даёт открытой странице доступ к
        // нашей через window.opener.
        if ($tag === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener');
        }

        // Картинка, у которой мы отобрали адрес, — обычно остаток от
        // вычищенного обработчика. Пустой img рисуется битой рамкой,
        // и в тексте это выглядит поломкой, а не защитой.
        if ($tag === 'img' && trim($element->getAttribute('src')) === '') {
            $element->parentNode?->removeChild($element);
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        // Относительные, якоря, почта и телефон — свои.
        if (preg_match('~^(/|\#|\?|mailto:|tel:)~i', $url)) {
            return true;
        }

        return (bool) preg_match('~^https?://~i', $url);
    }
}
