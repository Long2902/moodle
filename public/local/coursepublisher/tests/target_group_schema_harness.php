<?php
$root = dirname(__DIR__);
$xml = file_get_contents($root . '/db/install.xml');
$upgrade = file_get_contents($root . '/db/upgrade.php');
$version = file_get_contents($root . '/version.php');
$install = file_exists($root . '/db/install.php') ? file_get_contents($root . '/db/install.php') : '';
$checks = [
    'target group table' => strpos($xml, 'TABLE NAME="local_cp_target_group"') !== false,
    'discovery rule table' => strpos($xml, 'TABLE NAME="local_cp_discovery_rule"') !== false,
    'container table' => strpos($xml, 'TABLE NAME="local_cp_discovery_container"') !== false,
    'program root field' => strpos($xml, 'NAME="discoveryrootcategoryid"') !== false,
    'master targetgroupid' => preg_match('~TABLE NAME="local_cp_master".*?NAME="targetgroupid"~s', $xml) === 1,
    'target targetgroupid' => preg_match('~TABLE NAME="local_cp_target".*?NAME="targetgroupid"~s', $xml) === 1,
    'batch targetgroupid' => preg_match('~TABLE NAME="local_cp_batch".*?NAME="targetgroupid"~s', $xml) === 1,
    'gradekey widened' => substr_count($xml, 'NAME="gradekey" TYPE="char" LENGTH="64"') >= 4,
    'upgrade 1.1.1' => strpos($upgrade, '2026082902') !== false,
    'seed legacy groups' => strpos($upgrade, "['10' => 'Lớp 10', '11' => 'Lớp 11', '12' => 'Lớp 12']") !== false,
    'upgrade avoids text pattern equality' => preg_match("~record_exists\('local_cp_discovery_(?:rule|container)'.*?'pattern'~s", $upgrade) !== 1,
    'version bump' => strpos($version, '$plugin->version   = 2026082902;') !== false,
    'fresh install hook' => $install !== '',
    'fresh install container seeds' => strpos($install, 'KHỐI THPT') !== false && strpos($install, 'KHỐI LIÊN CẤP') !== false && strpos($install, 'TRUNG TÂM') !== false,
];
$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL;
    if (!$ok) $failed[] = $name;
}
exit($failed ? 1 : 0);
