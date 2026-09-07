<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

/** Manage temporary team layouts for one session. */
class team_manager {
    public static function get_layout(int $sessionid): array {
        global $DB;
        $result = [];
        foreach ($DB->get_records('wsg_team', ['sessionid' => $sessionid], 'id') as $team) {
            $members = $DB->get_records('wsg_member', ['teamid' => $team->id, 'status' => 'active'], 'id');
            $result[] = [
                'id' => (int)$team->id,
                'name' => $team->name,
                'representativeuserid' => (int)$team->representativeuserid,
                'members' => array_values(array_map(static fn($member) => (int)$member->userid, $members)),
            ];
        }
        return $result;
    }

    /**
     * Save a layout without recreating team IDs unnecessarily.
     *
     * This is important after a session has opened: in-progress attempts remain attached to
     * their original team record while students can be moved to another team. Submitted or
     * graded attempts keep their member set frozen and cannot be rewritten accidentally.
     */
    public static function replace_layout(int $sessionid, array $layout, int $actorid): void {
        global $DB;
        $session = $DB->get_record('wsg_session', ['id' => $sessionid], '*', MUST_EXIST);
        if ($session->status === 'closed' || $session->membershiplocked) {
            throw new \moodle_exception('teamslocked', 'mod_worksheetgrader');
        }

        $existingteams = $DB->get_records('wsg_team', ['sessionid' => $sessionid], 'id');
        $existingbyid = [];
        foreach ($existingteams as $team) {
            $existingbyid[(int)$team->id] = $team;
        }

        $seenusers = [];
        $clean = [];
        foreach ($layout as $index => $rawteam) {
            $teamid = (int)($rawteam['id'] ?? 0);
            if ($teamid && !isset($existingbyid[$teamid])) {
                throw new \moodle_exception('invalidteamid', 'mod_worksheetgrader');
            }
            $name = trim(clean_param($rawteam['name'] ?? '', PARAM_TEXT));
            if ($name === '') {
                $name = 'Nhóm ' . ($index + 1);
            }
            $members = [];
            foreach (($rawteam['members'] ?? []) as $userid) {
                $userid = (int)$userid;
                if ($userid <= 0) {
                    continue;
                }
                if (isset($seenusers[$userid])) {
                    throw new \moodle_exception('duplicateuserteam', 'mod_worksheetgrader', '', $userid);
                }
                $seenusers[$userid] = true;
                $members[] = $userid;
            }
            if (!$members && !$teamid) {
                continue;
            }
            $representative = (int)($rawteam['representativeuserid'] ?? 0);
            if (!in_array($representative, $members, true)) {
                $representative = $members[0] ?? 0;
            }
            $clean[] = [
                'id' => $teamid,
                'name' => $name,
                'members' => $members,
                'representativeuserid' => $representative,
            ];
        }

        // A submitted/graded attempt is an immutable historical snapshot. Do not let a team
        // layout save silently change who receives that submission and grade.
        foreach ($clean as $item) {
            if (!$item['id']) {
                continue;
            }
            $attempt = $DB->get_record('wsg_attempt', ['teamid' => $item['id'], 'attemptnumber' => 1]);
            if (!$attempt || $attempt->status === 'inprogress') {
                continue;
            }
            $oldmembers = self::get_member_ids($item['id']);
            $newmembers = array_values(array_map('intval', $item['members']));
            sort($oldmembers);
            sort($newmembers);
            if ($oldmembers !== $newmembers) {
                throw new \moodle_exception('cannotmovesubmittedteam', 'mod_worksheetgrader');
            }
        }

        $transaction = $DB->start_delegated_transaction();
        $now = time();
        $keptids = [];
        $changes = [];

        foreach ($clean as $item) {
            if ($item['id']) {
                $team = $existingbyid[$item['id']];
                $oldmembers = self::get_member_ids((int)$team->id);
                $team->name = $item['name'];
                $team->representativeuserid = $item['representativeuserid'];
                $team->timemodified = $now;
                $DB->update_record('wsg_team', $team);
                $teamid = (int)$team->id;
                $keptids[$teamid] = true;
                self::sync_members($teamid, $item['members'], $item['representativeuserid'], $actorid, $now);
                $changes[] = [
                    'teamid' => $teamid,
                    'before' => $oldmembers,
                    'after' => array_values($item['members']),
                ];
            } else {
                $teamid = $DB->insert_record('wsg_team', (object)[
                    'sessionid' => $sessionid,
                    'name' => $item['name'],
                    'representativeuserid' => $item['representativeuserid'],
                    'sourcegroupid' => 0,
                    'locked' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                $keptids[(int)$teamid] = true;
                self::sync_members((int)$teamid, $item['members'], $item['representativeuserid'], $actorid, $now);
                $changes[] = ['teamid' => (int)$teamid, 'before' => [], 'after' => array_values($item['members'])];
            }
        }

        foreach ($existingbyid as $teamid => $team) {
            if (isset($keptids[$teamid])) {
                continue;
            }
            if ($DB->record_exists('wsg_attempt', ['teamid' => $teamid])) {
                throw new \moodle_exception('cannotdeleteteamwithattempt', 'mod_worksheetgrader');
            }
            $DB->delete_records('wsg_member', ['teamid' => $teamid]);
            $DB->delete_records('wsg_team', ['id' => $teamid]);
        }

        $transaction->allow_commit();
        audit_logger::log(
            (int)$session->worksheetgraderid,
            'team_layout_saved',
            ['teams' => count($clean), 'members' => count($seenusers), 'changes' => $changes],
            $sessionid,
            0,
            0,
            $actorid
        );
    }

    private static function sync_members(int $teamid, array $memberids, int $representativeid,
            int $actorid, int $now): void {
        global $DB;
        $existing = $DB->get_records('wsg_member', ['teamid' => $teamid]);
        $existingbyuser = [];
        foreach ($existing as $record) {
            $existingbyuser[(int)$record->userid] = $record;
        }
        $wanted = array_fill_keys(array_map('intval', $memberids), true);
        foreach ($existingbyuser as $userid => $record) {
            if (!isset($wanted[$userid])) {
                $DB->delete_records('wsg_member', ['id' => $record->id]);
            }
        }
        foreach (array_keys($wanted) as $userid) {
            $role = $userid === $representativeid ? 'representative' : 'member';
            if (isset($existingbyuser[$userid])) {
                $record = $existingbyuser[$userid];
                $record->role = $role;
                $record->status = 'active';
                $DB->update_record('wsg_member', $record);
            } else {
                $DB->insert_record('wsg_member', (object)[
                    'teamid' => $teamid,
                    'userid' => $userid,
                    'role' => $role,
                    'status' => 'active',
                    'addedby' => $actorid,
                    'timecreated' => $now,
                ]);
            }
        }
    }

    public static function random_layout(array $users, int $teamsize): array {
        $ids = array_values(array_map(static fn($user) => (int)(is_object($user) ? $user->id : $user['id']), $users));
        shuffle($ids);
        $teamsize = max(1, $teamsize);
        $layout = [];
        foreach (array_chunk($ids, $teamsize) as $index => $members) {
            $layout[] = [
                'name' => 'Nhóm ' . ($index + 1),
                'members' => $members,
                'representativeuserid' => $members[0] ?? 0,
            ];
        }
        return $layout;
    }


    /** Split enrolled students into an exact number of balanced random teams. */
    public static function random_layout_by_count(array $users, int $teamcount): array {
        $ids = array_values(array_map(static fn($user) => (int)(is_object($user) ? $user->id : $user['id']), $users));
        shuffle($ids);
        if (!$ids) {
            return [];
        }
        $teamcount = min(count($ids), max(1, $teamcount));
        $buckets = array_fill(0, $teamcount, []);
        foreach ($ids as $index => $userid) {
            $buckets[$index % $teamcount][] = $userid;
        }
        $layout = [];
        foreach ($buckets as $index => $members) {
            if (!$members) {
                continue;
            }
            $layout[] = [
                'name' => 'Nhóm ' . ($index + 1),
                'members' => $members,
                'representativeuserid' => $members[0],
            ];
        }
        return $layout;
    }

    /**
     * Create a private one-person team only when an individual student first opens a session.
     * The lock makes concurrent page loads idempotent.
     */
    public static function get_or_create_individual_team(\stdClass $session, \stdClass $user,
            int $actorid): \stdClass {
        global $DB;
        if (($session->teammode ?? '') !== 'individual') {
            throw new \moodle_exception('invalidindividualsession', 'mod_worksheetgrader');
        }
        $existing = self::get_user_team((int)$session->id, (int)$user->id);
        if ($existing) {
            return $existing;
        }
        $factory = \core\lock\lock_config::get_lock_factory('mod_worksheetgrader');
        $lock = $factory->get_lock('individual-team-' . (int)$session->id . '-' . (int)$user->id, 10);
        if (!$lock) {
            throw new \moodle_exception('attemptlocktimeout', 'mod_worksheetgrader');
        }
        try {
            $existing = self::get_user_team((int)$session->id, (int)$user->id);
            if ($existing) {
                return $existing;
            }
            $now = time();
            $team = (object)[
                'sessionid' => (int)$session->id,
                'name' => 'Cá nhân - ' . fullname($user) . ' #' . (int)$user->id,
                'representativeuserid' => (int)$user->id,
                'sourcegroupid' => 0,
                'locked' => 1,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $team->id = $DB->insert_record('wsg_team', $team);
            $DB->insert_record('wsg_member', (object)[
                'teamid' => (int)$team->id,
                'userid' => (int)$user->id,
                'role' => 'representative',
                'status' => 'active',
                'addedby' => $actorid,
                'timecreated' => $now,
            ]);
            audit_logger::log(
                (int)$session->worksheetgraderid,
                'individual_workspace_created',
                ['userid' => (int)$user->id],
                (int)$session->id,
                (int)$team->id,
                0,
                $actorid
            );
            return $team;
        } finally {
            $lock->release();
        }
    }

    public static function import_moodle_groups(int $courseid, int $sessionid, int $actorid): void {
        $groups = groups_get_all_groups($courseid, 0, 0, 'g.id,g.name');
        $layout = [];
        foreach ($groups as $group) {
            $members = array_keys(groups_get_members($group->id, 'u.id'));
            if ($members) {
                $layout[] = [
                    'name' => $group->name,
                    'members' => array_map('intval', $members),
                    'representativeuserid' => (int)reset($members),
                ];
            }
        }
        self::replace_layout($sessionid, $layout, $actorid);
    }

    public static function get_user_team(int $sessionid, int $userid): ?\stdClass {
        global $DB;
        $sql = "SELECT t.*
                  FROM {wsg_team} t
                  JOIN {wsg_member} m ON m.teamid = t.id
                 WHERE t.sessionid = :sessionid
                   AND m.userid = :userid
                   AND m.status = :status";
        return $DB->get_record_sql($sql, [
            'sessionid' => $sessionid,
            'userid' => $userid,
            'status' => 'active',
        ]) ?: null;
    }

    public static function get_member_ids(int $teamid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            'wsg_member', 'userid', 'teamid = ? AND status = ?', [$teamid, 'active']
        ));
    }
}
