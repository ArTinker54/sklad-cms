<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\App;

/**
 * Общее для всех экранов админки.
 *
 * Проверка входа и прав — здесь, а не в каждом обработчике. Забыть
 * поставить её в новом экране слишком легко, а цена забывчивости —
 * открытые наружу заявки с персональными данными.
 */
abstract class Controller
{
    /** Раздел прав, к которому относится экран. Переопределяется наследниками. */
    protected string $section = '';

    public function __construct(protected App $app)
    {
        if ($this->needsAuth()) {
            if (!$this->app->auth->check()) {
                $this->app->redirect($this->app->adminPath('login'));
            }
            if ($this->section !== '' && !$this->app->auth->can($this->section)) {
                http_response_code(403);
                echo $this->render('admin/denied', ['section' => $this->section]);
                exit;
            }
        }
    }

    protected function needsAuth(): bool
    {
        return true;
    }

    protected function render(string $template, array $data = []): string
    {
        $data += [
            'user' => $this->app->auth->user(),
            'adminPath' => fn (string $p = '') => $this->app->adminPath($p),
            'token' => $this->app->auth->token(),
            'flash' => $this->takeFlash(),
        ];

        return $this->app->view->renderIn('admin/layout', $template, $data);
    }

    /** Значение поля формы, обрезанное по длине столбца. */
    protected function input(string $key, int $max = 255): string
    {
        return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
    }

    /**
     * Поля формы, которых в ней не было, — не трогаем.
     *
     * Разница между «стёрли нарочно» и «поля тут просто нет» видна
     * только по тому, пришёл ли ключ вообще. Пока её не различали,
     * отправка неполной формы затирала сохранённое пустотой: я так
     * потерял перенесённый заголовок главной, отправив форму без поля
     * meta_title. На боевом это выглядело бы как просевшие позиции через
     * месяц, и связать одно с другим было бы нечем.
     *
     * @param array<string, mixed> $map поле => значение, посчитанное обработчиком
     * @return array<string, mixed> только те, что форма правда прислала
     */
    protected function onlyGiven(array $map): array
    {
        return array_filter(
            $map,
            static fn (string $key): bool => array_key_exists($key, $_POST),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function int(string $key): ?int
    {
        $value = $_POST[$key] ?? '';

        return $value === '' ? null : (int) $value;
    }

    /**
     * Текст с разметкой из редактора.
     *
     * Администратору отдаём как есть: он и так может всё, а служебные
     * страницы — корзина, сравнение, поиск — держат внутри рабочие
     * скрипты, и чистка их сломала бы.
     *
     * Остальным оставляем только разрешённые теги. Иначе достаточно
     * вписать скрипт в «О компании», и он выполнится в браузере
     * администратора, открывшего эту страницу, с его правами.
     */
    protected function body(string $key = 'body'): string
    {
        $raw = (string) ($_POST[$key] ?? '');

        return ($this->app->auth->user()['role'] ?? '') === 'admin'
            ? $raw
            : \Sklad\Model\Html::clean($raw);
    }

    /**
     * Проверка ключа формы. Без неё чужая страница сможет отправить
     * форму от имени вошедшего администратора.
     */
    protected function guard(): void
    {
        // Тело тяжелее post_max_size PHP отбрасывает целиком: и $_POST, и
        // $_FILES приходят пустыми, ключа формы среди них нет. Пока это не
        // отличали от испорченного ключа, менеджер с четырьмя снимками с
        // телефона получал «форма устарела», обновлял страницу и получал
        // то же самое — настоящая причина не называлась нигде.
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
            && !$_POST
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            exit('Отправленное не поместилось: за раз сервер принимает не больше '
                . $this->postLimit() . '. Добавьте снимки по одному или уменьшите их.');
        }

        if (!$this->app->auth->checkToken($_POST['_token'] ?? null)) {
            http_response_code(400);
            exit('Форма устарела. Обновите страницу и попробуйте снова.');
        }
    }

    /**
     * Предел на размер запроса — из настроек PHP, а не числом в коде.
     *
     * Зашитое число разойдётся с настройками при первой правке на
     * хостинге, и человеку назовут предел, которого нет.
     */
    private function postLimit(): string
    {
        $limit = strtoupper(trim((string) ini_get('post_max_size')));
        if ($limit === '') {
            return 'разрешённого сервером';
        }

        // Значение может быть задано и просто в байтах — переводим сами.
        return ctype_digit($limit)
            ? round((int) $limit / 1048576, 1) . ' МБ'
            : strtr($limit, ['K' => ' КБ', 'M' => ' МБ', 'G' => ' ГБ']);
    }

    /** Сообщение, которое переживёт переадресацию после сохранения. */
    protected function flash(string $text, string $kind = 'ok'): void
    {
        // Сессия теперь заводится не всем подряд, а только тем, кому нужна:
        // сообщение как раз такой случай. Без этого оно молча пропадало бы
        // у того, кто ещё не вошёл, — например при неудачном входе.
        $this->app->auth->start();
        $_SESSION['flash'] = ['text' => $text, 'kind' => $kind];
    }

    /**
     * Набранное в форме, чтобы оно пережило переадресацию после отказа.
     *
     * Отказ сохранить — не повод выбрасывать работу: без этого форма
     * перерисовывалась из базы, и переписанные абзацы пропадали вместе с
     * опечаткой в одном поле, из-за которой отказ и случился.
     *
     * @param array<string, mixed> $values поле => присланное значение
     */
    protected function keepInput(array $values): void
    {
        $this->app->auth->start();
        $_SESSION['old'] = $values;
    }

    /**
     * Забираем набранное один раз, как flash: иначе оно всплыло бы поверх
     * чужой карточки, открытой следующей.
     *
     * @return array<string, mixed>
     */
    protected function oldInput(): array
    {
        $old = $_SESSION['old'] ?? null;
        unset($_SESSION['old']);

        return is_array($old) ? $old : [];
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $flash;
    }
}
