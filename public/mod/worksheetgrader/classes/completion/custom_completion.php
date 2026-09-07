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

namespace mod_worksheetgrader\completion;

class custom_completion extends \core_completion\activity_custom_completion {
    public function get_state(string $rule): int {
        global $DB;
        $activity = $DB->get_record('worksheetgrader', ['id' => $this->cm->instance], '*', MUST_EXIST);
        if ($rule === 'completiononsubmit') {
            $sql = "SELECT 1 FROM {wsg_attempt} a JOIN {wsg_team} t ON t.id=a.teamid JOIN {wsg_member} m ON m.teamid=t.id
                     JOIN {wsg_session} s ON s.id=a.sessionid WHERE s.worksheetgraderid=:aid AND m.userid=:uid AND a.status IN ('submitted','graded')";
            return $DB->record_exists_sql($sql, ['aid' => $activity->id, 'uid' => $this->userid]) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        if ($rule === 'completionongrade') {
            $sql = "SELECT 1 FROM {wsg_grade} g JOIN {wsg_attempt} a ON a.id=g.attemptid JOIN {wsg_session} s ON s.id=a.sessionid
                     WHERE s.worksheetgraderid=:aid AND g.userid=:uid AND g.published=1";
            return $DB->record_exists_sql($sql, ['aid' => $activity->id, 'uid' => $this->userid]) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }
        throw new \moodle_exception('invalidparameter');
    }
    public static function get_defined_custom_rules(): array { return ['completiononsubmit', 'completionongrade']; }
    public function get_custom_rule_descriptions(): array {
        return ['completiononsubmit' => get_string('completiononsubmit', 'mod_worksheetgrader'), 'completionongrade' => get_string('completionongrade', 'mod_worksheetgrader')];
    }
    public function get_sort_order(): array { return ['completionview', 'completiononsubmit', 'completionongrade', 'completionusegrade']; }
}
