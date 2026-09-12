<?php
$root=dirname(__DIR__);
$files=[];foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS)) as $f){if($f->isFile()&&$f->getExtension()==='php'&&strpos($f->getPathname(),'/lang/')===false&&strpos($f->getPathname(),'/tests/')===false)$files[$f->getPathname()]=file_get_contents($f->getPathname());}
$all=implode("\n",$files);
$job=file_get_contents($root.'/classes/local/job_service.php');
$checks=[
 'no service GRADES'=>strpos($all,'service::GRADES')===false && strpos($all,'self::GRADES')===false,
 'batch form dynamic'=>strpos(file_get_contents($root.'/classes/form/batch_form.php'),'target_group_options')!==false,
 'preview form dynamic'=>strpos(file_get_contents($root.'/classes/form/preview_form.php'),'target_group_options')!==false,
 'no dynamic grade string concat'=>strpos($all,"get_string('grade' .")===false,
 'legacy create_or_reuse signature'=>preg_match('~function create_or_reuse\(int \$programid, string \$gradekey, string \$sourcetype,\s+int \$sourceid, int \$schoolid~s',$job)===1,
 'health loads groups'=>strpos(file_get_contents($root.'/classes/local/service.php'),"local_cp_target_group")!==false,
 'batch resolves target group'=>strpos(file_get_contents($root.'/classes/local/batch_service.php'),'resolve_target_group')!==false,
];$failed=[];foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;}exit($failed?1:0);
