<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\preview_form;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:preview', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/preview.php'));
$PAGE->set_title(get_string('nav_preview', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$form = new preview_form();
$result = null;
$error = null;
if ($data = $form->get_data()) {
    try {
        $result = service::resolve_preview(
            (int)$data->programid,
            (string)$data->gradekey,
            (string)$data->sourcetype,
            (int)$data->sourceid,
            (int)$data->regionid,
            (int)$data->schoolid
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

local_coursepublisher_output_start('preview', get_string('nav_preview', 'local_coursepublisher'));

echo html_writer::start_div('cp-grid-2');
local_coursepublisher_card_start(get_string('previewrequest', 'local_coursepublisher'), get_string('previewrequesthelp', 'local_coursepublisher'), 'cp-form-card');
$form->display();
local_coursepublisher_card_end();

local_coursepublisher_card_start(get_string('previewresult', 'local_coursepublisher'));
if ($error) {
    echo html_writer::div(
        html_writer::tag('strong', get_string('previewblockedtitle', 'local_coursepublisher')) .
        html_writer::div(s($error), 'mt-2'),
        'cp-notice-error'
    );
    echo html_writer::div(get_string('previewblockedhelp', 'local_coursepublisher'), 'cp-muted');
} else if (!$result) {
    echo html_writer::div(
        html_writer::div('▷', 'cp-empty-icon') .
        html_writer::tag('strong', get_string('previewemptytitle', 'local_coursepublisher')) .
        html_writer::div(get_string('previewemptyhelp', 'local_coursepublisher'), 'cp-muted'),
        'cp-empty'
    );
} else {
    $mastercourse = $result['mastercourse'];
    $source = $result['source'];
    $sourcevalue = $source->displayname . ' — ' . $source->typedescription . ' #' . $source->id;

    echo html_writer::start_div('cp-preview-summary');
    $summary = [
        [get_string('program', 'local_coursepublisher'), format_string($result['program']->name) . ' [' . s($result['program']->code) . ']'],
        [get_string('mastercourse', 'local_coursepublisher'), format_string($mastercourse->fullname) . ' (#' . $mastercourse->id . ')'],
        [get_string('source', 'local_coursepublisher'), $sourcevalue],
    ];
    foreach ($summary as [$label, $value]) {
        echo html_writer::start_div('cp-summary-tile');
        echo html_writer::div($label, 'cp-summary-label');
        echo html_writer::div($value, 'cp-summary-value');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    if (!$result['targets']) {
        echo html_writer::div(
            html_writer::tag('strong', get_string('routemissing', 'local_coursepublisher')) .
            html_writer::div(get_string('routemissinghelp', 'local_coursepublisher'), 'mt-1'),
            'cp-notice-warning'
        );
    } else {
        $allready = true;
        foreach ($result['targets'] as $row) {
            if ($row->status !== 'ready') {
                $allready = false;
                break;
            }
        }
        echo html_writer::div(
            html_writer::tag('strong', $allready
                ? get_string('previewreadytitle', 'local_coursepublisher')
                : get_string('previewblockedtitle', 'local_coursepublisher')),
            $allready ? 'cp-notice-success' : 'cp-notice-error'
        );

        if ($allready && count($result['targets']) === 1 && has_capability('local/coursepublisher:publish', $context)) {
            $submitted = $data ?? null;
            if ($submitted && !empty($submitted->schoolid)) {
                $payload = [
                    'programid' => (int)$submitted->programid,
                    'gradekey' => (string)$submitted->gradekey,
                    'sourcetype' => (string)$submitted->sourcetype,
                    'sourceid' => (int)$submitted->sourceid,
                    'schoolid' => (int)$submitted->schoolid,
                    'sesskey' => sesskey(),
                ];

                echo html_writer::start_div('d-flex gap-2 flex-wrap mt-3');

                $genericactivityeligible = false;
                $genericactivitymodule = '';
                $sectionpeereligible = false;
                $subsectiontreeeligible = false;
                $copyblocked = [];
                $manifest = \local_coursepublisher\local\job_service::build_manifest(
                    (string)$submitted->sourcetype,
                    (int)$submitted->sourceid,
                    (int)$mastercourse->id
                );

                if ((string)$submitted->sourcetype === 'activity') {
                    try {
                        if (count($manifest) !== 1) {
                            throw new \moodle_exception('invalidparameter');
                        }
                        $activity = \local_coursepublisher\local\content_publisher::validate_backup_capable_activity(
                            (int)$submitted->sourceid,
                            (int)$mastercourse->id
                        );
                        if ((string)$activity['modname'] === 'subsection') {
                            throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
                        }
                        $genericactivityeligible = true;
                        $genericactivitymodule = (string)$activity['modname'];
                    } catch (\moodle_exception $e) {
                        $copyblocked[] = $e->getMessage();
                    }
                } else if ((string)$submitted->sourcetype === 'section') {
                    try {
                        $sourcesection = $DB->get_record(
                            'course_sections',
                            ['id' => (int)$submitted->sourceid, 'course' => (int)$mastercourse->id],
                            'id,course,section,component,itemid',
                            MUST_EXIST
                        );
                        \local_coursepublisher\local\content_publisher::validate_source_section_for_generic_copy(
                            (int)$submitted->sourceid,
                            (int)$mastercourse->id
                        );
                        foreach ($manifest as $row) {
                            \local_coursepublisher\local\content_publisher::validate_backup_capable_activity(
                                (int)$row['sourcecmid'],
                                (int)$mastercourse->id
                            );
                        }
                        $sectionpeereligible = true;

                        if ((string)$sourcesection->component === 'mod_subsection') {
                            \local_coursepublisher\local\content_publisher::validate_source_delegated_subsection(
                                (int)$submitted->sourceid,
                                (int)$mastercourse->id
                            );
                            $subsectiontreeeligible = true;
                        }
                    } catch (\moodle_exception $e) {
                        $copyblocked[] = $e->getMessage();
                    }
                }

                if ($genericactivityeligible) {
                    echo html_writer::start_tag('form', [
                        'method' => 'post',
                        'action' => (new moodle_url('/local/coursepublisher/publish.php'))->out(false),
                    ]);
                    foreach ($payload as $name => $value) {
                        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                    }
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'publishvariant', 'value' => 'genericactivity']);
                    echo html_writer::tag('button', get_string('creategenericactivityjob', 'local_coursepublisher', $genericactivitymodule), [
                        'type' => 'submit',
                        'class' => 'btn btn-primary',
                    ]);
                    echo html_writer::end_tag('form');
                }

                if ($sectionpeereligible) {
                    echo html_writer::start_tag('form', [
                        'method' => 'post',
                        'action' => (new moodle_url('/local/coursepublisher/publish.php'))->out(false),
                    ]);
                    foreach ($payload as $name => $value) {
                        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                    }
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'publishvariant', 'value' => 'peersection']);
                    echo html_writer::tag('button', get_string('createsectionpeerjob', 'local_coursepublisher'), [
                        'type' => 'submit',
                        'class' => 'btn btn-primary',
                    ]);
                    echo html_writer::end_tag('form');
                }

                if ($subsectiontreeeligible) {
                    echo html_writer::start_tag('form', [
                        'method' => 'post',
                        'action' => (new moodle_url('/local/coursepublisher/publish.php'))->out(false),
                    ]);
                    foreach ($payload as $name => $value) {
                        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
                    }
                    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'publishvariant', 'value' => 'subsectiontree']);
                    echo html_writer::tag('button', get_string('createsubsectiongenericjob', 'local_coursepublisher'), [
                        'type' => 'submit',
                        'class' => 'btn btn-outline-primary',
                    ]);
                    echo html_writer::end_tag('form');
                }
                echo html_writer::end_div();
                if (!$genericactivityeligible && !$sectionpeereligible && !$subsectiontreeeligible && $copyblocked) {
                    echo html_writer::div(
                        get_string('genericcopyblocked', 'local_coursepublisher', implode(' | ', array_map('s', $copyblocked))),
                        'cp-notice-warning mt-2'
                    );
                }
            } else {
                echo html_writer::div(get_string('jobselectschool', 'local_coursepublisher'), 'cp-notice-warning mt-3');
            }
        }
    }
}
local_coursepublisher_card_end();
echo html_writer::end_div();

