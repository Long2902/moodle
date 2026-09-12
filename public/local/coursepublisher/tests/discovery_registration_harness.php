<?php
$root=dirname(__DIR__);$s=file_get_contents($root.'/classes/local/discovery_service.php');
$checks=[
 'candidate key'=>strpos($s,'function candidate_key')!==false,
 'register selected'=>strpos($s,'function register_selected')!==false,
 're-scan before write'=>preg_match('~function register_selected\(.*?self::scan\(~s',$s)===1,
 'uses save region'=>strpos($s,'service::save_region')!==false,
 'uses save school'=>strpos($s,'service::save_school')!==false,
 'uses save target'=>strpos($s,'service::save_target')!==false,
 'auto region code'=>strpos($s,"'AUTO_R_' .")!==false,
 'auto unit code'=>strpos($s,"'AUTO_U_' .")!==false,
 'became existing'=>strpos($s,"'BECAME_EXISTING'")!==false,
 'failed revalidation'=>strpos($s,"'FAILED_REVALIDATION'")!==false,
 'failed write'=>strpos($s,"'FAILED_WRITE'")!==false,
 'no direct target insert'=>!preg_match("~insert_record\(['\"]local_cp_target['\"]~",$s),
];$failed=[];foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;}exit($failed?1:0);
