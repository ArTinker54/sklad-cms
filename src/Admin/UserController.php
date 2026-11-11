<?php

declare(strict_types=1);

namespace Sklad\Admin;

use Sklad\Core\Audit;

/**
 * Люди и права.
 *
 * Три роли, без конструктора разрешений: их можно объяснить словами, и
 * гибкая система превратилась бы в механизм, которым никто не
 * пользуется, но который все боятся трогать.
 *
 * Существенное: контент-менеджер не видит заявок. Там персональные
 * данные, и доступ к ним должен быть у тех, кому они нужны по работе.
 */
final class UserController extends Controller
{
    protected string $section = 'users';

    private const ROLES = [
        'admin' => 'Администратор — видит и может всё',
        // Подпись читает тот, кто заводит человека, и решает по ней —
        // значит, она должна совпадать с кодом. Прежняя обещала «заявки,
        // без правки цен», а каталог менеджеру открыт целиком: он видит
        // товары нарочно, чтобы понимать, что заказали. Не сказать об
        // этом — значит раздавать роль спокойнее, чем следует. Цены и
        // акции ему теперь и правда не подчиняются (Auth::canPrice).
        'manager' => 'Менеджер — заявки и каталог, цены и акции не правит',
        'content' => 'Контент — товары и тексты, без заявок',
    ];

    public function index(array $params = []): string
    {
        // Кого правим — выбирается ссылкой из списка, а форма приходит
        // уже заполненной. Раньше выбор был выпадающим списком, а поля
        // оставались пустыми: заходя поправить опечатку в имени, человек
        // не трогал роль — и она молча становилась «Администратор»,
        // потому что этот вариант шёл в списке первым.
        $editId = (int) ($_GET['edit'] ?? 0);
        $editing = $editId > 0
            ? $this->app->db->one('SELECT * FROM user WHERE id = ?', [$editId])
            : null;

        return $this->render('admin/users', [
            'title' => 'Люди',
            'rows' => $this->app->db->all('SELECT * FROM user ORDER BY is_active DESC, name'),
            'roles' => self::ROLES,
            'editing' => $editing,
        ]);
    }

    public function save(array $params = []): void
    {
        $this->guard();
        $db = $this->app->db;

        $id = (int) ($_POST['id'] ?? 0);
        $email = mb_strtolower($this->input('email', 190));
        $name = $this->input('name', 190);
        $role = isset(self::ROLES[$_POST['role'] ?? '']) ? (string) $_POST['role'] : 'manager';
        $password = (string) ($_POST['password'] ?? '');
        $active = empty($_POST['is_active']) ? 0 : 1;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('Проверьте почту', 'bad');
            $this->app->redirect($this->app->adminPath('user'));
        }

        // Единственный администратор не должен уметь разжаловать или
        // отключить сам себя: после этого в админку не войдёт никто.
        if ($id > 0 && $id === $this->app->auth->id() && ($role !== 'admin' || !$active)) {
            $this->flash('Нельзя снять права с самого себя — иначе войти станет некому', 'bad');
            $this->app->redirect($this->app->adminPath('user'));
        }

        $fields = ['email' => $email, 'name' => $name, 'role' => $role, 'is_active' => $active];

        // Почта — она же логин, и она уникальна в базе. Совпадение с
        // чужой роняло экран в 500 с текстом ошибки от базы: при
        // заведении такой же случай обрабатывался по-человечески, при
        // правке — нет.
        $busy = $db->value('SELECT name FROM user WHERE email = ? AND id <> ?', [$email, $id]);
        if ($busy !== null) {
            $this->flash('Эта почта уже у другого человека: ' . $busy, 'bad');
            $this->app->redirect($this->app->adminPath('user') . ($id > 0 ? '?edit=' . $id : ''));
        }

        if ($id > 0) {
            $before = $db->one('SELECT * FROM user WHERE id = ?', [$id]);
            if ($password !== '') {
                if (mb_strlen($password) < 12) {
                    $this->flash('Пароль — не короче 12 знаков', 'bad');
                    $this->app->redirect($this->app->adminPath('user'));
                }
                $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $db->update('user', $fields, 'id = ?', [$id]);
            // Пароль в журнал не пишем даже в виде хеша.
            unset($fields['password_hash']);
            (new Audit($db, $this->app->auth))->write('user', $id, 'update', $before ?? [], $fields);
            $this->flash('Сохранено');
        } else {
            if (mb_strlen($password) < 12) {
                $this->flash('Пароль — не короче 12 знаков', 'bad');
                $this->app->redirect($this->app->adminPath('user'));
            }
            if ($db->value('SELECT id FROM user WHERE email = ?', [$email]) !== null) {
                $this->flash('Такая почта уже заведена', 'bad');
                $this->app->redirect($this->app->adminPath('user'));
            }
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $fields['created_at'] = date('Y-m-d H:i:s');
            $newId = $db->insert('user', $fields);
            (new Audit($db, $this->app->auth))->write('user', $newId, 'create', [], ['email' => $email, 'role' => $role]);
            $this->flash('Человек добавлен');
        }

        $this->app->redirect($this->app->adminPath('user'));
    }
}
