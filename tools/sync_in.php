<?php
// CLI: pull projects/task lists/tasks from Zoho into the local DB.
require __DIR__ . '/../src/sync_in.php';

echo "Syncing from Zoho Projects...\n";
$t0 = microtime(true);
try {
    $c = sync_in(function ($m) { /* echo "  $m\n"; */ });
    printf("Done in %.1fs — users mapped: %d, projects: %d, task lists: %d, tasks: %d\n",
        microtime(true) - $t0, $c['users_mapped'], $c['projects'], $c['tasklists'], $c['tasks']);
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
