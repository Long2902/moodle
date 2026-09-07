<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

/** Import and export one-row-per-student team layouts as UTF-8 CSV. */
class team_csv_manager {
    public const MAX_BYTES = 2097152;

    public static function export_csv(int $sessionid): string {
        global $DB;
        $rows = [[
            'team_id', 'team_name', 'representative', 'user_id',
            'username', 'email', 'idnumber', 'full_name',
        ]];
        foreach ($DB->get_records('wsg_team', ['sessionid' => $sessionid], 'id') as $team) {
            $members = $DB->get_records('wsg_member', ['teamid' => $team->id, 'status' => 'active'], 'id');
            foreach ($members as $member) {
                $user = $DB->get_record('user', ['id' => $member->userid],
                    'id,username,email,idnumber,firstname,lastname', MUST_EXIST);
                $rows[] = [
                    (int)$team->id,
                    $team->name,
                    (int)$team->representativeuserid === (int)$user->id ? 1 : 0,
                    (int)$user->id,
                    $user->username,
                    $user->email,
                    $user->idnumber,
                    fullname($user),
                ];
            }
        }
        $handle = fopen('php://temp', 'w+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        return "\xEF\xBB\xBF" . $csv;
    }

    /** Parse an uploaded CSV and replace the current editable layout. */
    public static function import_csv(int $sessionid, string $filepath, array $eligibleusers,
            int $actorid): array {
        global $DB;
        if (!is_readable($filepath) || filesize($filepath) <= 0 || filesize($filepath) > self::MAX_BYTES) {
            throw new \moodle_exception('csvinvalidfile', 'mod_worksheetgrader');
        }
        $session = $DB->get_record('wsg_session', ['id' => $sessionid], '*', MUST_EXIST);
        if (($session->teammode ?? '') === 'individual') {
            throw new \moodle_exception('csvnotindividual', 'mod_worksheetgrader');
        }
        $handle = fopen($filepath, 'rb');
        $firstline = fgets($handle);
        if ($firstline === false) {
            fclose($handle);
            throw new \moodle_exception('csvinvalidfile', 'mod_worksheetgrader');
        }
        $firstline = preg_replace('/^\xEF\xBB\xBF/', '', $firstline);
        $delimiter = self::detect_delimiter($firstline);
        $headers = str_getcsv(rtrim($firstline, "\r\n"), $delimiter);
        $headers = array_map([self::class, 'normalise_header'], $headers);
        $map = array_flip($headers);
        if (!isset($map['team_name']) && !isset($map['team'])) {
            fclose($handle);
            throw new \moodle_exception('csvmissingteam', 'mod_worksheetgrader');
        }

        $lookups = self::build_user_lookups($eligibleusers);
        $teams = [];
        $seenusers = [];
        $rownumber = 1;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rownumber++;
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            $get = static function(array $aliases) use ($row, $map): string {
                foreach ($aliases as $alias) {
                    if (isset($map[$alias])) {
                        return trim((string)($row[$map[$alias]] ?? ''));
                    }
                }
                return '';
            };
            $teamname = $get(['team_name', 'team', 'group_name', 'nhom', 'ten_nhom', 'nhóm', 'tên_nhóm']);
            if ($teamname === '') {
                throw new \moodle_exception('csvrowerror', 'mod_worksheetgrader', '',
                    'Dòng ' . $rownumber . ': thiếu tên nhóm.');
            }
            $userid = self::resolve_user($get, $lookups);
            if (!$userid) {
                throw new \moodle_exception('csvrowerror', 'mod_worksheetgrader', '',
                    'Dòng ' . $rownumber . ': không nhận diện được học sinh hoặc học sinh không thuộc lớp.');
            }
            if (isset($seenusers[$userid])) {
                throw new \moodle_exception('csvrowerror', 'mod_worksheetgrader', '',
                    'Dòng ' . $rownumber . ': học sinh xuất hiện nhiều lần.');
            }
            $seenusers[$userid] = true;
            $teamid = (int)$get(['team_id', 'teamid', 'group_id']);
            $key = $teamid > 0 ? 'id:' . $teamid : 'name:' . \core_text::strtolower($teamname);
            if (!isset($teams[$key])) {
                $teams[$key] = [
                    'id' => $teamid,
                    'name' => clean_param($teamname, PARAM_TEXT),
                    'members' => [],
                    'representativeuserid' => 0,
                ];
            }
            $teams[$key]['members'][] = $userid;
            if (self::is_truthy($get(['representative', 'is_representative', 'dai_dien']))) {
                $teams[$key]['representativeuserid'] = $userid;
            }
        }
        fclose($handle);
        if (!$teams) {
            throw new \moodle_exception('csvempty', 'mod_worksheetgrader');
        }
        foreach ($teams as &$team) {
            if (!$team['representativeuserid']) {
                $team['representativeuserid'] = (int)$team['members'][0];
            }
        }
        unset($team);
        team_manager::replace_layout($sessionid, array_values($teams), $actorid);
        audit_logger::log(
            (int)$session->worksheetgraderid,
            'team_csv_imported',
            ['teams' => count($teams), 'members' => count($seenusers)],
            $sessionid,
            0,
            0,
            $actorid
        );
        return ['teams' => count($teams), 'members' => count($seenusers)];
    }

    private static function detect_delimiter(string $line): string {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);
        $delimiter = (string)array_key_first($counts);
        return reset($counts) > 0 ? $delimiter : ',';
    }

    private static function normalise_header(string $value): string {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value));
        $value = \core_text::strtolower($value);
        $value = str_replace([' ', '-', '/'], '_', $value);
        return preg_replace('/_+/', '_', $value);
    }

    private static function build_user_lookups(array $users): array {
        $lookups = ['id' => [], 'username' => [], 'email' => [], 'idnumber' => []];
        foreach ($users as $user) {
            $lookups['id'][(int)$user->id] = (int)$user->id;
            $lookups['username'][\core_text::strtolower(trim((string)$user->username))] = (int)$user->id;
            if (trim((string)$user->email) !== '') {
                $lookups['email'][\core_text::strtolower(trim((string)$user->email))] = (int)$user->id;
            }
            if (trim((string)$user->idnumber) !== '') {
                $lookups['idnumber'][\core_text::strtolower(trim((string)$user->idnumber))] = (int)$user->id;
            }
        }
        return $lookups;
    }

    private static function resolve_user(callable $get, array $lookups): int {
        $rawid = (int)$get(['user_id', 'userid', 'user']);
        if ($rawid && isset($lookups['id'][$rawid])) {
            return $rawid;
        }
        foreach ([
            'username' => ['username', 'login'],
            'email' => ['email'],
            'idnumber' => ['idnumber', 'student_id', 'ma_hoc_sinh', 'member'],
        ] as $type => $aliases) {
            $value = \core_text::strtolower($get($aliases));
            if ($value !== '' && isset($lookups[$type][$value])) {
                return (int)$lookups[$type][$value];
            }
        }
        return 0;
    }

    private static function is_truthy(string $value): bool {
        return in_array(\core_text::strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'x', 'co', 'có'], true);
    }
}
