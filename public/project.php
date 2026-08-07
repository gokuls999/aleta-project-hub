<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();

$pid = (int)($_GET['id'] ?? 0);
$showAll = ($_GET['show'] ?? '') === 'all';
$proj = db()->prepare('SELECT * FROM projects WHERE id = ?');
$proj->execute([$pid]);
$project = $proj->fetch();
if (!$project) { http_response_code(404); exit('Project not found.'); }

$self = 'project.php?id=' . $pid . ($showAll ? '&show=all' : '');

// tasks (open-only by default)
$where = 't.project_id = ?';
if (!$showAll) $where .= " AND t.completion < 100 AND t.status NOT LIKE '%Closed%' AND t.status NOT LIKE '%Cancel%'";
$st = db()->prepare(
    "SELECT t.*, COALESCE(tl.name,'— No task list —') AS tl_name
     FROM tasks t LEFT JOIN task_lists tl ON t.task_list_id = tl.id
     WHERE $where
     ORDER BY tl.sequence IS NULL, tl.sequence, tl.name, t.due_date IS NULL, t.due_date, t.name"
);
$st->execute([$pid]);
$tasks = $st->fetchAll();
$groups = [];
foreach ($tasks as $t) $groups[$t['tl_name']][] = $t;

// task lists for the add-task dropdown
$tl = db()->prepare('SELECT id, name FROM task_lists WHERE project_id = ? ORDER BY sequence, name');
$tl->execute([$pid]);
$taskLists = $tl->fetchAll();

$csrf = csrf_token();
ui_head($project['name'] . ' — Aleta Work Tracker');
ui_topbar($user, 'projects.php');
?>
  <div class="wrap" style="max-width:1000px">
    <p style="margin:0 0 4px"><a href="projects.php" style="color:#6b7684;text-decoration:none">← Projects</a></p>
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <h1 style="margin:0"><?= e($project['name']) ?></h1>
      <div style="display:flex;align-items:center;gap:12px">
        <?= ui_pill($project['status'] ?? '') ?>
        <a href="journals.php?project_id=<?= $pid ?>" class="btn-mini" style="padding:6px 12px">📑 Journals</a>
      </div>
    </div>
    <p class="muted">
      <?= count($tasks) ?> <?= $showAll ? 'total' : 'open' ?> task(s) ·
      <a href="project.php?id=<?= $pid ?><?= $showAll ? '' : '&show=all' ?>" style="color:#205072">
        <?= $showAll ? 'show open only' : 'show all (incl. closed)' ?>
      </a>
    </p>

    <!-- Add task (all staff may add) -->
    <details class="card" style="padding:14px 16px;margin:10px 0">
      <summary style="cursor:pointer;font-weight:600;color:#0b3d66">＋ Add a task</summary>
      <form method="post" action="task_action.php" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-top:12px">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_task">
        <input type="hidden" name="project_id" value="<?= $pid ?>">
        <input type="hidden" name="back" value="<?= e($self) ?>">
        <div style="flex:2;min-width:220px"><label>Task name</label><input type="text" name="name" required></div>
        <div style="flex:1;min-width:150px"><label>Task list</label>
          <select name="task_list_id"><option value="">— none —</option>
            <?php foreach ($taskLists as $l): ?><option value="<?= $l['id'] ?>"><?= e($l['name']) ?></option><?php endforeach; ?>
          </select></div>
        <div style="min-width:110px"><label>Priority</label>
          <select name="priority"><option value="none">None</option><option>low</option><option>medium</option><option>high</option></select></div>
        <div style="min-width:140px"><label>Due date</label><input type="date" name="due_date"></div>
        <div><button class="btn">Add</button></div>
      </form>
    </details>

    <?php foreach ($groups as $listName => $rows): ?>
      <h3 style="color:#205072;margin:20px 0 6px"><?= e($listName) ?> <span class="muted" style="font-weight:400">(<?= count($rows) ?>)</span></h3>
      <table class="table">
        <tr><th>Task</th><th>Assignee</th><th>Status</th><th>Priority</th><th>Due</th><th style="text-align:right">%</th><th>Actions</th></tr>
        <?php foreach ($rows as $t): $done = is_task_done($t); ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= e($t['name']) ?></div>
            <?php if ($t['description']): ?><div class="muted" style="font-size:12px"><?= e(mb_strimwidth($t['description'],0,90,'…')) ?></div><?php endif; ?>
          </td>
          <td><?= e($t['assignee_name'] ?? '—') ?></td>
          <td><?= ui_pill($t['status']) ?></td>
          <td><?= ui_pill($t['priority'], 'priority') ?></td>
          <td style="white-space:nowrap;color:#6b7684"><?= $t['due_date'] ? e(date('d M Y', strtotime($t['due_date']))) : '—' ?></td>
          <td style="text-align:right"><?= (int)$t['completion'] ?></td>
          <td style="white-space:nowrap">
            <?php if (!$done): ?>
              <?= action_btn('complete_task', $t['id'], '✓ Done', $csrf, $self) ?>
            <?php elseif ($user['role'] === 'admin'): ?>
              <?= action_btn('reopen_task', $t['id'], '↺ Reopen', $csrf, $self) ?>
            <?php endif; ?>
            <?php if ($user['role'] === 'admin'): ?>
              <?= action_btn('delete_task', $t['id'], '🗑', $csrf, $self, 'Delete this task?') ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    <?php endforeach; ?>

    <?php if (!$tasks): ?><p class="muted">No <?= $showAll ? '' : 'open ' ?>tasks here.</p><?php endif; ?>
  </div>
<?php ui_foot();

/** Render a one-click action form-button. */
function action_btn(string $action, $taskId, string $label, string $csrf, string $back, string $confirm = ''): string {
    $c = $confirm ? ' onsubmit="return confirm(\'' . e($confirm) . '\')"' : '';
    return '<form method="post" action="task_action.php" style="display:inline"' . $c . '>'
        . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
        . '<input type="hidden" name="action" value="' . e($action) . '">'
        . '<input type="hidden" name="task_id" value="' . (int)$taskId . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '">'
        . '<button class="btn-mini">' . e($label) . '</button></form>';
}
