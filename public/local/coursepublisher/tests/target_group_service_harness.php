<?php
$root = dirname(__DIR__);
$service = file_get_contents($root . '/classes/local/service.php');
$masterform = file_get_contents($root . '/classes/form/master_form.php');
$targetform = file_get_contents($root . '/classes/form/target_form.php');
$programform = file_get_contents($root . '/classes/form/program_form.php');
$targetgrouppage = file_get_contents($root . '/target_groups.php');
$checks = [
    'resolve target group' => strpos($service, 'function resolve_target_group') !== false,
    'target group options' => strpos($service, 'function target_group_options') !== false,
    'target group label' => strpos($service, 'function target_group_label') !== false,
    'save target group' => strpos($service, 'function save_target_group') !== false,
    'new program seeds default target groups' => strpos($service, 'ensure_default_target_groups') !== false && preg_match('~insert_record\(\'local_cp_program\'.*?ensure_default_target_groups~s', $service) === 1,
    'program defaults avoid text pattern equality' => strpos($service, 'default_discovery_rule_exists') !== false,
    'target group program immutable' => strpos($service, "targetgroupprogramimmutable") !== false,
    'custom key syntax 64' => strpos($service, 'strlen($gradekey) > 64') !== false,
    'master dynamic group select' => strpos($masterform, 'target_group_options') !== false,
    'target dynamic group select' => strpos($targetform, 'target_group_options') !== false,
    'program root field' => strpos($programform, 'discoveryrootcategoryid') !== false,
    'target groups page' => file_exists($root . '/target_groups.php'),
    'target group master status column' => strpos($targetgrouppage, 'masterconfigured') !== false && strpos($targetgrouppage, 'mastercourseid') !== false,
    'target group sort order column' => strpos($targetgrouppage, "get_string('sortorder'") !== false,
    'target group form' => file_exists($root . '/classes/form/target_group_form.php'),
];
$failed=[]; foreach($checks as $n=>$ok){echo ($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;} exit($failed?1:0);
