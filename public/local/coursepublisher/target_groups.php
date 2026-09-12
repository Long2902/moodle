<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\target_group_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:configureprograms', $context);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/target_groups.php'));
$PAGE->set_title(get_string('nav_targetgroups', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$editid = optional_param('edit', 0, PARAM_INT);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$error = null;
if ($toggleid) {
    require_sesskey();
    try {
        service::toggle('local_cp_target_group', $toggleid, 'target_group');
        redirect($PAGE->url, get_string('toggled', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
$form = new target_group_form();
if ($form->is_cancelled()) redirect($PAGE->url);
if ($data = $form->get_data()) {
    try {
        service::save_target_group($data);
        redirect($PAGE->url, get_string('saved', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($editid) $form->set_data($DB->get_record('local_cp_target_group', ['id' => $editid], '*', MUST_EXIST));

local_coursepublisher_output_start('targetgroups', get_string('nav_targetgroups', 'local_coursepublisher'), get_string('targetgroupssubtitle', 'local_coursepublisher'));
if ($error) echo html_writer::div(s($error), 'cp-notice-error');
local_coursepublisher_card_start($editid ? get_string('edittargetgroup', 'local_coursepublisher') : get_string('newtargetgroup', 'local_coursepublisher'), get_string('targetgrouphint', 'local_coursepublisher'), 'cp-form-card');
$form->display();
local_coursepublisher_card_end();
$sql = "SELECT g.*, p.name AS programname, p.code AS programcode,
               m.courseid AS mastercourseid, c.fullname AS mastercoursename, c.shortname AS mastershortname
          FROM {local_cp_target_group} g
          JOIN {local_cp_program} p ON p.id = g.programid
     LEFT JOIN {local_cp_master} m ON m.targetgroupid = g.id AND m.enabled = 1
     LEFT JOIN {course} c ON c.id = m.courseid
      ORDER BY p.name, g.sortorder, g.name";
$records = $DB->get_records_sql($sql);
local_coursepublisher_card_start(get_string('targetgrouplist', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($records)));
if (!$records) {
    echo html_writer::div(get_string('targetgroupempty', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('program', 'local_coursepublisher'),
        get_string('targetgroupkey', 'local_coursepublisher'),
        get_string('targetgroupname', 'local_coursepublisher'),
        get_string('masterconfigured', 'local_coursepublisher'),
        get_string('sortorder', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('actions', 'local_coursepublisher'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            format_string($record->programname) . ' [' . s($record->programcode) . ']',
            html_writer::span(s($record->groupkey), 'cp-code'),
            format_string($record->name),
            !empty($record->mastercourseid)
                ? format_string($record->mastercoursename) . ' [' . s($record->mastershortname) . '] (#' . (int)$record->mastercourseid . ')'
                : get_string('notconfigured', 'local_coursepublisher'),
            (int)$record->sortorder,
            local_coursepublisher_badge($record->enabled ? 'enabled' : 'disabled'),
            local_coursepublisher_actions($PAGE->url, (int)$record->id, (bool)$record->enabled),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();
local_coursepublisher_output_end();
