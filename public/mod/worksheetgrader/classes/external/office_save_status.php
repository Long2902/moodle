<?php
namespace mod_worksheetgrader\external;
use core_external\external_api; use core_external\external_function_parameters; use core_external\external_single_structure; use core_external\external_value;
final class office_save_status extends external_api {
 public static function execute_parameters(): external_function_parameters { return new external_function_parameters(['requestid'=>new external_value(PARAM_ALPHANUMEXT,'Request id')]); }
 public static function execute(string $requestid): array { global $USER;$p=self::validate_parameters(self::execute_parameters(),compact('requestid'));$r=\mod_worksheetgrader\service\office_submission_service::status($p['requestid'],(int)$USER->id);return ['requestid'=>$r->requestid,'status'=>$r->status,'intent'=>$r->intent,'errorcode'=>$r->errorcode]; }
 public static function execute_returns(): external_single_structure { return new external_single_structure(['requestid'=>new external_value(PARAM_ALPHANUMEXT,'Request id'),'status'=>new external_value(PARAM_ALPHA,'Status'),'intent'=>new external_value(PARAM_ALPHA,'Intent'),'errorcode'=>new external_value(PARAM_ALPHANUMEXT,'Error code',VALUE_DEFAULT,'')]); }
}
