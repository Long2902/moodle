<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

/** Manage one shared team attempt. */
class attempt_manager {
    public static function get_or_create(\stdClass $session, \stdClass $team, int $userid): \stdClass {
        global $DB;
        $attempt = $DB->get_record('wsg_attempt', ['teamid' => $team->id, 'attemptnumber' => 1]);
        if ($attempt) {
            return $attempt;
        }
        $now = time();
        $initialanswers = '{}';
        if (($session->worksheetkind ?? '') === 'native') {
            $metadata = json_decode((string)($session->metadatajson ?? '{}'), true);
            $initialanswers = is_array($metadata) ? (string)($metadata['nativejson'] ?? '') : '';
            if ($initialanswers === '') {
                throw new \moodle_exception('Native worksheet snapshot is missing');
            }
            \local_digieranative\document\validator::validate_json($initialanswers);
        }
        $attempt = (object)[
            'sessionid' => $session->id,
            'teamid' => $team->id,
            'attemptnumber' => 1,
            'status' => 'inprogress',
            'submitteruserid' => 0,
            'answersjson' => $initialanswers,
            'submissionhtml' => '',
            'membersnapshot' => '[]',
            'groupgrade' => null,
            'groupfeedback' => '',
            'gradedby' => 0,
            'timestarted' => $now,
            'timemodified' => $now,
            'timesubmitted' => 0,
            'timegraded' => 0,
            'version' => 1,
        ];
        $attempt->id = $DB->insert_record('wsg_attempt', $attempt);

        if (($session->worksheetkind ?? '') === 'native' && !empty($session->libraryversionid)) {
            $activity = $DB->get_record('worksheetgrader', ['id' => $session->worksheetgraderid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
            native_asset_service::clone_library_assets(
                \context_module::instance($cm->id),
                (int)$attempt->id,
                (int)$session->libraryversionid
            );
        }

        audit_logger::log(
            (int)$session->worksheetgraderid,
            'attempt_started',
            [],
            (int)$session->id,
            (int)$team->id,
            (int)$attempt->id,
            $userid
        );
        return $attempt;
    }

    public static function can_edit(\stdClass $activity, \stdClass $team, int $userid): bool {
        if ($activity->representative === 'fixed') {
            return (int)$team->representativeuserid === $userid;
        }
        return in_array($userid, team_manager::get_member_ids((int)$team->id), true);
    }

    /** Save text/checkbox/image metadata while preserving fields omitted by autosave. */
    public static function save(\stdClass $attempt, array $answers, int $userid, int $expectedversion = 0): \stdClass {
        global $DB;
        $factory = \core\lock\lock_config::get_lock_factory('mod_worksheetgrader');
        $lock = $factory->get_lock('attempt-' . (int)$attempt->id, 10);
        if (!$lock) {
            throw new \moodle_exception('attemptlocktimeout', 'mod_worksheetgrader');
        }
        try {
            $current = $DB->get_record('wsg_attempt', ['id' => $attempt->id], '*', MUST_EXIST);
            if ($current->status !== 'inprogress') {
                throw new \moodle_exception('attemptnoteditable', 'mod_worksheetgrader');
            }
            if ($expectedversion && (int)$current->version !== $expectedversion) {
                throw new \moodle_exception('attemptconflict', 'mod_worksheetgrader');
            }
            $session = $DB->get_record('wsg_session', ['id' => $current->sessionid], '*', MUST_EXIST);
            if (($session->worksheetkind ?? '') === 'native') {
                $nativejson = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                \local_digieranative\document\validator::validate_json($nativejson);
                $current->answersjson = $nativejson;
            } else {
                $clean = json_decode($current->answersjson ?: '{}', true) ?: [];
                foreach ($answers as $code => $value) {
                    if (!preg_match('/^[CSBLU]\d+$/', (string)$code)) {
                        continue;
                    }
                    $clean[$code] = clean_param((string)$value, PARAM_RAW);
                }
                $current->answersjson = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $current->timemodified = time();
            $current->version = (int)$current->version + 1;
            $DB->update_record('wsg_attempt', $current);
            return $current;
        } finally {
            $lock->release();
        }
    }

    public static function submit(\stdClass $attempt, \stdClass $session, \stdClass $activity,
            \stdClass $cm, int $userid): void {
        global $DB;
        $factory = \core\lock\lock_config::get_lock_factory('mod_worksheetgrader');
        $lock = $factory->get_lock('attempt-' . (int)$attempt->id, 10);
        if (!$lock) {
            throw new \moodle_exception('attemptlocktimeout', 'mod_worksheetgrader');
        }
        try {
            $attempt = $DB->get_record('wsg_attempt', ['id' => $attempt->id], '*', MUST_EXIST);
            if ($attempt->status !== 'inprogress') {
                throw new \moodle_exception('attemptalreadysubmitted', 'mod_worksheetgrader');
            }
            if (!$session->membershiplocked) {
                throw new \moodle_exception('membershipbeingedited', 'mod_worksheetgrader');
            }
            $memberids = team_manager::get_member_ids((int)$attempt->teamid);
            if (!in_array($userid, $memberids, true)) {
                throw new \required_capability_exception(
                    \context_module::instance($cm->id), 'mod/worksheetgrader:submit', 'nopermissions', ''
                );
            }
            $answers = json_decode($attempt->answersjson ?: '{}', true) ?: [];
            $attempt->status = 'submitted';
            $attempt->submitteruserid = $userid;
            $attempt->membersnapshot = json_encode($memberids);
            if (($session->worksheetkind ?? '') === 'native') {
                $nativejson = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                \local_digieranative\document\validator::validate_json($nativejson);
                $attempt->answersjson = $nativejson;
                $context = \context_module::instance($cm->id);
                $attempt->submissionhtml = \local_digieranative\document\renderer::render_json(
                    $nativejson,
                    'submission',
                    native_asset_service::asset_urls($context, (int)$attempt->id)
                );
            } else {
                $attempt->submissionhtml = \worksheetgrader_build_submission_html(
                    $session->contenthtml ?: $activity->contenthtml,
                    $answers,
                    [
                        'contextid' => \context_module::instance($cm->id)->id,
                        'attemptid' => (int)$attempt->id,
                    ]
                );
            }
            $attempt->timesubmitted = time();
            $attempt->timemodified = $attempt->timesubmitted;
            $attempt->version++;
            $DB->update_record('wsg_attempt', $attempt);
            audit_logger::log(
                (int)$activity->id,
                'attempt_submitted',
                ['members' => $memberids],
                (int)$session->id,
                (int)$attempt->teamid,
                (int)$attempt->id,
                $userid
            );
            if ($activity->completiononsubmit) {
                \worksheetgrader_update_completion($cm, $memberids);
            }
        } finally {
            $lock->release();
        }
    }
}
