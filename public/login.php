<?php
require_once __DIR__ . '/../src/auth.php';
if (current_user()) { header('Location: dashboard.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'Session expired — please try again.';
    } elseif (attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        header('Location: dashboard.php'); exit;
    } else {
        $err = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in — Aleta Work Tracker</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { display: grid; place-items: center; min-height: 100vh;
      background: radial-gradient(900px 500px at 15% -10%, #c9c4ff 0%, #e8e9fb 55%, #eef0fe 100%); }
    .login { width: 380px; }
    .login .head { text-align: center; margin-bottom: 22px; }
    .login .logo { width: 64px; height: 64px; border-radius: 20px; margin: 0 auto 14px;
      background: linear-gradient(160deg,#7d6cf5,#6c5ce7); display: grid; place-items: center;
      color: #fff; font-family: var(--font-head); font-weight: 700; font-size: 30px;
      box-shadow: 8px 8px 20px rgba(108,92,231,.35), -6px -6px 16px rgba(255,255,255,.9),
                  inset 2px 2px 5px rgba(255,255,255,.4), inset -3px -3px 8px rgba(74,58,167,.4); }
    .login .head h1 { font-family: var(--font-head); font-size: 26px; margin: 0; color: var(--ink); }
    .login .head p { color: var(--ink-2); font-size: 15px; font-weight: 600; margin: 4px 0 0; }
    .login .card { padding: 30px; }
    .login .hint { text-align: center; color: var(--muted); font-size: 13px; font-weight: 600; margin-top: 18px; }
  </style>
</head>
<body>
  <div class="login">
    <div class="head">
      <div class="logo">A</div>
      <h1>Aleta Work Tracker</h1>
      <p>Staff sign in</p>
    </div>
    <div class="card">
      <?php if ($err): ?><div class="note warn" style="margin:0 0 12px"><?= e($err) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Zoho email</label>
        <input type="email" name="email" required autofocus placeholder="you@aletasoftwarelabs.in" value="<?= e($_POST['email'] ?? '') ?>">
        <label>Password</label>
        <input type="password" name="password" required placeholder="••••••••">
        <div style="margin-top:20px"><button class="btn" style="width:100%;justify-content:center">Sign in</button></div>
      </form>
    </div>
    <p class="hint">Use your Zoho email and the password set by your admin.</p>
  </div>
</body>
</html>
