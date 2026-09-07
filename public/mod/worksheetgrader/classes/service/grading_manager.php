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

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

class grading_manager {
    public static function save_attempt_grades(\stdClass $attempt, \stdClass $session, \stdClass $activity, \stdClass $cm,
            float $groupgrade, string $feedback, array $adjustments, bool $publish, int $graderid): void {
        global $DB;
        $groupgrade = max(0.0, min((float)$session->maxpoints, $groupgrade));
        $members = json_decode($attempt->membersnapshot ?: '[]', true) ?: team_manager::get_member_ids($attempt->teamid);
        $now = time();
        $transaction = $DB->start_delegated_transaction();
        foreach ($members as $userid) {
            $userid = (int)$userid;
            $adjustment = $activity->allowadjust ? (float)($adjustments[$userid] ?? 0) : 0.0;
            $final = max(0.0, min((float)$session->maxpoints, $groupgrade + $adjustment));
            $record = $DB->get_record('wsg_grade', ['attemptid' => $attempt->id, 'userid' => $userid]);
            if (!$record) {
                $record = (object)['attemptid' => $attempt->id, 'userid' => $userid, 'timecreated' => $now];
            }
            $record->groupgrade = $groupgrade;
            $record->adjustment = $adjustment;
            $record->finalgrade = $final;
            $record->feedback = $feedback;
            $record->published = $publish ? 1 : 0;
            $record->publishedby = $publish ? $graderid : 0;
            $record->timemodified = $now;
            if (empty($record->id)) {
                $DB->insert_record('wsg_grade', $record);
            } else {
                $DB->update_record('wsg_grade', $record);
            }
        }
        $attempt->groupgrade = $groupgrade;
        $attempt->groupfeedback = $feedback;
        $attempt->gradedby = $graderid;
        $attempt->timegraded = $now;
        $attempt->status = $publish ? 'graded' : 'submitted';
        $DB->update_record('wsg_attempt', $attempt);
        $transaction->allow_commit();
        audit_logger::log($activity->id, $publish ? 'grades_published' : 'grades_saved', ['groupgrade' => $groupgrade, 'members' => $members], $session->id, $attempt->teamid, $attempt->id, $graderid);
        if ($publish) {
            \worksheetgrader_update_grades($activity);
            if ($activity->completionongrade) {
                \worksheetgrader_update_completion($cm, $members);
            }
        }
    }

    public static function get_gradebook_grades(\stdClass $activity, int $userid = 0): array {
        global $DB;
        $params = ['activityid' => $activity->id];
        $userwhere = '';
        if ($userid) {
            $userwhere = ' AND g.userid = :userid';
            $params['userid'] = $userid;
        }
        $sql = "SELECT g.id, g.userid, g.finalgrade, g.feedback, g.timemodified, s.maxpoints, s.sessiondate, s.id AS sessionid
                  FROM {wsg_grade} g
                  JOIN {wsg_attempt} a ON a.id = g.attemptid
                  JOIN {wsg_session} s ON s.id = a.sessionid
                 WHERE s.worksheetgraderid = :activityid AND g.published = 1" . $userwhere . " ORDER BY g.userid, s.sessiondate, s.id";
        $rows = $DB->get_records_sql($sql, $params);
        $byuser = [];
        foreach ($rows as $row) {
            $byuser[$row->userid][] = $row;
        }
        $result = [];
        foreach ($byuser as $uid => $items) {
            $values = [];
            foreach ($items as $item) {
                $values[] = $item->maxpoints > 0 ? ((float)$item->finalgrade / (float)$item->maxpoints) * (float)$activity->grade : 0.0;
            }
            $grade = match ($activity->aggregation) {
                'best' => max($values),
                'latest' => end($values),
                'sum' => min((float)$activity->grade, array_sum($values)),
                default => array_sum($values) / max(1, count($values)),
            };
            $latest = end($items);
            $result[$uid] = (object)[
                'userid' => (int)$uid,
                'rawgrade' => $grade,
                'feedback' => $latest->feedback ?? '',
                'feedbackformat' => FORMAT_PLAIN,
                'datesubmitted' => (int)($latest->timemodified ?? time()),
                'dategraded' => (int)($latest->timemodified ?? time()),
            ];
        }
        return $result;
    }
}
