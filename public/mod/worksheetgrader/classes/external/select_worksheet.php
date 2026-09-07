<?php
namespace mod_worksheetgrader\external;
use core_external\external_api; use core_external\external_function_parameters; use core_external\external_single_structure; use core_external\external_value;
final class select_worksheet extends external_api {
 public static function execute_parameters(): external_function_parameters { return new external_function_parameters(['sessionid'=>new external_value(PARAM_INT,'Session id'),'versionid'=>new external_value(PARAM_INT,'Published library version id')]); }
 public static function execute(int $sessionid,int $versionid): array { global $DB,$USER;$p=self::validate_parameters(self::execute_parameters(),compact('sessionid','versionid'));$s=$DB->get_record('wsg_session',['id'=>$p['sessionid']],'*',MUST_EXIST);$a=$DB->get_record('worksheetgrader',['id'=>$s->worksheetgraderid],'*',MUST_EXIST);$cm=get_coursemodule_from_instance('worksheetgrader',$a->id,$a->course,false,MUST_EXIST);$ctx=\context_module::instance($cm->id);self::validate_context($ctx);require_capability('mod/worksheetgrader:manageactivity',$ctx);$s=\mod_worksheetgrader\service\worksheet_snapshot_service::attach_published_version($sessionid,$versionid,(int)$USER->id);return ['ok'=>true,'kind'=>$s->worksheetkind,'hash'=>$s->worksheethash]; }
 public static function execute_returns(): external_single_structure { return new external_single_structure(['ok'=>new external_value(PARAM_BOOL,'OK'),'kind'=>new external_value(PARAM_TEXT,'Worksheet kind'),'hash'=>new external_value(PARAM_ALPHANUMEXT,'Snapshot hash')]); }
}
