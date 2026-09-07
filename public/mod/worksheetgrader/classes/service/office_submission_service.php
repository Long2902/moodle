<?php
namespace mod_worksheetgrader\service;
final class office_submission_service {
    public static function request(\stdClass $attempt, string $intent, int $userid): \stdClass {
        global $DB;
        office_attempt_service::assert_member($attempt,$userid);
        if($attempt->status!=='inprogress') throw new \moodle_exception('attemptnoteditable','mod_worksheetgrader');
        if(!in_array($intent,['progress','submit'],true)) throw new \invalid_parameter_exception('Invalid save intent');
        $session=$DB->get_record('wsg_session',['id'=>$attempt->sessionid],'*',MUST_EXIST);
        $activity=$DB->get_record('worksheetgrader',['id'=>$session->worksheetgraderid],'*',MUST_EXIST);
        $cm=get_coursemodule_from_instance('worksheetgrader',$activity->id,$activity->course,false,MUST_EXIST);
        $context=\context_module::instance($cm->id);$file=office_attempt_service::ensure_working_file($context,$session,$attempt);
        $revision=max(1,(int)($attempt->officerevision??1));
        $key=\local_digieraoffice\document_key::make('wsg-attempt',(int)$attempt->id,$revision);
        $requestid=bin2hex(random_bytes(16));$now=time();
        $id=$DB->insert_record('wsg_office_save',(object)['attemptid'=>$attempt->id,'requestid'=>$requestid,'intent'=>$intent,'status'=>'pending','documentkey'=>$key,'revision'=>$revision,'createdby'=>$userid,'errorcode'=>'','timecreated'=>$now,'timemodified'=>$now]);
        try{$response=\local_digieraoffice\command_client::forcesave($key,$requestid);if(!empty($response['error']))throw new \moodle_exception('ONLYOFFICE forcesave error '.(int)$response['error']);$DB->set_field('wsg_office_save','status','requested',['id'=>$id]);}
        catch(\Throwable $e){$DB->update_record('wsg_office_save',(object)['id'=>$id,'status'=>'failed','errorcode'=>'command','timemodified'=>time()]);throw $e;}
        return $DB->get_record('wsg_office_save',['id'=>$id],'*',MUST_EXIST);
    }
    public static function status(string $requestid,int $userid): \stdClass { global $DB;$r=$DB->get_record('wsg_office_save',['requestid'=>$requestid],'*',MUST_EXIST);$a=$DB->get_record('wsg_attempt',['id'=>$r->attemptid],'*',MUST_EXIST);self::assert_request_visible($a,$userid);return $r; }
    private static function assert_request_visible(\stdClass $attempt,int $userid): void { office_attempt_service::assert_member($attempt,$userid); }
    public static function handle_callback(int $attemptid,array $payload,string $token): array {
        global $CFG,$DB;
        $cfg=get_config('local_digieraoffice');
        if($token==='')throw new \moodle_exception('Missing callback JWT');
        \local_digieraoffice\jwt::decode($token,(string)$cfg->jwtsecret);
        $attempt=$DB->get_record('wsg_attempt',['id'=>$attemptid],'*',MUST_EXIST);
        $status=(int)($payload['status']??0);$requestid=(string)($payload['userdata']??'');
        $request=$requestid!==''?$DB->get_record('wsg_office_save',['requestid'=>$requestid,'attemptid'=>$attemptid]):null;
        if(!in_array($status,[2,6],true))return ['error'=>0];
        if(empty($payload['url']))throw new \moodle_exception('ONLYOFFICE callback URL missing');
        $session=$DB->get_record('wsg_session',['id'=>$attempt->sessionid],'*',MUST_EXIST);$activity=$DB->get_record('worksheetgrader',['id'=>$session->worksheetgraderid],'*',MUST_EXIST);$cm=get_coursemodule_from_instance('worksheetgrader',$activity->id,$activity->course,false,MUST_EXIST);$context=\context_module::instance($cm->id);
        $current=office_attempt_service::ensure_working_file($context,$session,$attempt);$expected=\local_digieraoffice\document_key::make('wsg-attempt',(int)$attempt->id,max(1,(int)($attempt->officerevision??1)));
        if($request && $request->documentkey!==$expected)throw new \moodle_exception('Stale document key');
        $downloadurl = (string)$payload['url'];
        $configuredhost = strtolower((string)parse_url((string)$cfg->documentserverurl, PHP_URL_HOST));
        $downloadhost = strtolower((string)parse_url($downloadurl, PHP_URL_HOST));
        if (strtolower((string)parse_url($downloadurl, PHP_URL_SCHEME)) !== 'https' || $downloadhost === '' || $downloadhost !== $configuredhost) {
            throw new \moodle_exception('Rejected ONLYOFFICE callback download URL');
        }
        require_once($CFG->libdir.'/filelib.php');
        $curl=new \curl();
        $raw=$curl->get($downloadurl, [], ['CURLOPT_TIMEOUT'=>120]);
        $info=$curl->get_info();
        if ((int)($info['http_code']??0) < 200 || (int)($info['http_code']??0) >= 300 || !is_string($raw) || $raw === '') {
            throw new \moodle_exception('ONLYOFFICE download failed');
        }
        $tmp=make_request_directory().'/saved.bin';
        if (file_put_contents($tmp,$raw) === false) { throw new \moodle_exception('cannotwritefile'); }
        $fs=get_file_storage();$fs->delete_area_files($context->id,'mod_worksheetgrader','officeworking',$attemptid);$working=$fs->create_file_from_pathname(['contextid'=>$context->id,'component'=>'mod_worksheetgrader','filearea'=>'officeworking','itemid'=>$attemptid,'filepath'=>'/','filename'=>$current->get_filename(),'mimetype'=>$current->get_mimetype()],$tmp);
        if($request){$DB->update_record('wsg_office_save',(object)['id'=>$request->id,'status'=>'saved','errorcode'=>'','timemodified'=>time()]);if($request->intent==='submit' && $attempt->status==='inprogress'){$fs->delete_area_files($context->id,'mod_worksheetgrader','officesubmission',$attemptid);$fs->create_file_from_storedfile(['contextid'=>$context->id,'component'=>'mod_worksheetgrader','filearea'=>'officesubmission','itemid'=>$attemptid,'filepath'=>'/','filename'=>$working->get_filename()],$working);attempt_manager::submit($attempt,$session,$activity,$cm,(int)$request->createdby);$DB->set_field('wsg_office_save','status','submitted',['id'=>$request->id]);}}
        if($status===2){$DB->update_record('wsg_attempt',(object)['id'=>$attemptid,'officerevision'=>max(1,(int)$attempt->officerevision)+1,'officefilename'=>$working->get_filename(),'timemodified'=>time()]);}
        return ['error'=>0];
    }
}
