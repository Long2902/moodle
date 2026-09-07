<?php
// This file is part of Moodle - http://moodle.org/
defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/restore_worksheetgrader_stepslib.php');

class restore_worksheetgrader_activity_task extends restore_activity_task {
    protected function define_my_settings(): void { }
    protected function define_my_steps(): void {
        $this->add_step(new restore_worksheetgrader_activity_structure_step('worksheetgrader_structure', 'worksheetgrader.xml'));
    }
    public static function define_decode_contents(): array {
        return [new restore_decode_content('worksheetgrader', ['intro','contenthtml'], 'worksheetgrader')];
    }
    public static function define_decode_rules(): array {
        return [new restore_decode_rule('WORKSHEETGRADERINDEX', '/mod/worksheetgrader/index.php?id=$1', 'course')];
    }
}
