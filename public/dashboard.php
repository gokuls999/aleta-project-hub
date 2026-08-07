<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();
$db = db();

$zp = $user['zoho_zpuid'] ?? '__none__';
$q = $db->prepare("SELECT COUNT(*) FROM tasks WHERE (assignee_user_id=? OR assignee_zpuid=?) AND completion<100 AND status NOT LIKE '%Closed%'");
$q->execute([$user['id'], $zp]);
$myOpen = (int)$q->fetchColumn();

$projects   = (int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn();
$openTasks  = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE completion<100 AND status NOT LIKE '%Closed%' AND status NOT LIKE '%Cancel%'")->fetchColumn();
$journals   = (int)$db->query('SELECT COUNT(*) FROM journals')->fetchColumn();
$lastSync   = setting_get('last_sync_at', 'never');

// projects by status (categorical bar)
$byStatus = $db->query("SELECT COALESCE(NULLIF(status,''),'—') AS s, COUNT(*) c FROM projects GROUP BY s ORDER BY c DESC")->fetchAll();
$maxc = max(1, ...array_map(fn($r)=>(int)$r['c'], $byStatus ?: [['c'=>1]]));
$catColors = ['#2a78d6','#eb6834','#1baf7a','#eda100','#e87ba4','#4a3aa7','#e34948','#8b909a'];

// my open tasks (top 10)
$mt = $db->prepare(
    "SELECT t.*, p.name project, p.id pid FROM tasks t JOIN projects p ON t.project_id=p.id
     WHERE (t.assignee_user_id=? OR t.assignee_zpuid=?) AND t.completion<100 AND t.status NOT LIKE '%Closed%'
     ORDER BY t.due_date IS NULL, t.due_date, t.name LIMIT 10"
);
$mt->execute([$user['id'],$zp]);
$myTasks = $mt->fetchAll();

$parts = preg_split('/\s+/', trim($user['full_name']));
$first = $parts[0];
if (preg_match('/^(dr|mr|ms|mrs|prof)\.?$/i', $first) && isset($parts[1])) $first = $parts[1];

ui_head('Dashboard — Aleta Work Tracker');
ui_topbar($user, 'dashboard.php');
ui_page_head('Welcome back, ' . e($first),
    'Last synced with Zoho Projects: <b>' . e($lastSync) . '</b>');
?>
    <div class="stats">
      <div class="stat"><div class="stat-ic"><?= ui_icon('check') ?></div><div class="label">My open tasks</div><div class="value"><?= $myOpen ?></div><div class="foot">assigned to you</div></div>
      <div class="stat"><div class="stat-ic"><?= ui_icon('folder') ?></div><div class="label">Projects</div><div class="value"><?= $projects ?></div><div class="foot"><a href="projects.php">view all →</a></div></div>
      <div class="stat"><div class="stat-ic"><?= ui_icon('grid') ?></div><div class="label">Open tasks</div><div class="value"><?= number_format($openTasks) ?></div><div class="foot">across all projects</div></div>
      <div class="stat"><div class="stat-ic"><?= ui_icon('book') ?></div><div class="label">Journals</div><div class="value"><?= $journals ?></div><div class="foot">tracked submissions</div></div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="section-title">Projects by status</div>
        <div class="bars">
          <?php foreach ($byStatus as $i => $r): ?>
          <div class="bar-row">
            <div class="bl"><?= e($r['s']) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= max(4, round(100*$r['c']/$maxc)) ?>%;background:<?= $catColors[$i % count($catColors)] ?>"></div></div>
            <div class="bn"><?= (int)$r['c'] ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (!$byStatus): ?><div class="muted">No projects yet.</div><?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="section-title">My open tasks</div>
        <?php if ($myTasks): ?>
        <div class="table-wrap" style="box-shadow:none;border:0">
          <table class="table">
            <tbody>
            <?php foreach ($myTasks as $t): ?>
            <tr>
              <td><div class="t-name"><?= e(mb_strimwidth($t['name'],0,46,'…')) ?></div>
                  <div class="t-sub"><a href="project.php?id=<?= (int)$t['pid'] ?>"><?= e(mb_strimwidth($t['project'],0,34,'…')) ?></a></div></td>
              <td><?= ui_pill($t['priority'],'priority') ?></td>
              <td class="num muted" style="white-space:nowrap"><?= $t['due_date'] ? e(date('d M', strtotime($t['due_date']))) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <p class="muted">No open tasks assigned to you. 🎉</p>
        <?php endif; ?>
        <div style="margin-top:12px"><a href="tasks.php" class="btn-mini">View all my tasks</a></div>
      </div>
    </div>
<?php ui_foot();
