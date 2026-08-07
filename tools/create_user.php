<?php
// CLI: create or update a staff/admin user.
// Usage: php tools/create_user.php <email> "<Full Name>" <password> [admin|staff]
require __DIR__ . '/../src/bootstrap.php';

$email = $argv[1] ?? null;
$name  = $argv[2] ?? null;
$pass  = $argv[3] ?? null;
$role  = ($argv[4] ?? 'staff') === 'admin' ? 'admin' : 'staff';

if (!$email || !$name || !$pass) {
    fwrite(STDERR, "usage: php tools/create_user.php <email> \"<Full Name>\" <password> [admin|staff]\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_DEFAULT);
$st = db()->prepare(
    'INSERT INTO users (zoho_email, password_hash, full_name, role, must_reset)
     VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash),
        full_name = VALUES(full_name), role = VALUES(role), is_active = 1'
);
$st->execute([strtolower(trim($email)), $hash, $name, $role]);

echo "OK — user saved: " . strtolower(trim($email)) . " ($role)\n";
