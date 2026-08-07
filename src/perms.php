<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Central permission rules.
 * Admin can do everything. Staff can add tasks and mark them complete,
 * but CANNOT delete or reopen — those need an admin.
 */
function can(string $action, array $user): bool
{
    if (($user['role'] ?? '') === 'admin') return true;
    switch ($action) {
        case 'add_task':
        case 'complete_task':
        case 'edit_task':
        case 'log_time':
        case 'add_journal':
        case 'update_journal':
            return true;   // staff allowed
        case 'delete_task':
        case 'reopen_task':
        case 'delete_journal':
        case 'manage_users':
        case 'manage_templates':
        case 'create_project':
            return false;  // admin only
        default:
            return false;
    }
}

/** Enforce a permission or stop with 403. */
function require_can(string $action, array $user): void
{
    if (!can($action, $user)) {
        http_response_code(403);
        exit('Not allowed — this action needs an admin.');
    }
}

function is_task_done(array $task): bool
{
    return (int)$task['completion'] >= 100 || stripos($task['status'], 'closed') !== false;
}