if ($result && $result['targets']) {
    local_coursepublisher_card_start(get_string('resolvedroutes', 'local_coursepublisher'));
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('region', 'local_coursepublisher'),
        get_string('school', 'local_coursepublisher'),
        get_string('targetgroupgrade', 'local_coursepublisher'),
        get_string('targetcourse', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
        get_string('reason', 'local_coursepublisher'),
    ];
    foreach ($result['targets'] as $row) {
        $course = format_string($row->coursename);
        if ($row->courseid) {
            if ($row->shortname !== '') {
                $course .= ' [' . s($row->shortname) . ']';
            }
            $course .= ' (#' . $row->courseid . ')';
        }
        $table->data[] = [
            format_string($row->regionname) . ' [' . s($row->regioncode) . ']',
            format_string($row->schoolname) . ' [' . s($row->schoolcode) . ']',
            service::target_group_label((int)$submitted->programid, (string)$row->gradekey),
            $course,
            local_coursepublisher_badge($row->status === 'ready' ? 'ready' : 'blocked'),
            s($row->reason),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
    local_coursepublisher_card_end();
}

local_coursepublisher_card_start(get_string('sourceidguide', 'local_coursepublisher'));
$sectionexample = html_writer::tag('code', '/course/section.php?id=3251') . ' → ' .
    html_writer::tag('code', 'Source type = Section; Source ID = 3251');
$activityexample = html_writer::tag('code', '/mod/page/view.php?id=19913') . ' → ' .
    html_writer::tag('code', 'Source type = Activity; Source ID = 19913');
echo html_writer::tag('p', get_string('sectionguide', 'local_coursepublisher') . ' ' . $sectionexample);
echo html_writer::tag('p', get_string('activityguide', 'local_coursepublisher') . ' ' . $activityexample, ['class' => 'mb-0']);
local_coursepublisher_card_end();

local_coursepublisher_output_end();
