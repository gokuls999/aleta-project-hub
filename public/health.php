<?php
// Diagnostic health check (was the Part 1 landing). Reach it at /health.php.
require __DIR__ . '/../src/bootstrap.php';

$checks = [];
$checks['PHP version'] = ['ok' => version_compare(PHP_VERSION, '7.4', '>='), 'val' => PHP_VERSION];
try {
    db()->query('SELECT 1');
    $checks['Database connection'] = ['ok' => true, 'val' => cfg('db.name') . ' @ ' . cfg('db.host')];
} catch (Throwable $ex) {
    $checks['Database connection'] = ['ok' => false, 'val' => $ex->getMessage()];
}
try {
    $tables = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $want = ['users','projects','task_lists','tasks','time_logs','sync_runs','settings','journals'];
    $missing = array_diff($want, $tables);
    $checks['Schema tables'] = ['ok' => empty($missing), 'val' => empty($missing) ? implode(', ', $want) : 'MISSING: ' . implode(', ', $missing)];
} catch (Throwable $ex) {
    $checks['Schema tables'] = ['ok' => false, 'val' => $ex->getMessage()];
}
try {
    $checks['Users in DB'] = ['ok' => true, 'val' => db()->query('SELECT COUNT(*) FROM users')->fetchColumn() . ' user(s)'];
} catch (Throwable $ex) {
    $checks['Users in DB'] = ['ok' => false, 'val' => $ex->getMessage()];
}
$allOk = !in_array(false, array_column($checks, 'ok'), true);
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Health — Aleta Work Tracker</title>
<link rel="stylesheet" href="assets/style.css"></head>
<body><div class="content" style="max-width:760px;margin:30px auto">
  <div class="page-head"><h1>Health check</h1><div class="sub">System diagnostics</div></div>
  <div class="table-wrap"><table class="table"><tbody>
  <?php foreach ($checks as $name => $c): ?>
    <tr><td class="t-name"><?= e($name) ?></td>
        <td><span class="badge <?= $c['ok'] ? 'good' : 'crit' ?>"><?= $c['ok'] ? 'OK' : 'FAIL' ?></span></td>
        <td class="muted"><?= e($c['val']) ?></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <div class="note <?= $allOk ? 'good' : 'warn' ?>" style="margin-top:14px">
    <?= $allOk ? 'All systems green.' : 'Something failed — check MySQL / schema.' ?>
    &nbsp;·&nbsp; <a href="login.php">Go to app →</a>
  </div>
</div></body></html>
