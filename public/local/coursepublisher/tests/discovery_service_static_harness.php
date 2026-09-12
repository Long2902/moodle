<?php
$root=dirname(__DIR__); $file=$root.'/classes/local/discovery_service.php'; $s=file_exists($file)?file_get_contents($file):'';
$checks=[
 'service exists'=>$s!=='',
 'ready status'=>strpos($s,"STATUS_READY_NEW = 'READY_NEW'")!==false,
 'existing status'=>strpos($s,"STATUS_EXISTING = 'EXISTING'")!==false,
 'unmatched status'=>strpos($s,"STATUS_UNMATCHED = 'UNMATCHED'")!==false,
 'group conflict'=>strpos($s,"STATUS_CONFLICT_GROUP = 'CONFLICT_GROUP'")!==false,
 'duplicate conflict'=>strpos($s,"STATUS_CONFLICT_DUPLICATE_COURSE = 'CONFLICT_DUPLICATE_COURSE'")!==false,
 'existing conflict'=>strpos($s,"STATUS_CONFLICT_EXISTING_BINDING = 'CONFLICT_EXISTING_BINDING'")!==false,
 'reused conflict'=>strpos($s,"STATUS_CONFLICT_COURSE_REUSED = 'CONFLICT_COURSE_REUSED'")!==false,
 'invalid topology'=>strpos($s,"STATUS_INVALID_TOPOLOGY = 'INVALID_TOPOLOGY'")!==false,
 'root field enforced'=>strpos($s,'discoveryrootcategoryid')!==false && strpos($s,'parent')!==false,
 'region exact category reuse'=>strpos($s,'regionsbycategory')!==false,
 'unit exact category reuse'=>strpos($s,'schoolsbycategory')!==false,
 'scan method'=>strpos($s,'function scan(')!==false,
 'strict selected ids'=>strpos($s,'validate_selected_ids')!==false,
 'scan read only'=>!preg_match('~function scan\(.*?return \[.*?\n        \];\n    \}~s',$s,$m) || !preg_match('~service::save_(?:region|school|target)~',$m[0]),
];
$failed=[];foreach($checks as $n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;}exit($failed?1:0);
