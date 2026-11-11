<?php

declare(strict_types=1);

namespace Sklad\Model;

use RuntimeException;
use Sklad\Core\Db;

/**
 * Приём фотографий.
 *
 * Менеджер льёт снимки прямо с телефона — там бывает и восемь мегабайт
 * на кадр. Без пережатия страница раздела с двумя десятками таких
 * плиток грузится минуту, и виноват в этом будет сайт, а не снимок.
 *
 * Поэтому файл принимается, уменьшается до разумного и сохраняется в
 * двух размерах и двух форматах: webp для тех, кто его понимает, jpeg
 * запасным. Исходник не храним — он больше не нужен, а место занимает.
 */
final class Media
{
    /** Больше 1600 точек по длинной стороне на витрине не нужно. */
    private const FULL = 1600;
    /** Плитка в списке. */
    private const CARD = 640;
    /** Больше — не распаковываем (около 40 Мп: 6000×6000 с запасом под поворот). */
    private const MAX_PIXELS = 40_000_000;

    private const ALLOWED = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    public function __construct(
        private Db $db,
        private string $publicRoot
    ) {
    }

    /**
     * Кладёт загруженный файл и заводит запись.
     *
     * @param array $file элемент из $_FILES
     * @return int идентификатор записи
     */
    public function accept(array $file, string $folder = 'products'): int
    {
        $info = $this->check($file);

        $hash = sha1_file($file['tmp_name']) ?: bin2hex(random_bytes(20));

        // Тот же файл уже мог быть загружен для другого товара — снимки
        // моделей одной линейки часто совпадают.
        $existing = $this->db->value('SELECT id FROM media WHERE hash = ?', [$hash]);
        if ($existing !== null) {
            return (int) $existing;
        }

        $source = $this->open($file['tmp_name'], $info[2]);
        if ($source === null) {
            throw new RuntimeException('Не удалось прочитать фотографию.');
        }

        $dir = '/upload/' . trim($folder, '/');
        $absoluteDir = $this->publicRoot . $dir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Нет папки для загрузок и её не создать.');
        }

        $name = substr($hash, 0, 16);
        $full = $this->save($source, $absoluteDir . '/' . $name, self::FULL);
        $this->save($source, $absoluteDir . '/' . $name . '-card', self::CARD);
        imagedestroy($source);

