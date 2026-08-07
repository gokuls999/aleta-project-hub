<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();

$q = trim($_GET['q'] ?? '');
$sql = "SELECT p.id, p.name, p.status, p.is_research,
          (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS total,
          (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status NOT LIKE '%Closed%' AND t.completion < 100) AS open
        FROM projects p";
$params = [];
if ($q !== '') { $sql .= " WHERE p.name LIKE ?"; $params[] = "%$q%"; }
$sql .= " ORDER BY open DESC, p.name ASC";
$st = db()->prepare($sql);
$st->execute($params);
$projects = $st->fetchAll();
$lastSync = setting_get('last_sync_at', 'never');

ui_head('Projects — Aleta Work Tracker');
ui_topbar($user, 'projects.php');
ui_page_head('Projects', number_format(count($projects)) . ' project' . (count($projects) === 1 ? '' : 's') . ' · last synced ' . e($lastSync));
?>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap">
      <form method="get" style="margin:0;flex:1;max-width:340px">
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="Search projects…">
      </form>
      <?php if ($user['role'] === 'admin'): ?><a href="new_project.php" class="btn">＋ New Project</a><?php endif; ?>
    </div>

    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Project</th><th>Status</th><th class="num">Open</th><th class="num">Total</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
        <tr>
          <td>
            <a href="project.php?id=<?= (int)$p['id'] ?>" class="t-name"><?= e($p['name']) ?></a>
            <?php if ($p['is_research']): ?> <span class="badge accent" style="font-size:10px">PhD</span><?php endif; ?>
          </td>
          <td><?= ui_pill($p['status'] ?? '') ?></td>
          <td class="num"><?= (int)$p['open'] ?></td>
          <td class="num muted"><?= (int)$p['total'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$projects): ?>
        <tr><td colspan="4" class="muted" style="text-align:center;padding:22px">No projects found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
<?php ui_foot();
