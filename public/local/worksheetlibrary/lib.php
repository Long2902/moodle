<?php
defined('MOODLE_INTERNAL') || die();
function local_worksheetlibrary_pluginfile($course,$cm,$context,$filearea,$args,$forcedownload,array $options=[]){ if($context->contextlevel!==CONTEXT_SYSTEM||$filearea!=='content')return false; require_login(); \local_worksheetlibrary\service\access_service::require_view(); $versionid=(int)array_shift($args); $filename=array_pop($args); $file=get_file_storage()->get_file($context->id,'local_worksheetlibrary','content',$versionid,'/',$filename); if(!$file)return false; send_stored_file($file,0,0,$forcedownload,['cacheability'=>'private']); }

function local_worksheetlibrary_extend_navigation_course($navigation, $course, $context) { if (\local_worksheetlibrary\service\access_service::can_author()) { $navigation->add(get_string('library','local_worksheetlibrary'), new moodle_url('/local/worksheetlibrary/index.php'), navigation_node::TYPE_CUSTOM, null, 'worksheetlibrary'); } }