        return $this->db->insert('media', [
            'file' => $dir . '/' . $name . '.jpg',
            'original_name' => mb_substr((string) ($file['name'] ?? ''), 0, 255),
            'mime' => 'image/jpeg',
            'width' => $full[0],
            'height' => $full[1],
            'size' => filesize($absoluteDir . '/' . $name . '.jpg') ?: null,
            'hash' => $hash,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Картинка без записи в библиотеку снимков — для разделов каталога.
     *
     * Снимки товаров живут в таблице media и показываются в «Медиатеке»;
     * картинка раздела — своя у раздела, и в общий поток фото товаров ей не
     * место (заказчик 04.10.2026: «фото там все общим потоком, а мне нужны
     * под разделы»). Обработка та же: проверка по содержимому, поворот по
     * EXIF, два размера, jpeg и webp.
     *
     * @return array{file: string, width: int, height: int, source: array{0:int,1:int}}
     */
    public function storeImage(array $file, string $folder): array
    {
        $info = $this->check($file);
        $hash = sha1_file($file['tmp_name']) ?: bin2hex(random_bytes(20));

        $source = $this->open($file['tmp_name'], $info[2]);
        if ($source === null) {
            throw new RuntimeException('Не удалось прочитать картинку.');
        }
        $original = [imagesx($source), imagesy($source)];

        $dir = '/upload/' . trim($folder, '/');
        $absoluteDir = $this->publicRoot . $dir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Нет папки для загрузок и её не создать.');
        }

        $name = substr($hash, 0, 16);
        $full = $this->save($source, $absoluteDir . '/' . $name, self::FULL);
        $this->save($source, $absoluteDir . '/' . $name . '-card', self::CARD);
        imagedestroy($source);

        return ['file' => $dir . '/' . $name . '.jpg', 'width' => $full[0], 'height' => $full[1], 'source' => $original];
    }

    /**
     * Слайд первого экрана главной — в пропорции слайдов сайта.
     *
     * Заказчик 04.11.2026: «сделать возможность менять Ирине картинку на
     * главной». Высота первого экрана на большом мониторе идёт по пропорции
     * первого слайда (main.css, «Большие экраны»), поэтому загруженное
     * обрезаем под неё: иначе сайт резал бы снимок сам, по центру, и мачта
     * снова уходила бы за край. Что оставить — выбирает человек ($focus:
     * center, top, bottom, left, right); лишнее срезается с другой стороны.
     * Не растягиваем: узкий снимок остаётся своей ширины, мутнеть ему не с чего.
     *
     * Пишем во временный файл и переименовываем: на сайте никогда не бывает
     * недописанного слайда, а прежний остаётся на месте, если что-то упало.
     *
     * @return array{source: array{0:int,1:int}, size: array{0:int,1:int}}
     */
    public function storeSlide(array $file, string $target, int $width, int $height, string $focus = 'center'): array
    {
        $info = $this->check($file);
        $source = $this->open($file['tmp_name'], $info[2]);
        if ($source === null) {
            throw new RuntimeException('Не удалось прочитать картинку.');
        }
        $sw = imagesx($source);
        $sh = imagesy($source);

        // Рамка нужной пропорции наибольшего размера внутри снимка.
        $ratio = $width / $height;

        // Если снимок заметно выше нужной полосы (например, 1920×600 при норме 1920×420)
        // и выбран 'fit' (или 'center'), вписываем технику пропорционально по высоте,
        // чтобы не срезать мачты и ручки, а фон бесшовно продлеваем влево.
        if ($focus === 'fit' || ($focus === 'center' && $sw >= 1600 && $sh > $height && ($sw / $sh) < ($ratio * 0.88))) {
            $scale = $height / $sh;
            $scaledW = (int) round($sw * $scale);
            $scaledH = $height;

            $scaled = imagecreatetruecolor($scaledW, $scaledH);
            imagecopyresampled($scaled, $source, 0, 0, 0, 0, $scaledW, $scaledH, $sw, $sh);
            imagedestroy($source);

            // Семплируем цвет фона с левого края
            $rTotal = 0; $gTotal = 0; $bTotal = 0; $samples = 0;
            for ($sy = 0; $sy < $scaledH; $sy += 4) {
                for ($sx = 0; $sx < min(6, $scaledW); $sx++) {
                    $rgb = imagecolorat($scaled, $sx, $sy);
                    $rTotal += ($rgb >> 16) & 0xFF;
                    $gTotal += ($rgb >> 8) & 0xFF;
                    $bTotal += $rgb & 0xFF;
                    $samples++;
                }
            }
            $bgR = (int) round($rTotal / max(1, $samples));
            $bgG = (int) round($gTotal / max(1, $samples));
            $bgB = (int) round($bTotal / max(1, $samples));

            $canvas = imagecreatetruecolor($width, $height);
            $bgCol = imagecolorallocate($canvas, $bgR, $bgG, $bgB);
            imagefill($canvas, 0, 0, $bgCol);

            // Безопасная правая граница (safe right):
            // для 1920 это 1440 (как на эталоне Hangcha), чтобы на iPad (1194px и 1024px)
            // техника не срезалась правым краем экрана.
            $safeRight = (int) round($width * 0.75); // 1440 при 1920
            $pasteX = max(0, $safeRight - $scaledW);

            // Продлеваем правый край от $safeRight до $width
            $blendR = 0;
            if ($safeRight < $width) {
                // Копируем вертикальную полоску с правого края $scaled
                $rightSliceW = $width - $safeRight;
                imagecopyresampled($canvas, $scaled, $safeRight, 0, $scaledW - 1, 0, $rightSliceW, $scaledH, 1, $scaledH);

                // Плавное сглаживание на стыке перед $safeRight
                $blendR = min(60, (int) round($scaledW / 8));
                for ($bx = 0; $bx < $blendR; $bx++) {
                    $alpha = $bx / (float) $blendR;
                    for ($by = 0; $by < $scaledH; $by++) {
                        $srcRgb = imagecolorat($scaled, $scaledW - $blendR + $bx, $by);
                        $sr = ($srcRgb >> 16) & 0xFF;
                        $sg = ($srcRgb >> 8) & 0xFF;
                        $sb = $srcRgb & 0xFF;

                        $edgeRgb = imagecolorat($scaled, $scaledW - 1, $by);
                        $er = ($edgeRgb >> 16) & 0xFF;
                        $eg = ($edgeRgb >> 8) & 0xFF;
                        $eb = $edgeRgb & 0xFF;

                        $r = (int) round($sr * (1.0 - $alpha) + $er * $alpha);
                        $g = (int) round($sg * (1.0 - $alpha) + $eg * $alpha);
                        $b = (int) round($sb * (1.0 - $alpha) + $eb * $alpha);

                        imagesetpixel($canvas, $safeRight - $blendR + $bx, $by, imagecolorallocate($canvas, $r, $g, $b));
                    }
                }
            }

            // Копируем основное тело
            $blendW = min(120, (int) round($scaledW / 4));
            $copyW = $scaledW - $blendW - $blendR;
            if ($copyW > 0) {
                imagecopy($canvas, $scaled, $pasteX + $blendW, 0, $blendW, 0, $copyW, $scaledH);
            }

            // Бесшовный переход слева
            for ($bx = 0; $bx < $blendW; $bx++) {
                $alpha = $bx / (float) $blendW;
                for ($by = 0; $by < $scaledH; $by++) {
                    $srcRgb = imagecolorat($scaled, $bx, $by);
                    $sr = ($srcRgb >> 16) & 0xFF;
                    $sg = ($srcRgb >> 8) & 0xFF;
                    $sb = $srcRgb & 0xFF;

                    $r = (int) round($bgR * (1.0 - $alpha) + $sr * $alpha);
                    $g = (int) round($bgG * (1.0 - $alpha) + $sg * $alpha);
                    $b = (int) round($bgB * (1.0 - $alpha) + $sb * $alpha);

                    imagesetpixel($canvas, $pasteX + $bx, $by, imagecolorallocate($canvas, $r, $g, $b));
                }
            }
            imagedestroy($scaled);

            $dir = dirname($target);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Нет папки для слайдов и её не создать.');
            }
            $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
            imageinterlace($canvas, true);
            $ok = imagejpeg($canvas, $tmp, 92);
            imagedestroy($canvas);
            if (!$ok || !rename($tmp, $target)) {
                @unlink($tmp);
                throw new RuntimeException('Не удалось записать слайд на диск.');
            }

            return ['source' => [$sw, $sh], 'size' => [$width, $height]];
        }

        $cw = $sw;
        $ch = (int) round($sw / $ratio);
        if ($ch > $sh) {
            $ch = $sh;
            $cw = (int) round($sh * $ratio);
        }
        $x = match ($focus) {
            'left' => 0,
            'right' => $sw - $cw,
            default => intdiv($sw - $cw, 2),
        };
        $y = match ($focus) {
            'top' => 0,
            'bottom' => $sh - $ch,
            default => intdiv($sh - $ch, 2),
        };

        $ow = min($width, $cw);
        $oh = max(1, (int) round($ow / $ratio));
        $canvas = imagecreatetruecolor($ow, $oh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $ow, $oh, $cw, $ch);
        imagedestroy($source);

        $dir = dirname($target);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Нет папки для слайдов и её не создать.');
        }
        $tmp = $target . '.tmp-' . bin2hex(random_bytes(4));
        imageinterlace($canvas, true);
        $ok = imagejpeg($canvas, $tmp, 84);
        imagedestroy($canvas);
        if (!$ok || !rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Не удалось записать слайд на диск.');
        }

