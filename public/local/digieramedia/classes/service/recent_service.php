<?php

namespace local_digieramedia\service;

final class recent_service {
    private const ALLOWED_ACTIONS = [
        'CREATE_REFERENCE',
        'UPDATE_REFERENCE',
    ];

    public function touch(
        int $userid,
        int $mediaid,
        int $contextid,
        string $action,
        ?int $usedat = null
    ): void {
        global $DB;

        $action = strtoupper(trim($action));
        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            throw new \invalid_parameter_exception('Unsupported DIGIERA recent action.');
        }
        if ($userid <= 0 || $mediaid <= 0 || $contextid <= 0) {
            throw new \invalid_parameter_exception('Recent history identifiers must be positive.');
        }
        if (!$DB->record_exists('local_digieramedia_media', ['id' => $mediaid])) {
            throw new \invalid_parameter_exception('DIGIERA media does not exist.');
        }

        $factory = \core\lock\lock_config::get_lock_factory('local_digieramedia');
        $lock = $factory->get_lock('recent-' . $userid . '-' . $mediaid, 10);
        if (!$lock) {
            throw new \moodle_exception('locktimeout', 'local_digieramedia');
        }

        try {
            $when = $usedat ?? time();
            $record = $DB->get_record('local_digieramedia_recent', [
                'userid' => $userid,
                'mediaid' => $mediaid,
            ]);
            if ($record) {
                $record->contextid = $contextid;
                $record->lastusedat = $when;
                $record->usecount = (int)$record->usecount + 1;
                $record->lastaction = $action;
                $DB->update_record('local_digieramedia_recent', $record);
                return;
            }

            $DB->insert_record('local_digieramedia_recent', (object)[
                'userid' => $userid,
                'mediaid' => $mediaid,
                'contextid' => $contextid,
                'lastusedat' => $when,
                'usecount' => 1,
                'lastaction' => $action,
            ]);
        } finally {
            $lock->release();
        }
    }
}
