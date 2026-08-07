<?php
// CLI: verify the Zoho Projects connection from within the app.
require __DIR__ . '/../src/zoho.php';

try {
    $z = new Zoho();
    $projects = $z->getProjects(1, 100);
    echo "Connection OK. Projects returned: " . count($projects) . "\n";
    $sample = array_slice($projects, 0, 5);
    foreach ($sample as $p) {
        $id   = $p['id'] ?? $p['id_string'] ?? '?';
        $name = $p['name'] ?? '?';
        $st   = is_array($p['status'] ?? null) ? ($p['status']['name'] ?? '') : ($p['status'] ?? '');
        echo "  - [$id] $name ($st)\n";
    }
    echo "\nAccess token cached in settings: " . (setting_get('zoho_access_token') ? 'yes' : 'no') . "\n";

    // Also pull tasks for the first project to prove deeper access.
    if (!empty($projects[0])) {
        $pid = (string)($projects[0]['id'] ?? '');
        $tasks = $z->getTasks($pid, 1, 5);
        echo "Tasks in first project ($pid): " . count($tasks) . "\n";
    }
} catch (Throwable $e) {
    echo "FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
