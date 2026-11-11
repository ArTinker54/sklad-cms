<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;
use Sklad\Model\Media;
use Throwable;

/**
 * Слайды первого экрана главной.
 *
 * Заказчик 04.11.2026: «сделать возможность менять Ирине картинку на
 * главной». Слайды — файлы public/assets/img/hero/slide01.jpg, slide02.jpg…
 * подряд, без дыр: шаблон главной считает их от первого до первого
 * недостающего. Поэтому здесь нет таблицы — только эти файлы: заменить,
 * добавить, убрать, поменять местами.
 *
 * Размер у всех слайдов один — как у первого (у сети 1920×420, у старых
 * сайтов марок свои): по нему считается высота первого экрана на большом
 * мониторе. Новый снимок обрезается под этот размер (Media::storeSlide).
 *
 * Заменённый или убранный слайд не стирается, а уходит в storage/hero-trash
 * с датой: перепутали файл — прежний можно вернуть.
 */
final class HeroController extends Controller
{
    protected string $section = 'content';

    /** Размер слайдов сети, если своих ещё нет. */
    private const DEFAULT_SIZE = [1920, 420];
    /** Больше слайдов первому экрану не нужно — их и так листают редко. */
    private const MAX_SLIDES = 8;
    private const FOCUS = ['center', 'top', 'bottom', 'left', 'right'];

