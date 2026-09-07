<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_worksheetgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class save_attempt extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'attemptid' => new external_value(PARAM_INT, 'Attempt id'),
            'answersjson' => new external_value(PARAM_RAW, 'Answers JSON'),
            'version' => new external_value(PARAM_INT, 'Expected attempt version'),
        ]);
    }

    public static function execute(int $attemptid, string $answersjson, int $version): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('attemptid', 'answersjson', 'version'));
        $attempt = $DB->get_record('wsg_attempt', ['id' => $params['attemptid']], '*', MUST_EXIST);
        $session = $DB->get_record('wsg_session', ['id' => $attempt->sessionid], '*', MUST_EXIST);
        $activity = $DB->get_record('worksheetgrader', ['id' => $session->worksheetgraderid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/worksheetgrader:submit', $context);
        if (!$session->membershiplocked || !\mod_worksheetgrader\service\session_manager::is_open($session)) {
            throw new \moodle_exception('membershipbeingedited', 'mod_worksheetgrader');
        }
        $team = $DB->get_record('wsg_team', ['id' => $attempt->teamid], '*', MUST_EXIST);
        if (!\mod_worksheetgrader\service\attempt_manager::can_edit($activity, $team, $USER->id)) {
            throw new \required_capability_exception($context, 'mod/worksheetgrader:submit', 'nopermissions', '');
        }
        $answers = json_decode($params['answersjson'], true);
        if (!is_array($answers)) {
            throw new \invalid_parameter_exception('Invalid answers JSON');
        }
        try {
            $saved = \mod_worksheetgrader\service\attempt_manager::save(
                $attempt, $answers, (int)$USER->id, $params['version']
            );
        } catch (\moodle_exception $exception) {
            if ($exception->errorcode !== 'attemptconflict' || ($session->worksheetkind ?? '') !== 'native') {
                throw $exception;
            }
            $current = $DB->get_record('wsg_attempt', ['id' => $params['attemptid']], '*', MUST_EXIST);
            return [
                'ok' => false,
                'conflict' => true,
                'version' => (int)$current->version,
                'timemodified' => (int)$current->timemodified,
            ];
        }
        return [
            'ok' => true,
            'conflict' => false,
            'version' => (int)$saved->version,
            'timemodified' => (int)$saved->timemodified,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok' => new external_value(PARAM_BOOL, 'Saved'),
            'conflict' => new external_value(PARAM_BOOL, 'Revision conflict'),
            'version' => new external_value(PARAM_INT, 'New version'),
            'timemodified' => new external_value(PARAM_INT, 'Saved time'),
        ]);
    }
}
