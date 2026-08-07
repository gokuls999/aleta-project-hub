<?php
// Handles task create / complete / edit / reopen / delete with role rules.
require_once __DIR__ . '/../src/perms.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check($_POST['csrf'] ?? null)) {
    http_response_code(400); exit('Bad request.');
}
$action = $_POST['action'] ?? '';
$back   = $_POST['back'] ?? 'projects.php';
$db = db();
$dirty = "sync_state = IF(sync_state='new','new','dirty')";

function go(string $u) { header("Location: $u"); exit; }
function clean_priority($p, $fallback = 'none') {
    return in_array($p, ['none','low','medium','high'], true) ? $p : $fallback;
}

// ---- ADD (any logged-in user) ----------------------------------------------
if ($action === 'add_task') {
    require_can('add_task', $user);
    $pid  = (int)($_POST['project_id'] ?? 0);
    $tl   = ($_POST['task_list_id'] ?? '') !== '' ? (int)$_POST['task_list_id'] : null;
    $name = trim($_POST['name'] ?? '');
    $prio = clean_priority($_POST['priority'] ?? 'none');
    $due  = ($_POST['due_date'] ?? '') !== '' ? $_POST['due_date'] : null;
    if ($name === '' || !$pid) go($back);
    $st = $db->prepare(
        "INSERT INTO tasks (project_id, task_list_id, name, status, priority, due_date,
            assignee_user_id, assignee_zpuid, assignee_name, completion, sync_state, local_modified_at)
         VALUES (?,?,?,'Open',?,?,?,?,?,0,'new',NOW())"
    );
    $st->execute([$pid, $tl, $name, $prio, $due, $user['id'], $user['zoho_zpuid'], $user['full_name']]);
    go($back);
}

// ---- everything else needs an existing task --------------------------------
$tid = (int)($_POST['task_id'] ?? 0);
$q = $db->prepare('SELECT * FROM tasks WHERE id = ?');
$q->execute([$tid]);
$task = $q->fetch();
if (!$task) go($back);

if ($action === 'complete_task') {
    require_can('complete_task', $user);
    $db->prepare("UPDATE tasks SET completion=100, status='Closed', local_modified_at=NOW(), $dirty WHERE id=?")
       ->execute([$tid]);
    go($back);
}

if ($action === 'reopen_task') {
    require_can('reopen_task', $user);   // admin only
    $db->prepare("UPDATE tasks SET completion=0, status='Open', local_modified_at=NOW(), $dirty WHERE id=?")
       ->execute([$tid]);
    go($back);
}

if ($action === 'delete_task') {
    require_can('delete_task', $user);   // admin only
    $db->prepare('DELETE FROM tasks WHERE id=?')->execute([$tid]);
    go($back);
}

if ($action === 'edit_task') {
    require_can('edit_task', $user);
    $name = trim($_POST['name'] ?? $task['name']);
    $prio = clean_priority($_POST['priority'] ?? '', $task['priority']);
    $due  = ($_POST['due_date'] ?? '') !== '' ? $_POST['due_date'] : null;
    $comp = max(0, min(100, (int)($_POST['completion'] ?? $task['completion'])));
    // A staff member must not reopen a done task via the edit form.
    if (is_task_done($task) && $comp < 100 && !can('reopen_task', $user)) {
        http_response_code(403); exit('Only an admin can reopen a task.');
    }
    $status = $comp >= 100 ? 'Closed' : (is_task_done($task) ? 'Open' : $task['status']);
    $db->prepare("UPDATE tasks SET name=?, priority=?, due_date=?, completion=?, status=?, local_modified_at=NOW(), $dirty WHERE id=?")
       ->execute([$name, $prio, $due, $comp, $status, $tid]);
    go($back);
}

go($back);
