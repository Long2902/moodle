<?php
define('MOODLE_INTERNAL', true);
require_once dirname(__DIR__) . '/classes/local/discovery_matcher.php';
use local_coursepublisher\local\discovery_matcher;

function check($name, $ok) { echo ($ok ? 'PASS ' : 'FAIL ') . $name . PHP_EOL; if (!$ok) $GLOBALS['failed'] = true; }
$GLOBALS['failed'] = false;
check('normalise whitespace/case', discovery_matcher::normalise_text("  LớP   10 \n") === discovery_matcher::normalise_text('lỚp 10'));
$course = (object)['fullname' => 'LỚP 10 - THPT A', 'shortname' => 'tn_AI_L10_2627'];
$rules = [
    (object)['id'=>1,'targetgroupid'=>10,'groupkey'=>'10','fieldname'=>'fullname','matchtype'=>'contains','pattern'=>'lớp 10','priority'=>100],
    (object)['id'=>2,'targetgroupid'=>10,'groupkey'=>'10','fieldname'=>'shortname','matchtype'=>'contains','pattern'=>'_L10_','priority'=>100],
];
$r = discovery_matcher::classify_course($course, $rules);
check('same group multi-rule matched', $r['status'] === 'matched' && $r['targetgroupid'] === 10 && count($r['ruleids']) === 2);
$rules[] = (object)['id'=>3,'targetgroupid'=>20,'groupkey'=>'DMST','fieldname'=>'fullname','matchtype'=>'contains','pattern'=>'THPT A','priority'=>10];
$r = discovery_matcher::classify_course($course, $rules);
check('cross group conflict', $r['status'] === 'conflict' && count($r['targetgroupids']) === 2);
check('starts with', discovery_matcher::match_value('LỚP 11 - A', 'starts_with', 'lớp 11'));
check('regex valid', discovery_matcher::validate_regex('/^LỚP\s+1[012]/iu'));
check('regex match', discovery_matcher::match_value('LỚP 12 - A', 'regex', '/^LỚP\s+12/iu'));
check('regex invalid', !discovery_matcher::validate_regex('/[abc/'));
exit($GLOBALS['failed'] ? 1 : 0);