        return ['source' => [$sw, $sh], 'size' => [$ow, $oh]];
    }

    /**
     * Загруженный файл — правда картинка нужного вида?
     *
     * @return array getimagesize()
     */
    private function check(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException($this->explainError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new RuntimeException('Файл не похож на загруженный.');
        }

        // Тип определяем по содержимому, а не по расширению: расширение
        // пишет тот, кто загружает, и доверять ему нельзя.
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !isset(self::ALLOWED[$info[2]])) {
            throw new RuntimeException('Это не фотография. Подойдут JPEG, PNG или WebP.');
        }

        // Предел по точкам — до распаковки в GD. Лёгкий файл с огромной
        // заявленной картинкой (или кадр 50 Мп с поворотом) съедал всю память
        // PHP, и вместо понятного сообщения человек видел пустую страницу:
        // нехватку памяти catch не ловит (ревью 04.10.2026).
        if ((int) $info[0] * (int) $info[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Картинка слишком большая: ' . $info[0] . '×' . $info[1]
                . ' точек. Уменьшите до 6000 точек по длинной стороне.');
        }

        return $info;
    }

    /** @return array{0:int,1:int} размеры сохранённого */
    private function save(\GdImage $source, string $pathWithoutExtension, int $limit): array
    {
        $width = imagesx($source);
        $height = imagesy($source);

        $scale = min(1, $limit / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        // Техника снята на белом. Заливаем белым до копирования, иначе
        // прозрачные PNG станут чёрными.
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        imagejpeg($canvas, $pathWithoutExtension . '.jpg', 84);
        if (function_exists('imagewebp')) {
            imagewebp($canvas, $pathWithoutExtension . '.webp', 82);
        }
        imagedestroy($canvas);

        return [$newWidth, $newHeight];
    }

    private function open(string $path, int $type): ?\GdImage
    {
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => false,
        };

        if ($image === false) {
            return null;
        }

        // Снятый вертикально телефонный кадр физически лежит
        // горизонтально, а поворот задан тегом EXIF. imagecreatefromjpeg
        // тег не читает, и на витрину снимок попадал на боку. В
        // проводнике и в окне выбора файла менеджер видит его правильно,
        // поэтому причину не понять, а поправить в админке нечем —
        // доворачиваем здесь, до пережатия, чтобы оба размера ушли
        // на диск уже как надо.
        return $type === IMAGETYPE_JPEG ? $this->applyOrientation($image, $path) : $image;
    }

    /**
     * Доворачивает кадр по тегу EXIF Orientation.
     *
     * Расширение exif собрано не везде, и без него функции просто нет.
     * Снимок без поворота — беда небольшая, а падение на ней уронило бы
     * всю загрузку, поэтому наличие проверяем.
     */
    private function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        if (!is_array($exif)) {
            return $image;
        }

        $orientation = (int) ($exif['Orientation'] ?? 0);

        // Четыре значения из восьми — это ещё и зеркало (кадр с
        // фронтальной камеры). Без отражения такой снимок выйдет
        // зеркальным, и надписи на технике станет не прочесть.
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate крутит против часовой, а тег задаёт поворот по
        // часовой — отсюда 270 там, где в таблице EXIF стоит 90.
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270,
            5, 8 => 90,
            default => 0,
        };
        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    /**
     * Человеческое объяснение вместо номера ошибки.
     *
     * «Файл больше допустимого» менеджеру понятно, UPLOAD_ERR_INI_SIZE —
     * нет, а звонить он будет всё равно мне.
     */
    private function explainError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой. Предел — 16 МБ.',
            UPLOAD_ERR_PARTIAL => 'Файл долетел не целиком. Попробуйте ещё раз.',
            UPLOAD_ERR_NO_FILE => 'Файл не выбран.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Серверу некуда записать файл — это к хостингу.',
            default => 'Файл не загрузился.',
        };
    }
}
