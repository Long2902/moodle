<?php
$root=dirname(__DIR__);$page=file_get_contents($root.'/discovery.php');$form=file_get_contents($root.'/classes/form/discovery_form.php');$css=file_get_contents($root.'/styles.css');$lib=file_get_contents($root.'/lib.php');$en=file_get_contents($root.'/lang/en/local_coursepublisher.php');$vi=file_get_contents($root.'/lang/vi/local_coursepublisher.php');
$checks=[
 'discovery nav'=>strpos($lib,"'discovery' => ['/local/coursepublisher/discovery.php'")!==false,
 'discovery page'=>file_exists($root.'/discovery.php'),
 'discovery form'=>file_exists($root.'/classes/form/discovery_form.php'),
 'balanced discovery cards'=>substr_count($page,'local_coursepublisher_card_start(')===substr_count($page,'local_coursepublisher_card_end()'),
 'select all ready control'=>strpos($page,'cp-discovery-select-all')!==false && strpos($page,"get_string('discoveryselectall'")!==false,
 'matched rule evidence'=>strpos($page, "get_string('matchedrules'") !== false && strpos($page, "['matchedruleids']") !== false,
    'existing binding evidence'=>strpos($page, "get_string('existingbinding'") !== false && strpos($page, "['targetbindid']") !== false,
 'nav targetgroups strings'=>strpos($en,"\$string['nav_targetgroups']")!==false && strpos($vi,"\$string['nav_targetgroups']")!==false,
 'nav rules strings'=>strpos($en,"\$string['nav_discoveryrules']")!==false && strpos($vi,"\$string['nav_discoveryrules']")!==false,
 'nav discovery strings'=>strpos($en,"\$string['nav_discovery']")!==false && strpos($vi,"\$string['nav_discovery']")!==false,
 'ready status strings'=>strpos($en,"\$string['discovery_status_READY_NEW']")!==false && strpos($vi,"\$string['discovery_status_READY_NEW']")!==false,
 'target group grade strings'=>strpos($en,"\$string['targetgroupgrade']")!==false && strpos($vi,"\$string['targetgroupgrade']")!==false,
 'discovery summary uses styled component'=>strpos($page, "'cp-preview-summary'")!==false && strpos($page, "'cp-summary-tile'")!==false && strpos($page, "'cp-summary-label'")!==false && strpos($page, "'cp-summary-value'")!==false,
 'discovery summary component has css'=>strpos($css,'.cp-preview-summary')!==false && strpos($css,'.cp-summary-tile')!==false && strpos($css,'.cp-summary-label')!==false && strpos($css,'.cp-summary-value')!==false,
 'discovery summary has no orphan stat classes'=>strpos($page,'cp-stat-grid')===false && strpos($page,'cp-stat-card')===false && strpos($page,'cp-stat-value')===false && strpos($page,'cp-stat-label')===false,
 'show unmatched option wired'=>strpos($form,"'showunmatched'")!==false && strpos($form,"get_string('discoveryshowunmatched'")!==false && strpos($page,"optional_param('showunmatched'")!==false && strpos($page,"'name'=>'showunmatched'")!==false,
 'display rows use presenter filter'=>strpos($page,'discovery_presenter::visible_rows')!==false && strpos($page,'foreach($displayrows as $row)')!==false,
 'matched courses summary wired'=>strpos($page,'discovery_presenter::matched_course_count')!==false && strpos($page,"get_string('discoverymatchedcourses'")!==false,
];$failed=[];foreach($checks as$n=>$ok){echo($ok?'PASS ':'FAIL ').$n.PHP_EOL;if(!$ok)$failed[]=$n;}exit($failed?1:0);
