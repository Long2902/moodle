<?php
namespace mod_worksheetgrader\service;
final class office_attempt_service {
    public static function assert_member(\stdClass $attempt, int $userid): void {
        global $DB;
        $members=json_decode((string)$attempt->membersnapshot,true) ?: team_manager::get_member_ids((int)$attempt->teamid);
        if(!in_array($userid,array_map('intval',$members),true)) throw new \required_capability_exception(\context_system::instance(),'mod/worksheetgrader:submit','nopermissions','');
    }
    public static function ensure_working_file(\context_module $context, \stdClass $session, \stdClass $attempt): \stored_file {
        $fs=get_file_storage();
        foreach($fs->get_area_files($context->id,'mod_worksheetgrader','officeworking',(int)$attempt->id,'id',false) as $f)return $f;
        $template=null;foreach($fs->get_area_files($context->id,'mod_worksheetgrader','officetemplate',(int)$session->id,'id',false) as $f){$template=$f;break;}
        if(!$template) throw new \moodle_exception('Office template missing');
        $file=$fs->create_file_from_storedfile(['contextid'=>$context->id,'component'=>'mod_worksheetgrader','filearea'=>'officeworking','itemid'=>$attempt->id,'filepath'=>'/','filename'=>$template->get_filename()],$template);
        global $DB;$DB->update_record('wsg_attempt',(object)['id'=>$attempt->id,'officefilename'=>$file->get_filename(),'officerevision'=>max(1,(int)($attempt->officerevision??1))]);
        return $file;
    }
    public static function editor_config(\context_module $context, \stdClass $activity, \stdClass $session, \stdClass $team, \stdClass $attempt, \stdClass $user, bool $readonly=false): array {
        $file=self::ensure_working_file($context,$session,$attempt);
        $filearea = 'officeworking';
        if ($readonly && $attempt->status !== 'inprogress') {
            foreach (get_file_storage()->get_area_files($context->id, 'mod_worksheetgrader', 'officesubmission', (int)$attempt->id, 'id', false) as $submitted) {
                $file = $submitted;
                $filearea = 'officesubmission';
                break;
            }
        }
        $cfg=get_config('local_digieraoffice');
        if(empty($cfg->enabled)||empty($cfg->documentserverurl)||empty($cfg->jwtsecret)) throw new \moodle_exception('ONLYOFFICE is not configured');
        $revision=max(1,(int)($attempt->officerevision??1));
        $key = $filearea === 'officesubmission'
            ? \local_digieraoffice\document_key::make('wsg-submission', (int)$attempt->id, $revision, $file->get_contenthash())
            : \local_digieraoffice\document_key::make('wsg-attempt', (int)$attempt->id, $revision);
        $url=\moodle_url::make_pluginfile_url($context->id,'mod_worksheetgrader',$filearea,(int)$attempt->id,'/',$file->get_filename(),false)->out(false);
        $callback=(new \moodle_url('/mod/worksheetgrader/office_callback.php',['attemptid'=>$attempt->id]))->out(false);
        $doc=['fileType'=>pathinfo($file->get_filename(),PATHINFO_EXTENSION),'key'=>$key,'title'=>$session->name,'url'=>$url,'permissions'=>['edit'=>!$readonly,'download'=>true,'print'=>true]];
        $editor=['callbackUrl'=>$callback,'lang'=>current_language(),'mode'=>$readonly?'view':'edit','user'=>['id'=>(string)$user->id,'name'=>fullname($user)],'customization'=>['autosave'=>true,'forcesave'=>true,'compactHeader'=>false]];
        return ['server'=>rtrim((string)$cfg->documentserverurl,'/'),'key'=>$key,'config'=>\local_digieraoffice\editor_config::build($doc,$editor,(string)$cfg->jwtsecret)];
    }
}
