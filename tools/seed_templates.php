<?php
// Seed the PhD Research Project template (9 task lists) + default journal checklist items.
// Idempotent: skips if the template already exists.
require __DIR__ . '/../src/bootstrap.php';
$db = db();

$templateName = 'PhD Research Project';
$lists = [
    'Client Interaction & Requirement Understanding',
    'Pre-Research & Existing Work Study',
    'Concept Development & Architecture',
    'Diagram Digitization & Design',
    'Literature Review & Presentation Preparation',
    'Technical Documentation Development',
    'Budgeting & Client Review',
    'Publication Preparation',
    'Journal Submission',
];

$exists = $db->prepare('SELECT id FROM project_templates WHERE name = ?');
$exists->execute([$templateName]);
$tid = $exists->fetchColumn();
if ($tid) {
    echo "Template '$templateName' already exists (id $tid) — skipping.\n";
} else {
    $db->prepare('INSERT INTO project_templates (name, description) VALUES (?, ?)')
       ->execute([$templateName, 'Standard 9-stage research/publication workflow (from Zoho Projects content).']);
    $tid = (int)$db->lastInsertId();
    $ins = $db->prepare('INSERT INTO template_task_lists (template_id, name, sequence) VALUES (?, ?, ?)');
    foreach ($lists as $i => $name) $ins->execute([$tid, $name, $i + 1]);
    echo "Created template '$templateName' (id $tid) with " . count($lists) . " task lists.\n";
}

// Default journal checklist items (pre-submission + post-submission).
$defaults = [
    ['pre',  'Formatted to journal guidelines'],
    ['pre',  'Plagiarism / similarity check done'],
    ['pre',  'References complete & styled'],
    ['pre',  'Figures & tables quality checked'],
    ['pre',  'Author names & affiliations correct'],
    ['pre',  'Cover letter prepared'],
    ['post', 'Submission confirmation received'],
    ['post', 'Manuscript ID recorded'],
    ['post', 'Reviewer comments tracked'],
    ['post', 'Revision deadline noted'],
    ['post', 'Final decision recorded'],
];
$have = (int)$db->query('SELECT COUNT(*) FROM journal_checklist_defaults')->fetchColumn();
if ($have > 0) {
    echo "Journal checklist defaults already present ($have) — skipping.\n";
} else {
    $ins = $db->prepare('INSERT INTO journal_checklist_defaults (phase, item, sequence) VALUES (?, ?, ?)');
    foreach ($defaults as $i => $d) $ins->execute([$d[0], $d[1], $i + 1]);
    echo "Seeded " . count($defaults) . " default checklist items.\n";
}
echo "Done.\n";
