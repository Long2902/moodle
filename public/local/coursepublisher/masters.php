<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\master_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:configureprograms', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/masters.php'));
$PAGE->set_title(get_string('nav_masters', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$editid = optional_param('edit', 0, PARAM_INT);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$error = null;
if ($toggleid) {
    require_sesskey();
    try {
        service::toggle('local_cp_master', $toggleid, 'master');
        redirect($PAGE->url, get_string('toggled', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$form = new master_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    try {
        service::save_master($data);
        redirect($PAGE->url, get_string('saved', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($editid) {
    $form->set_data($DB->get_record('local_cp_master', ['id' => $editid], '*', MUST_EXIST));
}

local_coursepublisher_output_start('masters', get_string('nav_masters', 'local_coursepublisher'), get_string('masterssubtitle', 'local_coursepublisher'));
if ($error) {
    echo html_writer::div(s($error), 'cp-notice-error');
}
local_coursepublisher_card_start($editid ? get_string('editmaster', 'local_coursepublisher') : get_string('newmaster', 'local_coursepublisher'), '', 'cp-form-card');
$form->display();
local_coursepublisher_card_end();

$sql = "SELECT m.*, p.code AS programcode, p.name AS programname, c.fullname AS coursename, c.shortname, c.category
          FROM {local_cp_master} m
          JOIN {local_cp_program} p ON p.id = m.programid
     LEFT JOIN {course} c ON c.id = m.courseid
      ORDER BY p.name, m.gradekey";
$records = $DB->get_records_sql($sql);
local_coursepublisher_card_start(get_string('masterlist', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($records)));
if (!$records) {
    echo html_writer::div(get_string('masterempty', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('program', 'local_coursepublisher'),
        get_string('targetgroupgrade', 'local_coursepublisher'),
        get_string('mastercourse', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('actions', 'local_coursepublisher'),
    ];
    foreach ($records as $record) {
        $courselabel = $record->coursename ? format_string($record->coursename) . ' [' . s($record->shortname) . '] (#' . $record->courseid . ')' :
            local_coursepublisher_badge('error', get_string('missingcourse', 'local_coursepublisher'));
        $table->data[] = [
            format_string($record->programname) . ' [' . s($record->programcode) . ']',
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
