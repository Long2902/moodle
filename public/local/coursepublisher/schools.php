<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\school_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:configuretopology', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/schools.php'));
$PAGE->set_title(get_string('nav_schools', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$editid = optional_param('edit', 0, PARAM_INT);
$toggleid = optional_param('toggle', 0, PARAM_INT);
$scanregion = optional_param('scanregion', 0, PARAM_INT);
$error = null;
if ($toggleid) {
    require_sesskey();
    try {
        service::toggle('local_cp_school', $toggleid, 'school');
        redirect($PAGE->url, get_string('toggled', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$form = new school_form();
if ($form->is_cancelled()) {
    redirect($PAGE->url);
}
if ($data = $form->get_data()) {
    try {
        service::save_school($data);
        redirect($PAGE->url, get_string('saved', 'local_coursepublisher'));
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if ($editid) {
    $form->set_data($DB->get_record('local_cp_school', ['id' => $editid], '*', MUST_EXIST));
}

local_coursepublisher_output_start('schools', get_string('nav_schools', 'local_coursepublisher'), get_string('schoolssubtitle', 'local_coursepublisher'));
if ($error) {
    echo html_writer::div(s($error), 'cp-notice-error');
}
local_coursepublisher_card_start($editid ? get_string('editschool', 'local_coursepublisher') : get_string('newschool', 'local_coursepublisher'), '', 'cp-form-card');
$form->display();
local_coursepublisher_card_end();

$sql = "SELECT s.*, r.name AS regionname, r.code AS regioncode, r.categoryid AS regioncategoryid
          FROM {local_cp_school} s
          JOIN {local_cp_region} r ON r.id = s.regionid
      ORDER BY r.name, s.name";
$records = $DB->get_records_sql($sql);
local_coursepublisher_card_start(get_string('schoollist', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($records)));
if (!$records) {
    echo html_writer::div(get_string('schoolempty', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('region', 'local_coursepublisher'),
        get_string('schoolcode', 'local_coursepublisher'),
        get_string('schoolname', 'local_coursepublisher'),
        get_string('category', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('actions', 'local_coursepublisher'),
    ];
    foreach ($records as $record) {
        $topologyok = $record->categoryid && $record->regioncategoryid &&
            service::category_is_descendant((int)$record->categoryid, (int)$record->regioncategoryid);
        $status = $record->enabled ? local_coursepublisher_badge($topologyok ? 'enabled' : 'error', $topologyok ?
            get_string('enabledstatus', 'local_coursepublisher') : get_string('invalidtopology', 'local_coursepublisher')) :
            local_coursepublisher_badge('disabled');
        $table->data[] = [
            format_string($record->regionname),
            html_writer::span(s($record->code), 'cp-code'),
            format_string($record->name),
            service::category_label((int)$record->categoryid),
            $status,
            local_coursepublisher_actions($PAGE->url, (int)$record->id, (bool)$record->enabled),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();

$regions = $DB->get_records('local_cp_region', ['enabled' => 1], 'name ASC');
if ($regions) {
    $buttons = '';
    foreach ($regions as $region) {
        $buttons .= html_writer::link(
            new moodle_url($PAGE->url, ['scanregion' => $region->id]),
            s($region->name),
            ['class' => 'btn btn-outline-primary btn-sm me-2 mb-2']
        );
    }
    local_coursepublisher_card_start(get_string('scanregion', 'local_coursepublisher'), get_string('scanregionhelp', 'local_coursepublisher'));
    echo $buttons;

    if ($scanregion) {
        $region = $DB->get_record('local_cp_region', ['id' => $scanregion], '*', MUST_EXIST);
        echo html_writer::tag('h4', get_string('candidatecategories', 'local_coursepublisher') . ': ' . format_string($region->name), ['class' => 'mt-3']);
        if (empty($region->categoryid)) {
            echo html_writer::div(get_string('regioncategorymissing', 'local_coursepublisher'), 'cp-notice-warning');
        } else {
            $root = $DB->get_record('course_categories', ['id' => $region->categoryid], 'id,path', MUST_EXIST);
            $pathprefix = $root->path . '/%';
            $sql = "SELECT id,name,idnumber,path FROM {course_categories} WHERE " . $DB->sql_like('path', ':path', false) . " ORDER BY path";
            $candidates = $DB->get_records_sql($sql, ['path' => $pathprefix]);
            $registeredids = $DB->get_fieldset_select('local_cp_school', 'categoryid', 'categoryid <> 0');
            $ctable = new html_table();
            $ctable->attributes['class'] = 'generaltable cp-table';
            $ctable->head = [
                get_string('categoryid', 'local_coursepublisher'),
                get_string('categorypath', 'local_coursepublisher'),
                get_string('idnumber', 'local_coursepublisher'),
                get_string('status', 'local_coursepublisher'),
            ];
            foreach ($candidates as $candidate) {
                $registered = in_array($candidate->id, $registeredids);
                $ctable->data[] = [
                    '#' . $candidate->id,
                    service::category_label((int)$candidate->id),
                    s($candidate->idnumber),
                    local_coursepublisher_badge($registered ? 'enabled' : 'info', $registered ?
                        get_string('registered', 'local_coursepublisher') : get_string('unregistered', 'local_coursepublisher')),
                ];
            }
            echo html_writer::div(html_writer::table($ctable), 'cp-table-wrap');
        }
    }
    local_coursepublisher_card_end();
}
local_coursepublisher_output_end();
