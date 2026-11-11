<?php

declare(strict_types=1);

namespace Sklad\Admin;

/**
 * Вход и выход.
 *
 * Единственный экран админки, куда пускают без входа, — поэтому и
 * проверка отключена только здесь.
 */
final class AuthController extends Controller
{
    protected function needsAuth(): bool
    {
        return false;
    }

    public function form(array $params = []): string
    {
        if ($this->app->auth->check()) {
            $this->app->redirect($this->app->adminPath());
        }

        return $this->app->view->render('admin/login', [
            'token' => $this->app->auth->token(),
            'adminPath' => fn (string $p = '') => $this->app->adminPath($p),
            'error' => $_GET['error'] ?? null,
        ]);
    }

    public function login(array $params = []): void
    {
        $this->guard();

        // Слишком много промахов с одного адреса — дальше не пробуем.
        // Пароль при этом даже не проверяется: смысл в том, чтобы перебор
        // упирался в стену, а не в скорость проверки хеша.
        if ($this->app->auth->throttled()) {
            $this->app->redirect($this->app->adminPath('login') . '?error=slow');
        }

        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($this->app->auth->attempt($email, $password)) {
            $this->app->db->insert('audit', [
                'user_id' => $this->app->auth->id(),
                'entity' => 'user',
                'entity_id' => $this->app->auth->id(),
                'action' => 'login',
                'changes' => null,
                'ip' => $this->ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $this->app->redirect($this->app->adminPath());
        }

        // Не уточняем, что именно не подошло: подсказка «такой почты нет»
        // помогает подбирать учётки.
        $this->app->redirect($this->app->adminPath('login') . '?error=1');
    }

    public function logout(array $params = []): void
    {
        $this->guard();
        $this->app->auth->logout();
        $this->app->redirect($this->app->adminPath('login'));
    }

    private function ip(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        return $forwarded !== ''
            ? trim(explode(',', $forwarded)[0])
            : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
