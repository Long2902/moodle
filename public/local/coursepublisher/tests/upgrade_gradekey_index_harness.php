<?php
$root = dirname(__DIR__);
$upgrade = file_get_contents($root . '/db/upgrade.php');

$checks = [
    'master dependent index declared' => strpos($upgrade, "'local_cp_master' => ['program_grade_uix'") !== false,
    'target dependent index declared' => strpos($upgrade, "'local_cp_target' => ['route_uix'") !== false,
    'job dependent index declared' => strpos($upgrade, "'local_cp_job' => ['route_ix'") !== false,
    'batch dependent index declared' => strpos($upgrade, "'local_cp_batch' => ['program_grade_ix'") !== false,
    'drops dependent index before precision change' => strpos($upgrade, '$dbman->drop_index($t, $index);') !== false,
    'recreates dependent index after precision change' => strpos($upgrade, '$dbman->add_index($t, $index);') !== false,
];

$drop = strpos($upgrade, '$dbman->drop_index($t, $index);');
$change = strpos($upgrade, '$dbman->change_field_precision($t, $field);');
$add = strpos($upgrade, '$dbman->add_index($t, $index);', $change === false ? 0 : $change);
$checks['DDL order is drop -> change -> add'] = $drop !== false && $change !== false && $add !== false && $drop < $change && $change < $add;

$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) {
        $failed[] = $name;
    }
}
exit($failed ? 1 : 0);
