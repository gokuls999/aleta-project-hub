<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

/** Currently logged-in user row, or null. */
function current_user(): ?array {
    static $u = null;
    if ($u !== null) return $u ?: null;
    if (empty($_SESSION['uid'])) { $u = false; return null; }
    $st = db()->prepare('SELECT * FROM users WHERE id = ? AND is_active = 1');
    $st->execute([$_SESSION['uid']]);
    $u = $st->fetch() ?: false;
    return $u ?: null;
}

/** Redirect to login unless authenticated; returns the user row. */
function require_login(): array {
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

/** Like require_login but also enforces admin role. */
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { http_response_code(403); exit('Admins only.'); }
    return $u;
}

/** Validate credentials and start a session. */
function attempt_login(string $email, string $pass): bool {
    $st = db()->prepare('SELECT * FROM users WHERE zoho_email = ? AND is_active = 1');
    $st->execute([strtolower(trim($email))]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        return true;
    }
    return false;
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** CSRF helpers. */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_check(?string $t): bool {
    return !empty($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
