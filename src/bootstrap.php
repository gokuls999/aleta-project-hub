<?php
declare(strict_types=1);

// Central bootstrap: config, session, DB handle, tiny helpers.
$GLOBALS['__cfg'] = require __DIR__ . '/../config/config.php';

date_default_timezone_set($GLOBALS['__cfg']['app']['timezone']);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($GLOBALS['__cfg']['app']['session_name']);
    session_start();
}

/** Get a config value by dotted path, e.g. cfg('db.host'). */
function cfg(string $path = '') {
    $c = $GLOBALS['__cfg'];
    if ($path === '') return $c;
    foreach (explode('.', $path) as $k) {
        if (!is_array($c) || !array_key_exists($k, $c)) return null;
        $c = $c[$k];
    }
    return $c;
}

/** Shared PDO connection. */
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $d = cfg('db');
        $dsn = "mysql:host={$d['host']};dbname={$d['name']};charset={$d['charset']}";
        $pdo = new PDO($dsn, $d['user'], $d['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/** HTML-escape helper. */
function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Current app theme (admin-set, global). */
function theme(): array {
    return [
        'look'    => setting_get('theme_look', 'clay'),
        'palette' => setting_get('theme_palette', '1'),
        'font'    => setting_get('theme_font', '1'),
    ];
}
/** data-* attributes for the <body> tag. */
function theme_body_attrs(): string {
    $t = theme();
    return 'data-look="' . e($t['look']) . '" data-palette="' . e($t['palette']) . '" data-font="' . e($t['font']) . '"';
}
/** Google-Fonts <link> covering every selectable font. */
function theme_font_links(): string {
    return '<link rel="preconnect" href="https://fonts.googleapis.com">'
      . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
      . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?'
      . 'family=Baloo+2:wght@400;500;600;700&family=Fredoka:wght@400;500;600;700'
      . '&family=Inter:wght@400;500;600;700;800&family=Nunito:wght@400;500;600;700;800'
      . '&family=Poppins:wght@400;500;600;700&family=Quicksand:wght@400;500;600;700'
      . '&family=Sora:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700'
      . '&family=Work+Sans:wght@400;500;600;700&display=swap">';
}

/** Read/write a row in the settings table. */
function setting_get(string $key, $default = null) {
    $st = db()->prepare('SELECT svalue FROM settings WHERE skey = ?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}
function setting_set(string $key, string $value): void {
    $st = db()->prepare(
        'INSERT INTO settings (skey, svalue) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)'
    );
    $st->execute([$key, $value]);
}
