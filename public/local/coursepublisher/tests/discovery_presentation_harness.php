<?php
// Standalone regression harness for discovery preview filtering/count semantics.
define('MOODLE_INTERNAL', true);
require_once __DIR__ . '/../classes/local/discovery_service.php';
require_once __DIR__ . '/../classes/local/discovery_presenter.php';

use local_coursepublisher\local\discovery_presenter;
use local_coursepublisher\local\discovery_service;

$rows = [
    ['courseid' => 40, 'status' => discovery_service::STATUS_UNMATCHED],
    ['courseid' => 165, 'status' => discovery_service::STATUS_UNMATCHED],
    ['courseid' => 167, 'status' => discovery_service::STATUS_UNMATCHED],
    ['courseid' => 41, 'status' => discovery_service::STATUS_READY_NEW],
    ['courseid' => 45, 'status' => discovery_service::STATUS_READY_NEW],
    ['courseid' => 0, 'status' => discovery_service::STATUS_MISSING],
];

$defaultrows = discovery_presenter::visible_rows($rows, false);
$debugrows = discovery_presenter::visible_rows($rows, true);
$matched = discovery_presenter::matched_course_count($rows);

$checks = [
    'default preview hides unmatched course rows' => count($defaultrows) === 3,
    'default preview keeps READY rows' => count(array_filter($defaultrows, fn($r) => $r['status'] === discovery_service::STATUS_READY_NEW)) === 2,
    'default preview keeps MISSING rows' => count(array_filter($defaultrows, fn($r) => $r['status'] === discovery_service::STATUS_MISSING)) === 1,
    'debug option restores unmatched rows' => count($debugrows) === count($rows),
    'matched course count excludes unmatched and zero-course rows' => $matched === 2,
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failed[] = $name;
    }
}
exit($failed ? 1 : 0);
