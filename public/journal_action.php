<?php
// Journal create / update / delete + checklist toggle/add. Role rules via perms.php.
require_once __DIR__ . '/../src/perms.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(400); exit('Bad request.');
}
$action = $_POST['action'] ?? '';
$back   = $_POST['back'] ?? 'projects.php';
$db = db();
function go(string $u) { header("Location: $u"); exit; }

/** Read all journal fields from POST into an array. */
function journal_fields(): array {
    $stage  = in_array($_POST['stage'] ?? '', ['not_started','pre_check','submitted','post_audit','done'], true) ? $_POST['stage'] : 'not_started';
    $result = in_array($_POST['result'] ?? '', ['pending','accepted','rejected','revise'], true) ? $_POST['result'] : 'pending';
    return [
        'name'          => trim($_POST['name'] ?? ''),
        'indexing'      => trim($_POST['indexing'] ?? '') ?: null,
        'impact_factor' => trim($_POST['impact_factor'] ?? '') ?: null,
        'deadline'      => ($_POST['deadline'] ?? '') !== '' ? $_POST['deadline'] : null,
        'fee'           => ($_POST['fee'] ?? '') !== '' ? (float)$_POST['fee'] : null,
        'url'           => trim($_POST['url'] ?? '') ?: null,
        'notes'         => trim($_POST['notes'] ?? '') ?: null,
        'stage'         => $stage,
        'result'        => $result,
        'decision_date' => ($_POST['decision_date'] ?? '') !== '' ? $_POST['decision_date'] : null,
    ];
}

if ($action === 'add_journal') {
    require_can('add_journal', $user);
    $pid = (int)($_POST['project_id'] ?? 0);
    $f = journal_fields();
    if (!$pid || $f['name'] === '') go($back);
    $db->prepare('INSERT INTO journals (project_id,name,indexing,impact_factor,deadline,fee,url,notes,stage,result,decision_date,created_by)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$pid,$f['name'],$f['indexing'],$f['impact_factor'],$f['deadline'],$f['fee'],$f['url'],$f['notes'],$f['stage'],$f['result'],$f['decision_date'],$user['id']]);
    $jid = (int)$db->lastInsertId();
    // copy default checklist items
    $defs = $db->query('SELECT phase,item,sequence FROM journal_checklist_defaults ORDER BY sequence')->fetchAll();
    $ins = $db->prepare('INSERT INTO journal_checklist (journal_id,phase,item,sequence) VALUES (?,?,?,?)');
    foreach ($defs as $d) $ins->execute([$jid,$d['phase'],$d['item'],$d['sequence']]);
    go('journal.php?id=' . $jid);
}

if ($action === 'update_journal') {
    require_can('update_journal', $user);
    $jid = (int)($_POST['journal_id'] ?? 0);
    $f = journal_fields();
    if (!$jid || $f['name'] === '') go($back);
    $db->prepare('UPDATE journals SET name=?,indexing=?,impact_factor=?,deadline=?,fee=?,url=?,notes=?,stage=?,result=?,decision_date=? WHERE id=?')
       ->execute([$f['name'],$f['indexing'],$f['impact_factor'],$f['deadline'],$f['fee'],$f['url'],$f['notes'],$f['stage'],$f['result'],$f['decision_date'],$jid]);
    go($back);
}

if ($action === 'delete_journal') {
    require_can('delete_journal', $user);   // admin only
    $jid = (int)($_POST['journal_id'] ?? 0);
    $pid = (int)$db->query('SELECT project_id FROM journals WHERE id=' . $jid)->fetchColumn();
    $db->prepare('DELETE FROM journals WHERE id=?')->execute([$jid]);
    go('journals.php?project_id=' . $pid);
}

if ($action === 'toggle_check') {
    require_can('update_journal', $user);
    $cid = (int)($_POST['item_id'] ?? 0);
    $db->prepare('UPDATE journal_checklist SET is_done = 1 - is_done WHERE id = ?')->execute([$cid]);
    go($back);
}

if ($action === 'add_check_item') {
    require_can('update_journal', $user);
    $jid = (int)($_POST['journal_id'] ?? 0);
    $phase = ($_POST['phase'] ?? 'pre') === 'post' ? 'post' : 'pre';
    $item = trim($_POST['item'] ?? '');
    if ($jid && $item !== '') {
        $seq = (int)$db->query('SELECT COALESCE(MAX(sequence),0)+1 FROM journal_checklist WHERE journal_id=' . $jid)->fetchColumn();
        $db->prepare('INSERT INTO journal_checklist (journal_id,phase,item,sequence) VALUES (?,?,?,?)')
           ->execute([$jid,$phase,$item,$seq]);
    }
    go($back);
}

if ($action === 'delete_check_item') {
    require_can('delete_task', $user);   // admin only (reuse admin gate)
    $cid = (int)($_POST['item_id'] ?? 0);
    $db->prepare('DELETE FROM journal_checklist WHERE id=?')->execute([$cid]);
    go($back);
}

go($back);
