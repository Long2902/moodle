<?php
$s=file_get_contents(dirname(__DIR__).'/classes/local/service.php');
function body($s,$name,$next){$a=strpos($s,"function $name");$b=strpos($s,"function $next",$a);return substr($s,$a,$b-$a);}
$master=body($s,'save_master','save_region');$target=body($s,'save_target','validate_match_definition');$program=body($s,'save_program','validate_master_candidate');
$checks=[
 'master insert targetgroupid'=>strpos($master, "'gradekey' => \$data->gradekey,\n            'targetgroupid' => (int)\$group->id,\n            'courseid'") !== false,
 'target insert targetgroupid'=>strpos($target, "'gradekey' => \$data->gradekey,\n            'targetgroupid' => (int)\$group->id,\n            'courseid'") !== false,
 'program duplicate throw once'=>substr_count($program,"throw new \\moodle_exception('duplicateprogram'")===2,
];
$failed=[];foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;}exit($failed?1:0);
