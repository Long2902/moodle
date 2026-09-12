<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\target_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:bindcourses', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/targets.php'));
$PAGE->set_title(get_string('nav_targets', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$editid = optional_param('edit', 0, PARAM_INT);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$error = null;
if ($toggleid) {
    require_sesskey();
    try {
        service::toggle('local_cp_target', $toggleid, 'target');
        redirect($PAGE->url, get_string('toggled', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$form = new target_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    try {
        service::save_target($data);
        redirect($PAGE->url, get_string('saved', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($editid) {
    $form->set_data($DB->get_record('local_cp_target', ['id' => $editid], '*', MUST_EXIST));
}

local_coursepublisher_output_start('targets', get_string('nav_targets', 'local_coursepublisher'), get_string('targetssubtitle', 'local_coursepublisher'));
if ($error) {
    echo html_writer::div(s($error), 'cp-notice-error');
}
local_coursepublisher_card_start($editid ? get_string('edittarget', 'local_coursepublisher') : get_string('newtarget', 'local_coursepublisher'), get_string('targetselectorhint', 'local_coursepublisher'), 'cp-form-card');
$form->display();
local_coursepublisher_card_end();

$sql = "SELECT t.*, p.name AS programname, p.code AS programcode, s.name AS schoolname, s.code AS schoolcode,
               r.name AS regionname, c.fullname AS coursename, c.shortname, c.category
          FROM {local_cp_target} t
          JOIN {local_cp_program} p ON p.id = t.programid
          JOIN {local_cp_school} s ON s.id = t.schoolid
          JOIN {local_cp_region} r ON r.id = s.regionid
     LEFT JOIN {course} c ON c.id = t.courseid
      ORDER BY p.name, r.name, s.name, t.gradekey";
$records = $DB->get_records_sql($sql);
local_coursepublisher_card_start(get_string('targetlist', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($records)));
if (!$records) {
    echo html_writer::div(get_string('targetempty', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('program', 'local_coursepublisher'),
        get_string('region', 'local_coursepublisher'),
        get_string('school', 'local_coursepublisher'),
        get_string('targetgroupgrade', 'local_coursepublisher'),
        get_string('targetcourse', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('actions', 'local_coursepublisher'),
    ];
    foreach ($records as $record) {
        $courselabel = $record->coursename ? format_string($record->coursename) . ' [' . s($record->shortname) . '] (#' . $record->courseid . ')' :
            local_coursepublisher_badge('error', get_string('missingcourse', 'local_coursepublisher'));
        $table->data[] = [
            format_string($record->programname),
            format_string($record->regionname),
            format_string($record->schoolname) . ' [' . s($record->schoolcode) . ']',
            service::target_group_label((int)$record->programid, (string)$record->gradekey),
            $courselabel,
            local_coursepublisher_badge($record->enabled ? 'enabled' : 'disabled'),
            local_coursepublisher_actions($PAGE->url, (int)$record->id, (bool)$record->enabled),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();
local_coursepublisher_output_end();
