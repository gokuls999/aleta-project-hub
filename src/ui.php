<?php
// Shared UI chrome: sidebar shell, page header, badges.

function ui_head(string $title): void { ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <?= theme_font_links() ?>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body <?= theme_body_attrs() ?>>
<?php }

function ui_icon(string $name): string {
    $p = [
        'grid'  => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'folder'=> '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'check' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'book'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'paint' => '<circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2a10 10 0 0 0 0 20 2.5 2.5 0 0 0 2-4 2.5 2.5 0 0 1 2-4h2a4 4 0 0 0 4-4 10 10 0 0 0-12-8z"/>',
    ];
    $body = $p[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
}

/** Renders the sidebar and opens the main content area. Call ui_foot() to close. */
function ui_topbar(array $user, string $active): void {
    $initials = strtoupper(mb_substr($user['full_name'], 0, 1));
    $nav = [
        'dashboard.php' => ['grid', 'Dashboard'],
        'projects.php'  => ['folder', 'Projects'],
        'tasks.php'     => ['check', 'My Tasks'],
        'timesheet.php' => ['clock', 'Time'],
    ]; ?>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand"><span class="logo">A</span> Work Tracker</div>
      <nav>
        <div class="navlabel">Menu</div>
        <?php foreach ($nav as $href => [$icon, $label]): ?>
          <a href="<?= $href ?>" class="<?= $active === $href ? 'active' : '' ?>"><span class="nav-ic"><?= ui_icon($icon) ?></span><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
        <?php if ($user['role'] === 'admin'): ?>
          <div class="navlabel">Admin</div>
          <a href="admin_users.php" class="<?= $active === 'admin_users.php' ? 'active' : '' ?>"><span class="nav-ic"><?= ui_icon('users') ?></span><span>Staff</span></a>
          <a href="appearance.php" class="<?= $active === 'appearance.php' ? 'active' : '' ?>"><span class="nav-ic"><?= ui_icon('paint') ?></span><span>Appearance</span></a>
        <?php endif; ?>
      </nav>
      <div class="userblock">
        <div class="avatar"><?= e($initials) ?></div>
        <div class="who"><b><?= e($user['full_name']) ?></b><span><?= e(ucfirst($user['role'])) ?></span></div>
        <a href="logout.php" title="Sign out">⎋</a>
      </div>
    </aside>
    <main class="content">
<?php }

/** Optional page header. */
function ui_page_head(string $title, string $sub = ''): void {
    echo '<div class="page-head"><h1>' . e($title) . '</h1>';
    if ($sub !== '') echo '<div class="sub">' . $sub . '</div>';
    echo '</div>';
}

/** Map a free-text status/priority to a badge. */
function ui_pill(string $text, string $kind = 'status'): string {
    if ($text === '') return '';
    $t = strtolower($text); $cls = 'neutral';
    if ($kind === 'priority') {
        $cls = ['high'=>'crit','medium'=>'serious','low'=>'good','none'=>'neutral'][$t] ?? 'neutral';
    } else {
        if (strpos($t,'closed')!==false || strpos($t,'complete')!==false || strpos($t,'done')!==false) $cls = 'good';
        elseif (strpos($t,'progress')!==false || strpos($t,'track')!==false) $cls = 'accent';
        elseif (strpos($t,'open')!==false) $cls = 'warn';
        elseif (strpos($t,'delay')!==false) $cls = 'serious';
        elseif (strpos($t,'cancel')!==false) $cls = 'neutral';
    }
    return '<span class="badge ' . $cls . '">' . e($text) . '</span>';
}
function journal_stage_pill(string $s): string {
    $m = ['not_started'=>['Not started','neutral'],'pre_check'=>['Pre-check','warn'],'submitted'=>['Submitted','accent'],'post_audit'=>['Post-audit','serious'],'done'=>['Done','good']];
    $x = $m[$s] ?? [$s,'neutral'];
    return '<span class="badge ' . $x[1] . '">' . e($x[0]) . '</span>';
}
function journal_result_pill(string $r): string {
    $m = ['pending'=>['Pending','neutral'],'accepted'=>['Accepted','good'],'rejected'=>['Rejected','crit'],'revise'=>['Revise','warn']];
    $x = $m[$r] ?? [$r,'neutral'];
    return '<span class="badge ' . $x[1] . '">' . e($x[0]) . '</span>';
}
/** Compatibility shims for older inline pill helpers. */
function pill_html(string $t, string $bg, string $fg): string { return '<span class="badge neutral">' . e($t) . '</span>'; }

function ui_foot(): void { echo "\n    </main>\n  </div>\n</body></html>"; }
