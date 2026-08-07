<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();

// "My Tasks": match by app-user link OR by the user's Zoho id (zpuid).
$st = db()->prepare(
    "SELECT t.*, p.name AS project_name, p.id AS project_local_id
     FROM tasks t JOIN projects p ON t.project_id = p.id
     WHERE (t.assignee_user_id = ? OR (t.assignee_zpuid IS NOT NULL AND t.assignee_zpuid = ?))
     ORDER BY (t.status LIKE '%Closed%' OR t.completion >= 100), t.due_date IS NULL, t.due_date, t.name"
);
$st->execute([$user['id'], $user['zoho_zpuid'] ?? '__none__']);
$tasks = $st->fetchAll();

$open = array_filter($tasks, fn($t) => stripos($t['status'], 'closed') === false && (int)$t['completion'] < 100);

ui_head('My Tasks — Aleta Work Tracker');
ui_topbar($user, 'tasks.php');
?>
  <div class="wrap" style="max-width:980px">
    <h1>My Tasks</h1>
    <p class="muted"><?= count($open) ?> open · <?= count($tasks) ?> total assigned to you</p>

    <?php if (!$user['zoho_zpuid'] && !array_filter($tasks, fn($t)=>$t['assignee_user_id']==$user['id'])): ?>
      <div class="note warn">Your account isn't linked to a Zoho Projects user yet, so tasks may not show. A sync links staff automatically once their Zoho email matches.</div>
    <?php endif; ?>

    <table class="table">
      <tr><th>Task</th><th>Project</th><th>Status</th><th>Priority</th><th>Due</th><th style="text-align:right">%</th></tr>
      <?php foreach ($tasks as $t): ?>
      <tr>
        <td style="font-weight:600"><?= e($t['name']) ?></td>
        <td><a href="project.php?id=<?= (int)$t['project_local_id'] ?>" style="color:#0b3d66;text-decoration:none"><?= e($t['project_name']) ?></a></td>
        <td><?= ui_pill($t['status']) ?></td>
        <td><?= ui_pill($t['priority'], 'priority') ?></td>
        <td style="white-space:nowrap;color:#6b7684"><?= $t['due_date'] ? e(date('d M Y', strtotime($t['due_date']))) : '—' ?></td>
        <td style="text-align:right"><?= (int)$t['completion'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$tasks): ?>
      <tr><td colspan="6" class="muted" style="text-align:center;padding:20px">No tasks assigned to you (or none synced yet).</td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php ui_foot();
