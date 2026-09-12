<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Seed global discovery container rules on a fresh plugin install.
 */
function xmldb_local_coursepublisher_install(): void {
    global $DB;

    $now = time();
    foreach ([
        ['Khối THPT', 'KHỐI THPT', 10],
        ['Khối Liên Cấp', 'KHỐI LIÊN CẤP', 20],
        ['Trung tâm', 'TRUNG TÂM', 30],
    ] as [$name, $pattern, $sortorder]) {
        $DB->insert_record('local_cp_discovery_container', (object)[
            'name' => $name,
            'matchtype' => 'contains',
            'pattern' => $pattern,
            'enabled' => 1,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }
}
