<?php
// Standalone regression harness for discovery summary semantics.
define('MOODLE_INTERNAL', true);
require_once __DIR__ . '/../classes/local/discovery_service.php';

$class = new ReflectionClass('local_coursepublisher\\local\\discovery_service');
if (!$class->hasMethod('summarize_rows')) {
    fwrite(STDERR, "FAIL summarize_rows helper missing\n");
    exit(1);
}
$method = $class->getMethod('summarize_rows');
$method->setAccessible(true);

$rows = [
    [
        'regioncategoryid' => 10,
        'containercategoryid' => 20,
        'unitcategoryid' => 30,
        'courseid' => 165,
        'status' => 'EXISTING',
    ],
    [
        'regioncategoryid' => 10,
        'containercategoryid' => 20,
        'unitcategoryid' => 31,
        'courseid' => 45,
        'status' => 'UNMATCHED',
    ],
    [
        'regioncategoryid' => 10,
        'containercategoryid' => 20,
        'unitcategoryid' => 31,
        'courseid' => 0,
        'status' => 'MISSING',
    ],
];

$summary = $method->invoke(null, $rows);
$checks = [
    'region count comes from displayed rows' => ($summary['regions'] ?? -1) === 1,
    'container count comes from displayed rows' => ($summary['containers'] ?? -1) === 1,
    'unit count is unique displayed units' => ($summary['units'] ?? -1) === 2,
    'course count is unique nonzero displayed courses' => ($summary['courses'] ?? -1) === 2,
    'existing status counted' => (($summary['statuses']['EXISTING'] ?? 0) === 1),
    'unmatched status counted' => (($summary['statuses']['UNMATCHED'] ?? 0) === 1),
    'missing status counted' => (($summary['statuses']['MISSING'] ?? 0) === 1),
];

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failed[] = $name;
    }
}
exit($failed ? 1 : 0);
