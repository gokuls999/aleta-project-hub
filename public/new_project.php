<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_admin();   // creating projects is admin-only

$templates = db()->query('SELECT id, name, description FROM project_templates WHERE is_active = 1 ORDER BY name')->fetchAll();

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $err = 'Session expired — try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $tplId = (int)($_POST['template_id'] ?? 0);
        $isResearch = isset($_POST['is_research']) ? 1 : 0;
        if ($name === '') {
            $err = 'Project name is required.';
        } else {
            $db = db();
            $db->prepare('INSERT INTO projects (zoho_project_id, name, status, is_research, created_by, last_synced_at)
                          VALUES (NULL, ?, ?, ?, ?, NULL)')
               ->execute([$name, 'Active', $isResearch, $user['id']]);
            $pid = (int)$db->lastInsertId();
            if ($tplId) {
                $lists = $db->prepare('SELECT name, sequence FROM template_task_lists WHERE template_id = ? ORDER BY sequence');
                $lists->execute([$tplId]);
                $ins = $db->prepare('INSERT INTO task_lists (zoho_tasklist_id, project_id, name, sequence) VALUES (NULL, ?, ?, ?)');
                foreach ($lists->fetchAll() as $l) $ins->execute([$pid, $l['name'], $l['sequence']]);
            }
            header('Location: project.php?id=' . $pid); exit;
        }
    }
}
ui_head('New Project — Aleta Work Tracker');
ui_topbar($user, 'projects.php');
?>
  <div class="wrap" style="max-width:560px">
    <p style="margin:0 0 4px"><a href="projects.php" style="color:#6b7684;text-decoration:none">← Projects</a></p>
    <h1>New Project</h1>
    <?php if ($err): ?><div class="note warn"><?= e($err) ?></div><?php endif; ?>
    <div class="card" style="padding:20px">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Project name</label>
        <input type="text" name="name" required autofocus placeholder="e.g. Deep Learning for X — Student Name">
        <label>Start from template</label>
        <select name="template_id">
          <option value="0">— Blank (no task lists) —</option>
          <?php foreach ($templates as $t): ?>
            <option value="<?= $t['id'] ?>" selected><?= e($t['name']) ?> — creates its task lists</option>
          <?php endforeach; ?>
        </select>
        <label style="margin-top:14px"><input type="checkbox" name="is_research" checked style="width:auto"> This is a PhD / research project (enables Journals)</label>
        <div style="margin-top:18px"><button class="btn">Create project</button></div>
      </form>
    </div>
  </div>
<?php ui_foot();
