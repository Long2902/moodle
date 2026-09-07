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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_worksheetgrader_stepslib.php');

/**
 * Backup task for the Worksheet grader activity.
 *
 * @package    mod_worksheetgrader
 * @category   backup
 * @copyright  2026 Digital Era Education
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_worksheetgrader_activity_task extends backup_activity_task {
    /**
     * No activity-specific backup settings.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Add the activity structure step.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_worksheetgrader_activity_structure_step(
            'worksheetgrader_structure',
            'worksheetgrader.xml'
        ));
    }

    /**
     * Encode links to the activity index page so they survive backup/restore.
     *
     * Moodle 5.1 core activity modules build this URL pattern from
     * $CFG->wwwroot rather than from a backup-class URL constant.
     *
     * @param string $content HTML which may contain activity links.
     * @return string Content with portable backup link tokens.
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $search = '/(' . $base . '\/mod\/worksheetgrader\/index\.php\?id=)([0-9]+)/';

        return preg_replace($search, '$@WORKSHEETGRADERINDEX*$2@$', $content);
    }
}
