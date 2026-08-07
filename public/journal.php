<?php
require_once __DIR__ . '/../src/perms.php';
require_once __DIR__ . '/../src/ui.php';
$user = require_login();

$jid = (int)($_GET['id'] ?? 0);
$q = db()->prepare('SELECT j.*, p.name AS project_name FROM journals j JOIN projects p ON j.project_id=p.id WHERE j.id = ?');
$q->execute([$jid]);
$j = $q->fetch();
if (!$j) { http_response_code(404); exit('Journal not found.'); }

$cl = db()->prepare('SELECT * FROM journal_checklist WHERE journal_id = ? ORDER BY phase DESC, sequence, id');
$cl->execute([$jid]);
$items = ['pre' => [], 'post' => []];
foreach ($cl->fetchAll() as $c) $items[$c['phase']][] = $c;

$self = 'journal.php?id=' . $jid;
$csrf = csrf_token();
$stages  = ['not_started'=>'Not started','pre_check'=>'Pre-check','submitted'=>'Submitted','post_audit'=>'Post-audit','done'=>'Done'];
$results = ['pending'=>'Pending','accepted'=>'Accepted','rejected'=>'Rejected','revise'=>'Revise'];

ui_head($j['name'] . ' — Journal');
ui_topbar($user, 'projects.php');

$renderChecklist = function(string $phase, array $rows) use ($csrf, $self, $user) {
    echo '<table class="table" style="margin-bottom:4px">';
    foreach ($rows as $c) {
        echo '<tr><td style="width:34px;text-align:center">';
        echo '<form method="post" action="journal_action.php" style="display:inline">';
        echo '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="toggle_check">';
        echo '<input type="hidden" name="item_id" value="' . (int)$c['id'] . '"><input type="hidden" name="back" value="' . e($self) . '">';
        echo '<button class="btn-mini" title="toggle">' . ($c['is_done'] ? '✓' : '○') . '</button></form></td>';
        echo '<td' . ($c['is_done'] ? ' style="color:#6b7684;text-decoration:line-through"' : '') . '>' . e($c['item']) . '</td>';
        echo '<td style="width:34px;text-align:right">';
        if ($user['role'] === 'admin') {
            echo '<form method="post" action="journal_action.php" style="display:inline" onsubmit="return confirm(\'Remove item?\')">';
            echo '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="delete_check_item">';
            echo '<input type="hidden" name="item_id" value="' . (int)$c['id'] . '"><input type="hidden" name="back" value="' . e($self) . '">';
            echo '<button class="btn-mini">×</button></form>';
        }
        echo '</td></tr>';
    }
    if (!$rows) echo '<tr><td colspan="3" class="muted" style="padding:10px">No items.</td></tr>';
    echo '</table>';
    // add item
    echo '<form method="post" action="journal_action.php" style="display:flex;gap:6px;margin:0 0 10px">';
    echo '<input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="action" value="add_check_item">';
    echo '<input type="hidden" name="journal_id" value="' . (int)$GLOBALS['jid'] . '"><input type="hidden" name="phase" value="' . $phase . '">';
    echo '<input type="hidden" name="back" value="' . e($self) . '">';
    echo '<input type="text" name="item" placeholder="add ' . $phase . ' item…" style="flex:1"><button class="btn-mini">Add</button></form>';
};
?>
  <div class="wrap" style="max-width:860px">
    <p style="margin:0 0 4px"><a href="journals.php?project_id=<?= (int)$j['project_id'] ?>" style="color:#6b7684;text-decoration:none">← Journals · <?= e($j['project_name']) ?></a></p>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
      <h1 style="margin:0"><?= e($j['name']) ?></h1>
      <div><?= journal_stage_pill($j['stage']) ?> <?= journal_result_pill($j['result']) ?></div>
    </div>

    <!-- edit details -->
    <div class="card" style="padding:18px;margin:12px 0">
      <form method="post" action="journal_action.php">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="update_journal">
        <input type="hidden" name="journal_id" value="<?= $jid ?>">
        <input type="hidden" name="back" value="<?= e($self) ?>">
        <div style="display:flex;gap:10px;flex-wrap:wrap">
          <div style="flex:2;min-width:220px"><label>Journal name</label><input type="text" name="name" required value="<?= e($j['name']) ?>"></div>
          <div style="flex:1;min-width:130px"><label>Indexing</label><input type="text" name="indexing" value="<?= e($j['indexing'] ?? '') ?>"></div>
          <div style="min-width:110px"><label>Impact factor</label><input type="text" name="impact_factor" value="<?= e($j['impact_factor'] ?? '') ?>"></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
          <div style="min-width:150px"><label>Deadline</label><input type="date" name="deadline" value="<?= e($j['deadline'] ?? '') ?>"></div>
          <div style="min-width:120px"><label>Fee (₹)</label><input type="number" step="0.01" name="fee" value="<?= e($j['fee'] ?? '') ?>"></div>
          <div style="flex:2;min-width:220px"><label>URL</label><input type="text" name="url" value="<?= e($j['url'] ?? '') ?>"></div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px">
          <div style="min-width:150px"><label>Stage</label><select name="stage">
            <?php foreach ($stages as $k=>$v): ?><option value="<?= $k ?>" <?= $j['stage']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div style="min-width:150px"><label>Result</label><select name="result">
            <?php foreach ($results as $k=>$v): ?><option value="<?= $k ?>" <?= $j['result']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></div>
          <div style="min-width:150px"><label>Decision date</label><input type="date" name="decision_date" value="<?= e($j['decision_date'] ?? '') ?>"></div>
        </div>
        <label>Notes</label><textarea name="notes" rows="2"><?= e($j['notes'] ?? '') ?></textarea>
        <div style="margin-top:14px;display:flex;justify-content:space-between;align-items:center">
          <button class="btn">Save</button>
          <?php if ($user['role'] === 'admin'): ?>
          <form method="post" action="journal_action.php" onsubmit="return confirm('Delete this journal and its checklist?')">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete_journal">
            <input type="hidden" name="journal_id" value="<?= $jid ?>">
            <button class="btn-mini" style="color:#b32020">🗑 Delete journal</button>
          </form>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <div style="flex:1;min-width:300px">
        <h3 style="color:#205072">Pre-submission checklist</h3>
        <?php $renderChecklist('pre', $items['pre']); ?>
      </div>
      <div style="flex:1;min-width:300px">
        <h3 style="color:#205072">Post-submission checklist</h3>
        <?php $renderChecklist('post', $items['post']); ?>
      </div>
    </div>
  </div>
<?php ui_foot();
