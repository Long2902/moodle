<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\program_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:configureprograms', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/programs.php'));
$PAGE->set_title(get_string('nav_programs', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$editid = optional_param('edit', 0, PARAM_INT);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$error = null;

if ($toggleid) {
    require_sesskey();
    try {
        service::toggle('local_cp_program', $toggleid, 'program');
        redirect($PAGE->url, get_string('toggled', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$form = new program_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    try {
        service::save_program($data);
        redirect($PAGE->url, get_string('saved', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($editid) {
    $form->set_data($DB->get_record('local_cp_program', ['id' => $editid], '*', MUST_EXIST));
}

local_coursepublisher_output_start('programs', get_string('nav_programs', 'local_coursepublisher'), get_string('programssubtitle', 'local_coursepublisher'));
if ($error) {
    echo html_writer::div(s($error), 'cp-notice-error');
}

local_coursepublisher_card_start($editid ? get_string('editprogram', 'local_coursepublisher') : get_string('newprogram', 'local_coursepublisher'), '', 'cp-form-card');
$form->display();
local_coursepublisher_card_end();

$records = $DB->get_records('local_cp_program', null, 'name ASC');
local_coursepublisher_card_start(get_string('programlist', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($records)));
if (!$records) {
    echo html_writer::div(get_string('programempty', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('id', 'local_coursepublisher'),
        get_string('programcode', 'local_coursepublisher'),
        get_string('programname', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('actions', 'local_coursepublisher'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            '#' . $record->id,
            html_writer::span(s($record->code), 'cp-code'),
            format_string($record->name),
            local_coursepublisher_badge($record->enabled ? 'enabled' : 'disabled'),
            local_coursepublisher_actions($PAGE->url, (int)$record->id, (bool)$record->enabled),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();
local_coursepublisher_output_end();
