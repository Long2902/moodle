<?php
namespace mod_worksheetgrader\external;
use core_external\external_api; use core_external\external_function_parameters; use core_external\external_single_structure; use core_external\external_value;
final class request_office_save extends external_api {
 public static function execute_parameters(): external_function_parameters { return new external_function_parameters(['attemptid'=>new external_value(PARAM_INT,'Attempt id'),'intent'=>new external_value(PARAM_ALPHA,'progress or submit')]); }
 public static function execute(int $attemptid,string $intent): array { global $DB,$USER;$p=self::validate_parameters(self::execute_parameters(),compact('attemptid','intent'));$a=$DB->get_record('wsg_attempt',['id'=>$p['attemptid']],'*',MUST_EXIST);$s=$DB->get_record('wsg_session',['id'=>$a->sessionid],'*',MUST_EXIST);$activity=$DB->get_record('worksheetgrader',['id'=>$s->worksheetgraderid],'*',MUST_EXIST);$cm=get_coursemodule_from_instance('worksheetgrader',$activity->id,$activity->course,false,MUST_EXIST);$ctx=\context_module::instance($cm->id);self::validate_context($ctx);require_capability('mod/worksheetgrader:submit',$ctx);$req=\mod_worksheetgrader\service\office_submission_service::request($a,$p['intent'],(int)$USER->id);return ['ok'=>true,'requestid'=>$req->requestid,'status'=>$req->status]; }
 public static function execute_returns(): external_single_structure { return new external_single_structure(['ok'=>new external_value(PARAM_BOOL,'OK'),'requestid'=>new external_value(PARAM_ALPHANUMEXT,'Request id'),'status'=>new external_value(PARAM_ALPHA,'Status')]); }
}
