<?php
namespace mod_worksheetgrader\service;
final class readiness_service {
    public static function inspect(\stdClass $session, \stdClass $activity, \context_module $context): array {
        global $DB;
        $checks = [];
        $checks['worksheet'] = ['ok'=>session_manager::has_content($session), 'label'=>'Phiếu học tập'];
        $students = get_enrolled_users($context, 'mod/worksheetgrader:submit', 0, 'u.id');
        if (($session->teammode ?? '') === 'individual') {
            $checks['teams'] = ['ok'=>true, 'label'=>'Cá nhân · không cần chia nhóm'];
        } else {
            $membercount = (int)$DB->count_records_sql('SELECT COUNT(DISTINCT m.userid) FROM {wsg_member} m JOIN {wsg_team} t ON t.id=m.teamid WHERE t.sessionid=? AND m.status=?', [$session->id,'active']);
            $teamcount = $DB->count_records('wsg_team', ['sessionid'=>$session->id]);
            $checks['teams'] = ['ok'=>$teamcount > 0 && $membercount >= count($students), 'label'=>"Nhóm & thành viên ($membercount/".count($students).')'];
        }
        $now=time();
        $checks['time']=['ok'=>(empty($session->timeclose) || $session->timeclose>$now), 'label'=>'Thời gian'];
        $migration=(string)($session->migrationstatus ?? 'not_required');
        $checks['migration']=['ok'=>$migration !== 'migration_blocked', 'label'=>'Dữ liệu legacy'];
        $kind=(string)($session->worksheetkind ?? 'html');
        if ($kind === 'office') {
            $ocfg=get_config('local_digieraoffice');
            $checks['office']=['ok'=>!empty($ocfg->enabled)&&!empty($ocfg->documentserverurl)&&!empty($ocfg->jwtsecret), 'label'=>'ONLYOFFICE Docs'];
        }
        $ok=!in_array(false,array_map(fn($c)=>(bool)$c['ok'],$checks),true);
        return ['ok'=>$ok,'checks'=>$checks,'studentcount'=>count($students)];
    }
    public static function open(\stdClass $session, \stdClass $activity, \context_module $context, int $actorid): array {
        global $DB;
        $result=self::inspect($session,$activity,$context);
        if(!$result['ok']) throw new \moodle_exception('Buổi chưa sẵn sàng để mở');
        $session->setupcomplete=1;
        $DB->update_record('wsg_session',$session);
        session_manager::set_status($session,'open');
        audit_logger::log((int)$activity->id,'v12_session_opened',['readiness'=>$result],(int)$session->id,0,0,$actorid);
        return $result;
    }
}
