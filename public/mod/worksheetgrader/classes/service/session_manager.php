<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

/** Manage dynamic teaching sessions and their membership lock state. */
class session_manager {
    /**
     * Return whether a session is currently available for student editing.
     *
     * This lives in an autoloaded service class so web-service/AJAX entry points do
     * not depend on locallib.php having been included by a page script.
     */
    public static function is_open(\stdClass $session, ?int $now = null): bool {
        $now = $now ?? time();
        if (($session->status ?? '') !== 'open') {
            return false;
        }
        if (!empty($session->timeopen) && (int)$session->timeopen > $now) {
            return false;
        }
        if (!empty($session->timeclose) && (int)$session->timeclose < $now) {
            return false;
        }
        return true;
    }


    /** Return whether the session has a portable worksheet snapshot. */
    public static function has_content(\stdClass $session): bool {
        global $DB;
        $kind = (string)($session->worksheetkind ?? '');
        if (in_array($kind, ['office', 'pdf'], true)) {
            $activity = $DB->get_record('worksheetgrader', ['id' => $session->worksheetgraderid], 'id,course', MUST_EXIST);
            $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
            return (bool)get_file_storage()->get_area_files(\context_module::instance($cm->id)->id,
                'mod_worksheetgrader', 'officetemplate', (int)$session->id, 'id', false);
        }
        if (($session->contentmode ?? 'html') === 'docspace') {
            return $DB->record_exists('wsg_docspace', ['sessionid' => (int)$session->id, 'teamid' => 0, 'scope' => 'template']);
        }
        return trim((string)($session->contenthtml ?? '')) !== '';
    }

    public static function create(int $activityid, array $data): int {
        global $DB;
        $activity = $DB->get_record('worksheetgrader', ['id' => $activityid], '*', MUST_EXIST);
        $now = time();
        $record = (object)[
            'worksheetgraderid' => $activityid,
            'name' => trim($data['name'] ?? '') ?: 'Buổi ' . userdate($now, '%d/%m/%Y'),
            'sessiondate' => (int)($data['sessiondate'] ?? $now),
            'status' => 'draft',
            'teammode' => $data['teammode'] ?? 'temporary',
            'contentmode' => in_array(($data['contentmode'] ?? 'html'), ['html', 'docspace'], true)
                ? $data['contentmode'] : 'html',
            'groupingid' => (int)($data['groupingid'] ?? 0),
            'maxpoints' => (float)($data['maxpoints'] ?? $activity->grade),
            'contenthtml' => $data['contenthtml'] ?? $activity->contenthtml,
            'contentformat' => FORMAT_HTML,
            'sourcefilename' => '',
            'metadatajson' => '{}',
            'membershiplocked' => 0,
            'timeopen' => (int)($data['timeopen'] ?? 0),
            'timeclose' => (int)($data['timeclose'] ?? 0),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('wsg_session', $record);
        audit_logger::log($activityid, 'session_created', ['name' => $record->name], $id);
        return $id;
    }

    public static function set_status(\stdClass $session, string $status): void {
        global $DB;
        if (!in_array($status, ['draft', 'open', 'closed'], true)) {
            throw new \moodle_exception('invalidparameter');
        }
        if ($status === 'open') {
            if (!self::has_content($session)) {
                throw new \moodle_exception('cannotopenwithoutcontent', 'mod_worksheetgrader');
            }
            if (($session->teammode ?? 'temporary') !== 'individual' &&
                    !$DB->count_records('wsg_team', ['sessionid' => $session->id])) {
                throw new \moodle_exception('cannotopenwithoutteams', 'mod_worksheetgrader');
            }
            // Opening or resuming always freezes membership until the teacher explicitly unlocks it.
            $session->membershiplocked = 1;
        }
        if ($status === 'draft' && !$DB->record_exists('wsg_attempt', ['sessionid' => $session->id])) {
            $session->membershiplocked = 0;
        }
        if ($status === 'closed') {
            $session->membershiplocked = 1;
        }
        $session->status = $status;
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        audit_logger::log($session->worksheetgraderid, 'session_' . $status, [], $session->id);
    }

    /**
     * Temporarily pause a running session so a teacher can change teams.
     * Student editing is disabled while membershiplocked = 0 on an open session.
     */
    public static function set_membership_locked(\stdClass $session, bool $locked, int $actorid): void {
        global $DB;
        if (!in_array($session->status, ['draft', 'open'], true)) {
            throw new \moodle_exception('cannoteditclosedteams', 'mod_worksheetgrader');
        }
        if ($locked && ($session->teammode ?? 'temporary') !== 'individual' &&
                !$DB->count_records('wsg_team', ['sessionid' => $session->id])) {
            throw new \moodle_exception('cannotopenwithoutteams', 'mod_worksheetgrader');
        }
        $session->membershiplocked = $locked ? 1 : 0;
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        audit_logger::log(
            (int)$session->worksheetgraderid,
            $locked ? 'team_membership_locked' : 'team_membership_unlocked',
            ['sessionstatus' => $session->status],
            (int)$session->id,
            0,
            0,
            $actorid
        );
    }

    public static function copy_teams(int $sourceid, int $targetid, int $actorid): void {
        global $DB;
        $source = $DB->get_record('wsg_session', ['id' => $sourceid], '*', MUST_EXIST);
        $target = $DB->get_record('wsg_session', ['id' => $targetid], '*', MUST_EXIST);
        if ($source->worksheetgraderid !== $target->worksheetgraderid || $target->status !== 'draft') {
            throw new \moodle_exception('invalidparameter');
        }
        $layout = [];
        foreach ($DB->get_records('wsg_team', ['sessionid' => $sourceid], 'id') as $team) {
            $layout[] = [
                'name' => $team->name,
                'representativeuserid' => $team->representativeuserid,
                'members' => array_values($DB->get_fieldset_select(
                    'wsg_member', 'userid', 'teamid = ? AND status = ?', [$team->id, 'active']
                )),
            ];
        }
        team_manager::replace_layout($targetid, $layout, $actorid);
    }

    /** V12: create local Moodle state only. Never call DocSpace or ONLYOFFICE here. */
    public static function create_v12(int $activityid, array $data): int {
        global $DB;
        $activity = $DB->get_record('worksheetgrader', ['id' => $activityid], '*', MUST_EXIST);
        $now = time();
        $record = (object)[
            'worksheetgraderid' => $activityid,
            'name' => trim((string)($data['name'] ?? '')) ?: 'Buổi ' . userdate($now, '%d/%m/%Y'),
            'sessiondate' => (int)($data['sessiondate'] ?? $now),
            'status' => 'draft',
            'teammode' => (($data['workmode'] ?? 'grouped') === 'individual') ? 'individual' : 'temporary',
            'contentmode' => 'html',
            'groupingid' => 0,
            'maxpoints' => (float)($data['maxpoints'] ?? $activity->grade),
            'contenthtml' => '',
            'contentformat' => FORMAT_HTML,
            'sourcefilename' => '',
            'metadatajson' => json_encode(['description' => trim((string)($data['description'] ?? ''))], JSON_UNESCAPED_UNICODE),
            'membershiplocked' => 0,
            'timeopen' => (int)($data['timeopen'] ?? 0),
            'timeclose' => (int)($data['timeclose'] ?? 0),
            'libraryitemid' => 0,
            'libraryversionid' => 0,
            'worksheetkind' => 'html',
            'worksheethash' => '',
            'migrationstatus' => 'not_required',
            'setupcomplete' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('wsg_session', $record);
        audit_logger::log($activityid, 'v12_session_created', ['name' => $record->name], $id);
        return $id;
    }

}
