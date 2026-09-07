<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Autoloadable audit logger.
 *
 * Service and external AJAX classes are loaded without locallib.php, therefore
 * logging must not depend on a global helper being included beforehand.
 */
class audit_logger {
    public static function log(int $activityid, string $action, array $details = [], int $sessionid = 0,
            int $teamid = 0, int $attemptid = 0, int $actorid = 0): void {
        global $DB, $USER;
        $record = (object)[
            'worksheetgraderid' => $activityid,
            'sessionid' => $sessionid,
            'teamid' => $teamid,
            'attemptid' => $attemptid,
            'actorid' => $actorid ?: (int)($USER->id ?? 0),
            'action' => substr(clean_param($action, PARAM_ALPHANUMEXT), 0, 50),
            'detailsjson' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timecreated' => time(),
        ];
        $DB->insert_record('wsg_log', $record);
    }
}
