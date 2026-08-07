<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();

$pid = (int)($_GET['project_id'] ?? 0);
$proj = db()->prepare('SELECT * FROM projects WHERE id = ?');
$proj->execute([$pid]);
$project = $proj->fetch();
if (!$project) { http_response_code(404); exit('Project not found.'); }

$st = db()->prepare('SELECT j.*,
        (SELECT COUNT(*) FROM journal_checklist c WHERE c.journal_id=j.id) AS chk_total,
        (SELECT COUNT(*) FROM journal_checklist c WHERE c.journal_id=j.id AND c.is_done=1) AS chk_done
     FROM journals j WHERE j.project_id = ? ORDER BY j.deadline IS NULL, j.deadline, j.name');
$st->execute([$pid]);
$journals = $st->fetchAll();
$self = 'journals.php?project_id=' . $pid;
$csrf = csrf_token();

ui_head('Journals — ' . $project['name']);
ui_topbar($user, 'projects.php');
?>
  <div class="wrap" style="max-width:1000px">
    <p style="margin:0 0 4px"><a href="project.php?id=<?= $pid ?>" style="color:#6b7684;text-decoration:none">← <?= e($project['name']) ?></a></p>
    <h1 style="margin:0 0 2px">Journals</h1>
    <p class="muted"><?= count($journals) ?> journal(s) · each is one card with its own stage &amp; checklist</p>

    <details class="card" style="padding:14px 16px;margin:10px 0">
      <summary style="cursor:pointer;font-weight:600;color:#0b3d66">＋ Add a journal</summary>
      <form method="post" action="journal_action.php" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="add_journal">
        <input type="hidden" name="project_id" value="<?= $pid ?>">
        <input type="hidden" name="back" value="<?= e($self) ?>">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="flex:2;min-width:220px"><label>Journal name</label><input type="text" name="name" required placeholder="e.g. IEEE Access"></div>
          <div style="flex:1;min-width:130px"><label>Indexing</label><input type="text" name="indexing" placeholder="Scopus / SCI"></div>
          <div style="min-width:110px"><label>Impact factor</label><input type="text" name="impact_factor" placeholder="3.9"></div>
          <div style="min-width:140px"><label>Deadline</label><input type="date" name="deadline"></div>
          <div style="min-width:110px"><label>Fee (₹)</label><input type="number" step="0.01" name="fee"></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px">
          <div style="flex:2;min-width:220px"><label>URL</label><input type="text" name="url" placeholder="https://…"></div>
          <div style="flex:2;min-width:220px"><label>Notes</label><input type="text" name="notes"></div>
        </div>
        <div style="margin-top:12px"><button class="btn">Add journal</button> <span class="muted" style="font-size:12px">(pre/post checklist is added automatically)</span></div>
      </form>
    </details>

    <table class="table">
      <tr><th>Journal</th><th>Indexing</th><th>IF</th><th>Deadline</th><th>Fee</th><th>Stage</th><th>Result</th><th>Checklist</th></tr>
      <?php foreach ($journals as $j): ?>
      <tr>
        <td><a href="journal.php?id=<?= (int)$j['id'] ?>" style="color:#0b3d66;font-weight:600;text-decoration:none"><?= e($j['name']) ?></a></td>
        <td class="muted"><?= e($j['indexing'] ?? '—') ?></td>
        <td class="muted"><?= e($j['impact_factor'] ?? '—') ?></td>
        <td style="white-space:nowrap;color:#6b7684"><?= $j['deadline'] ? e(date('d M Y', strtotime($j['deadline']))) : '—' ?></td>
        <td style="white-space:nowrap;color:#6b7684"><?= $j['fee'] !== null ? '₹' . e(number_format((float)$j['fee'])) : '—' ?></td>
        <td><?= journal_stage_pill($j['stage']) ?></td>
        <td><?= journal_result_pill($j['result']) ?></td>
        <td class="muted" style="white-space:nowrap"><?= (int)$j['chk_done'] ?>/<?= (int)$j['chk_total'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$journals): ?>
      <tr><td colspan="8" class="muted" style="text-align:center;padding:20px">No journals yet — add one above. This replaces the old 20×3 identical-task mess with clean named cards.</td></tr>
      <?php endif; ?>
    </table>
  </div>
<?php ui_foot();
