<?php
declare(strict_types=1);
require_once __DIR__ . '/zoho.php';

/** Zoho status can be a string or an object {name:...}. */
function zoho_status_name($s): string
{
    if (is_array($s)) return (string)($s['name'] ?? '');
    return (string)$s;
}

/** ISO UTC datetime -> local Y-m-d (converts to app timezone first). */
function iso_to_local_date($iso): ?string
{
    if (empty($iso)) return null;
    try {
        $d = new DateTime((string)$iso);
        $d->setTimezone(new DateTimeZone(cfg('app.timezone')));
        return $d->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

/** Map portal users to app accounts by email; returns count updated. */
function sync_users(Zoho $z): int
{
    try { $users = $z->getUsers(); } catch (Throwable $e) { return 0; }
    $n = 0;
    $upd = db()->prepare('UPDATE users SET zoho_zpuid = ? WHERE zoho_email = ?');
    foreach ($users as $u) {
        $email = strtolower((string)($u['email'] ?? ''));
        $zp    = (string)($u['zpuid'] ?? $u['id'] ?? '');
        if ($email === '' || $zp === '') continue;
        $upd->execute([$zp, $email]);
        $n += $upd->rowCount();
    }
    return $n;
}

function upsert_project(array $p): int
{
    $db = db();
    $zid = (string)($p['id'] ?? '');
    $name = (string)($p['name'] ?? '');
    $status = zoho_status_name($p['status'] ?? '');
    $st = $db->prepare('SELECT id FROM projects WHERE zoho_project_id = ?');
    $st->execute([$zid]);
    $id = $st->fetchColumn();
    if ($id) {
        $db->prepare('UPDATE projects SET name=?, status=?, last_synced_at=NOW() WHERE id=?')
           ->execute([$name, $status, $id]);
        return (int)$id;
    }
    $db->prepare('INSERT INTO projects (zoho_project_id, name, status, last_synced_at) VALUES (?,?,?,NOW())')
       ->execute([$zid, $name, $status]);
    return (int)$db->lastInsertId();
}

function upsert_tasklist(int $projLocalId, array $tl): void
{
    $db = db();
    $zid = (string)($tl['id'] ?? '');
    if ($zid === '') return;
    $name = (string)($tl['name'] ?? '');
    $status = zoho_status_name($tl['status'] ?? '');
    $st = $db->prepare('SELECT id FROM task_lists WHERE zoho_tasklist_id = ?');
    $st->execute([$zid]);
    $id = $st->fetchColumn();
    if ($id) {
        $db->prepare('UPDATE task_lists SET project_id=?, name=?, status=?, last_synced_at=NOW() WHERE id=?')
           ->execute([$projLocalId, $name, $status, $id]);
    } else {
        $db->prepare('INSERT INTO task_lists (zoho_tasklist_id, project_id, name, status, last_synced_at) VALUES (?,?,?,?,NOW())')
           ->execute([$zid, $projLocalId, $name, $status]);
    }
}

function upsert_task(int $projLocalId, array $tk): void
{
    $db = db();
    $zid = (string)($tk['id'] ?? '');
    if ($zid === '') return;
    $name = (string)($tk['name'] ?? '');
    $desc = trim(strip_tags((string)($tk['description'] ?? '')));
    $status = zoho_status_name($tk['status'] ?? '');
    $priority = strtolower((string)($tk['priority'] ?? 'none'));
    if (!in_array($priority, ['none','low','medium','high'], true)) $priority = 'none';
    $comp = (int)($tk['completion_percentage'] ?? 0);
    $start = iso_to_local_date($tk['start_date'] ?? null);
    $due   = iso_to_local_date($tk['end_date'] ?? null);

    // task list -> local id
    $tlLocal = null;
    if (!empty($tk['tasklist']['id'])) {
        $s = $db->prepare('SELECT id FROM task_lists WHERE zoho_tasklist_id = ?');
        $s->execute([(string)$tk['tasklist']['id']]);
        $tlLocal = $s->fetchColumn() ?: null;
    }
    // assignee (first owner)
    $own = $tk['owners_and_work']['owners'][0] ?? null;
    $azp = $own['zpuid'] ?? null;
    $aname = $own['name'] ?? null;
    $aemail = strtolower((string)($own['email'] ?? ''));
    $auid = null;
    if ($aemail !== '') {
        $s = $db->prepare('SELECT id FROM users WHERE zoho_email = ?');
        $s->execute([$aemail]);
        $auid = $s->fetchColumn() ?: null;
    }

    $ex = $db->prepare('SELECT id FROM tasks WHERE zoho_task_id = ?');
    $ex->execute([$zid]);
    $id = $ex->fetchColumn();
    if ($id) {
        $db->prepare('UPDATE tasks SET project_id=?, task_list_id=?, name=?, description=?, status=?, priority=?,
             completion=?, start_date=?, due_date=?, assignee_user_id=?, assignee_zpuid=?, assignee_name=?,
             last_synced_at=NOW(), sync_state=\'synced\' WHERE id=?')
           ->execute([$projLocalId,$tlLocal,$name,$desc,$status,$priority,$comp,$start,$due,$auid,$azp,$aname,$id]);
    } else {
        $db->prepare('INSERT INTO tasks (zoho_task_id, project_id, task_list_id, name, description, status, priority,
             completion, start_date, due_date, assignee_user_id, assignee_zpuid, assignee_name, last_synced_at, sync_state)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),\'synced\')')
           ->execute([$zid,$projLocalId,$tlLocal,$name,$desc,$status,$priority,$comp,$start,$due,$auid,$azp,$aname]);
    }
}

/** Pull projects, task lists, and tasks from Zoho into the local DB. */
function sync_in(?callable $log = null): array
{
    $log = $log ?? function ($m) {};
    $z = new Zoho();
    $c = ['users_mapped' => 0, 'projects' => 0, 'tasklists' => 0, 'tasks' => 0];

    $c['users_mapped'] = sync_users($z);
    $log("users mapped: {$c['users_mapped']}");

    $projects = $z->getProjects(1, 200);
    foreach ($projects as $p) {
        $projLocalId = upsert_project($p);
        $c['projects']++;
        $pid = (string)($p['id'] ?? '');

        foreach ($z->getTaskLists($pid, 1, 100) as $tl) {
            upsert_tasklist($projLocalId, $tl);
            $c['tasklists']++;
        }
        $tp = 1;
        while (true) {
            $tasks = $z->getTasks($pid, $tp, 100);
            if (!$tasks) break;
            foreach ($tasks as $tk) { upsert_task($projLocalId, $tk); $c['tasks']++; }
            if (count($tasks) < 100) break;
            $tp++;
        }
        $log("project {$p['name']}: done");
    }

    setting_set('last_sync_at', date('Y-m-d H:i'));
    return $c;
}
