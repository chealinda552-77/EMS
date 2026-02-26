<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_login()
{
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to continue.');
        redirect(url('index.php'));
    }
}

function current_user()
{
    static $cachedUser = false;

    if ($cachedUser !== false) {
        return $cachedUser;
    }

    if (!is_logged_in()) {
        $cachedUser = null;
        return null;
    }

    $statement = db()->prepare('SELECT id, username, role FROM users WHERE id = :id LIMIT 1');
    $statement->execute(array('id' => $_SESSION['user_id']));
    $user = $statement->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        $cachedUser = null;
        return null;
    }

    $cachedUser = $user;
    return $cachedUser;
}

function login_user($username, $password)
{
    $statement = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = :username LIMIT 1');
    $statement->execute(array('username' => $username));
    $user = $statement->fetch();

    if (!$user || !password_verify((string) $password, (string) $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    return true;
}

function logout_user()
{
    $_SESSION = array();

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