    public function index(array $params = []): string
    {
        $slides = [];
        foreach ($this->files() as $i => $path) {
            $size = @getimagesize($path) ?: [0, 0];
            $slides[] = [
                'n' => $i,
                'url' => $this->url($i) . '?v=' . (int) @filemtime($path),
                'width' => (int) $size[0],
                'height' => (int) $size[1],
                'time' => (int) @filemtime($path),
            ];
        }

        $s = $this->app->settings;
        $eyebrow = (string) ($s->get('hero_eyebrow') ?: 'Официальный дилер в России');
        $title = (string) ($s->get('hero_title') ?: 'Складская техника');
        $brand = (string) $s->get('brand', '');

        $brandHeroPath = $this->app->root() . '/public/assets/img/brand-hero.png';
        $brandHeroUrl = is_file($brandHeroPath)
            ? '/assets/img/brand-hero.png?v=' . (int) @filemtime($brandHeroPath)
            : null;

        $db = $this->app->db;
        $total = 0;
        try {
            $total = (int) $db->value('SELECT COUNT(*) FROM product WHERE deleted_at IS NULL');
        } catch (Throwable) {}

        $plural = static function (int $n, string $one, string $few, string $many): string {
            $mod100 = $n % 100;
            $mod10 = $n % 10;
            if ($mod100 >= 11 && $mod100 <= 14) {
                return $many;
            }

            return $mod10 === 1 ? $one : ($mod10 >= 2 && $mod10 <= 4 ? $few : $many);
        };

        $lead = (string) ($s->get('hero_lead')
            ?: 'Тележки, штабелёры, ричтраки, электротягачи и подъёмные столы. %d от производителя. Работаем с НДС. Отгрузка по всей территории РФ.');
        $lead = str_replace(
            '%d',
            $total . ' ' . $plural($total, 'модель', 'модели', 'моделей'),
            $lead
        );

        $cta2 = trim((string) $s->get('hero_cta2'));
        $cta2 = $cta2 !== '' ? $cta2 : 'Подобрать запчасти';

        $figures = [];
        if ($rawFigures = (string) $s->get('hero_figures', '')) {
            foreach (preg_split('/(?:\r\n|\r|\n)/u', $rawFigures) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, '|')) {
                    continue;
                }
                [$num, $caption] = explode('|', $line, 2);
                $figures[] = ['num' => trim($num), 'label' => trim($caption)];
            }
        }

        return $this->render('admin/hero', [
            'title' => 'Картинки главной',
            'slides' => $slides,
            'size' => $this->size(),
            'max' => self::MAX_SLIDES,
            'heroOverlay' => [
                'eyebrow' => $eyebrow,
                'title' => $title,
                'brand' => $brand,
                'brandHeroUrl' => $brandHeroUrl,
                'lead' => $lead,
                'cta2' => $cta2,
                'figures' => $figures,
            ],
        ]);
    }

    /** Заменить слайд n или (n = 0) добавить новый в конец. */
    public function upload(array $params = []): void
    {
        $this->guard();
        $files = $this->files();
        $n = (int) ($_POST['n'] ?? 0);
        if ($n !== 0 && !isset($files[$n])) {
            $this->back('Такого слайда нет — обновите страницу.', 'bad');
        }
        if ($n === 0 && count($files) >= self::MAX_SLIDES) {
            $this->back('Слайдов уже ' . self::MAX_SLIDES . ' — замените один из них или уберите лишний.', 'bad');
        }
        $slot = $n ?: count($files) + 1;
        $focus = in_array($_POST['focus'] ?? '', self::FOCUS, true) ? $_POST['focus'] : 'center';
        [$width, $height] = $this->size();

        if ($n !== 0) {
            $this->keep($files[$n], $n);
        }
        try {
            $done = (new Media($this->app->db, $this->app->root() . '/public'))
                ->storeSlide($_FILES['image'] ?? [], $this->path($slot), $width, $height, $focus);
        } catch (Throwable $e) {
            $this->back($e->getMessage(), 'bad');
        }

        (new Audit($this->app->db, $this->app->auth))->write(
            'hero', $slot, $n !== 0 ? 'update' : 'create', [], ['file' => basename($this->path($slot))]
        );

        [$sw, $sh] = $done['source'];
        [$ow, $oh] = $done['size'];
        $message = ($n !== 0 ? 'Слайд ' . $n . ' заменён' : 'Слайд ' . $slot . ' добавлен') . " ({$ow}×{$oh}).";
        if ($ow < $width) {
            $message .= " Снимок меньше нужного ({$sw}×{$sh}, а нужно {$width}×{$height}):"
                . ' на большом мониторе он будет мутным. Лучше загрузить крупнее.';
            $this->back($message, 'bad');
        }
        $this->back($message . ' На главной он уже стоит.');
    }

    public function remove(array $params = []): void
    {
        $this->guard();
        $files = $this->files();
        $n = (int) ($params['n'] ?? 0);
        if (!isset($files[$n])) {
            $this->back('Такого слайда нет — обновите страницу.', 'bad');
        }
        // Без слайдов первый экран — тёмный прямоугольник с текстом:
        // последний не убираем, его можно только заменить.
        if (count($files) === 1) {
            $this->back('Это последний слайд — его можно заменить, но не убрать.', 'bad');
        }

        $this->keep($files[$n], $n);
        // Сдвигаем следующие на место убранного: дыра в нумерации оборвала
        // бы показ — шаблон главной считает слайды до первого пропуска.
        $last = count($files);
        if (!@unlink($files[$n])) {
            $this->back('Не удалось убрать файл слайда.', 'bad');
        }
        for ($i = $n + 1; $i <= $last; $i++) {
            @rename($files[$i], $this->path($i - 1));
        }

        (new Audit($this->app->db, $this->app->auth))->write('hero', $n, 'delete', ['file' => basename($files[$n])], []);
        $this->back('Слайд ' . $n . ' убран. Он сохранён в архиве на сервере — если нужно, его можно вернуть.');
    }

    /** Поменять слайд местами с соседним: dir = up (раньше) или down (позже). */
    public function move(array $params = []): void
    {
        $this->guard();
        $files = $this->files();
        $n = (int) ($params['n'] ?? 0);
        $m = ($_POST['dir'] ?? '') === 'up' ? $n - 1 : $n + 1;
        if (!isset($files[$n], $files[$m])) {
            $this->back('Двигать некуда — обновите страницу.', 'bad');
        }

        $tmp = $files[$n] . '.swap-' . bin2hex(random_bytes(4));
        if (!@rename($files[$n], $tmp) || !@rename($files[$m], $files[$n]) || !@rename($tmp, $files[$m])) {
            $this->back('Не удалось поменять слайды местами.', 'bad');
        }
        // Обоим файлам — новое время: по нему адрес снимка на главной
        // меняется, и браузер не покажет на этом месте прежний.
        @touch($files[$n]);
        @touch($files[$m]);

        (new Audit($this->app->db, $this->app->auth))->write('hero', $n, 'update', ['место' => $n], ['место' => $m]);
        $this->back('Слайды ' . min($n, $m) . ' и ' . max($n, $m) . ' поменялись местами.');
    }

    /** @return array<int, string> номер слайда → путь к файлу, подряд с первого */
    private function files(): array
    {
        $files = [];
        for ($i = 1; is_file($this->path($i)); $i++) {
            $files[$i] = $this->path($i);
        }

        return $files;
    }

    /**
     * Размер слайдов сайта: пропорция — первого слайда в тех же пределах 2,4–5,
     * что берёт шаблон главной, ширина — не меньше 1920. У старых сайтов марок
     * первый слайд в 1110 точек, и новые снимки не должны мельчать вслед за ним.
     *
     * @return array{0:int,1:int}
     */
    private function size(): array
    {
        return self::DEFAULT_SIZE;
    }

    private function path(int $n): string
    {
        return $this->app->root() . '/public' . $this->url($n);
    }

    private function url(int $n): string
    {
        return sprintf('/assets/img/hero/slide%02d.jpg', $n);
    }

    /** Копия слайда перед заменой или удалением — в storage, снаружи её не видно. */
    private function keep(string $path, int $n): void
    {
        $dir = $this->app->root() . '/storage/hero-trash';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @copy($path, $dir . '/' . date('Ymd-His') . sprintf('-slide%02d.jpg', $n));
    }

    private function back(string $message, string $kind = 'ok'): never
    {
        $this->flash($message, $kind);
        $this->app->redirect($this->app->adminPath('hero'));
        exit;
    }
}
