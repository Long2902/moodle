<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\form\batch_form;
use local_coursepublisher\local\batch_service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:preview', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/batch.php'));
$PAGE->set_title(get_string('newbatch', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

/** Build the service request from scalar POST/form values. */
function local_coursepublisher_batch_request(array $values, array $targetids): array {
    return [
        'programid' => (int)($values['programid'] ?? 0),
        'gradekey' => (string)($values['gradekey'] ?? ''),
        'sourcetype' => (string)($values['sourcetype'] ?? ''),
        'sourceid' => (int)($values['sourceid'] ?? 0),
        'publishmode' => (string)($values['publishmode'] ?? ''),
        'placementmode' => (string)($values['placementmode'] ?? 'auto'),
        'targetbindids' => $targetids,
        'manualplacement' => [
            'sectionname' => (string)($values['manual_section_name'] ?? ''),
            'position' => (string)($values['manual_position'] ?? ''),
            'anchorname' => (string)($values['manual_anchor_name'] ?? ''),
            'anchormodname' => (string)($values['manual_anchor_modname'] ?? ''),
        ],
    ];
}

/** Read the immutable source/placement payload from POST. */
function local_coursepublisher_batch_post_values(): array {
    return [
        'programid' => required_param('programid', PARAM_INT),
        'gradekey' => required_param('gradekey', PARAM_ALPHANUMEXT),
        'sourcetype' => required_param('sourcetype', PARAM_ALPHA),
        'sourceid' => required_param('sourceid', PARAM_INT),
        'publishmode' => required_param('publishmode', PARAM_ALPHANUMEXT),
        'placementmode' => required_param('placementmode', PARAM_ALPHA),
        'manual_section_name' => optional_param('manual_section_name', '', PARAM_TEXT),
        'manual_position' => optional_param('manual_position', '', PARAM_ALPHA),
        'manual_anchor_name' => optional_param('manual_anchor_name', '', PARAM_TEXT),
        'manual_anchor_modname' => optional_param('manual_anchor_modname', '', PARAM_ALPHANUMEXT),
    ];
}

/** Emit hidden fields carrying the source/placement request. */
function local_coursepublisher_batch_hidden_values(array $values): void {
    foreach ($values as $name => $value) {
        echo html_writer::empty_tag('input', [
            'type' => 'hidden', 'name' => $name, 'value' => (string)$value,
        ]);
    }
}

$form = new batch_form();
$values = null;
$eligible = [];
$preflight = null;
$error = null;
$stage = optional_param('stage', '', PARAM_ALPHA);

if ($stage === 'preflight' || $stage === 'create') {
    require_sesskey();
    $values = local_coursepublisher_batch_post_values();
    $targetids = optional_param_array('targetbindids', [], PARAM_INT);
    try {
        $preflight = batch_service::preflight(local_coursepublisher_batch_request($values, $targetids));
        if ($stage === 'create') {
            require_capability('local/coursepublisher:publish', $context);
            // Intentionally rerun preflight in this request. No browser preview is trusted for mutation authorization.
            $confirmed = batch_service::preflight(local_coursepublisher_batch_request($values, $targetids));
            $batch = batch_service::create_from_preflight($confirmed, (int)$USER->id);
            redirect(
                new moodle_url('/local/coursepublisher/batch_view.php', ['id' => (int)$batch->id]),
                get_string('batchcreated', 'local_coursepublisher', (int)$batch->id),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
} else if ($data = $form->get_data()) {
    $values = [
        'programid' => (int)$data->programid,
        'gradekey' => (string)$data->gradekey,
        'sourcetype' => (string)$data->sourcetype,
        'sourceid' => (int)$data->sourceid,
        'publishmode' => (string)$data->publishmode,
        'placementmode' => (string)$data->placementmode,
        'manual_section_name' => (string)($data->manual_section_name ?? ''),
        'manual_position' => (string)($data->manual_position ?? ''),
        'manual_anchor_name' => (string)($data->manual_anchor_name ?? ''),
        'manual_anchor_modname' => (string)($data->manual_anchor_modname ?? ''),
    ];
    try {
        // This is selection discovery only. Bulk preflight happens after explicit target selection.
        $eligible = batch_service::eligible_targets((int)$data->programid, (string)$data->gradekey);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

local_coursepublisher_output_start('batch', get_string('newbatch', 'local_coursepublisher'));

local_coursepublisher_card_start(get_string('batchtitle', 'local_coursepublisher'), get_string('batchhelp', 'local_coursepublisher'));
$form->display();
local_coursepublisher_card_end();

if ($error) {
    echo html_writer::div(s($error), 'cp-notice-error');
}

if ($values && $preflight === null && !$error) {
    local_coursepublisher_card_start(
        get_string('batchselecttargets', 'local_coursepublisher'),
        get_string('batchconfiguredcount', 'local_coursepublisher', count($eligible))
    );
    if (!$eligible) {
        echo html_writer::div(get_string('batchnotargets', 'local_coursepublisher'), 'cp-empty');
    } else {
        $PAGE->requires->js_call_amd('local_coursepublisher/batch_selector', 'init');
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => (new moodle_url('/local/coursepublisher/batch.php'))->out(false),
            'class' => 'cp-batch-target-form', 'data-region' => 'batch-selector',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'stage', 'value' => 'preflight']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        local_coursepublisher_batch_hidden_values($values);

        echo html_writer::start_div('cp-batch-tools');
        echo html_writer::tag('button', get_string('batchselectall', 'local_coursepublisher'), [
            'type' => 'button', 'class' => 'btn btn-secondary', 'data-action' => 'select-all',
        ]);
        echo html_writer::tag('button', get_string('batchclearall', 'local_coursepublisher'), [
            'type' => 'button', 'class' => 'btn btn-secondary', 'data-action' => 'clear-all',
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'search', 'class' => 'form-control cp-batch-search',
            'placeholder' => get_string('batchtargetsearch', 'local_coursepublisher'), 'data-action' => 'search',
        ]);
        echo html_writer::end_div();

        $groups = [];
        foreach ($eligible as $target) {
            $key = (int)$target->regionid;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => (string)$target->regionname,
                    'code' => (string)$target->regioncode,
                    'targets' => [],
                ];
            }
            $groups[$key]['targets'][] = $target;
        }
        foreach ($groups as $regionid => $group) {
            echo html_writer::start_div('cp-batch-region', ['data-region-id' => $regionid]);
            echo html_writer::start_div('cp-batch-region-head');
            echo html_writer::tag('strong', format_string($group['name']) . ' [' . s($group['code']) . ']');
            echo html_writer::tag('button', get_string('batchselectregion', 'local_coursepublisher'), [
                'type' => 'button', 'class' => 'btn btn-sm btn-link', 'data-action' => 'select-region',
                'data-region-id' => $regionid,
            ]);
            echo html_writer::end_div();
            foreach ($group['targets'] as $target) {
                $search = strtolower(trim(
                    (string)$target->regionname . ' ' . (string)$target->regioncode . ' ' .
                    (string)$target->schoolname . ' ' . (string)$target->schoolcode . ' ' .
                    (string)$target->coursename . ' ' . (string)$target->shortname
                ));
                echo html_writer::start_tag('label', [
                    'class' => 'cp-batch-target', 'data-search' => $search, 'data-region-id' => $regionid,
                ]);
                echo html_writer::empty_tag('input', [
                    'type' => 'checkbox', 'name' => 'targetbindids[]', 'value' => (int)$target->targetbindid,
                    'class' => 'cp-batch-checkbox',
                ]);
                echo html_writer::start_span('cp-batch-target-copy');
                echo html_writer::span(format_string((string)$target->schoolname), 'cp-batch-school');
                echo html_writer::span(
                    format_string((string)$target->coursename) . ' [' . s((string)$target->shortname) . '] · #' . (int)$target->targetcourseid,
                    'cp-batch-course'
                );
                echo html_writer::end_span();
                echo html_writer::end_tag('label');
            }
            echo html_writer::end_div();
        }
        echo html_writer::div('', 'cp-batch-selected-count', ['data-region' => 'selected-count']);
        echo html_writer::tag('button', get_string('batchpreflight', 'local_coursepublisher'), [
            'type' => 'submit', 'class' => 'btn btn-primary mt-3',
        ]);
        echo html_writer::end_tag('form');
    }
    local_coursepublisher_card_end();
}

if ($preflight) {
    local_coursepublisher_card_start(get_string('batchpreflightresult', 'local_coursepublisher'));
    echo html_writer::start_div('cp-preview-summary');
    $summary = [
        [get_string('program', 'local_coursepublisher'), format_string($preflight['program']->name) . ' [' . s($preflight['program']->code) . ']'],
        [get_string('mastercourse', 'local_coursepublisher'), format_string($preflight['mastercourse']->fullname) . ' (#' . (int)$preflight['mastercourse']->id . ')'],
        [get_string('batchsource', 'local_coursepublisher'), format_string($preflight['source']->displayname) . ' · ' . s($preflight['sourcetype']) . ' #' . (int)$preflight['sourceid']],
        [get_string('batchselected', 'local_coursepublisher'), (string)$preflight['totaltargets']],
        [get_string('batchready', 'local_coursepublisher'), (string)$preflight['readytargets']],
        [get_string('batchblocked', 'local_coursepublisher'), (string)$preflight['blockedtargets']],
    ];
    foreach ($summary as [$label, $value]) {
        echo html_writer::start_div('cp-summary-tile');
        echo html_writer::div($label, 'cp-summary-label');
        echo html_writer::div($value, 'cp-summary-value');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('batchregion', 'local_coursepublisher'), get_string('batchschool', 'local_coursepublisher'),
        get_string('batchcourse', 'local_coursepublisher'), get_string('status', 'local_coursepublisher'),
        get_string('batchreason', 'local_coursepublisher'),
    ];
    foreach ($preflight['targets'] as $row) {
        $ready = $row['status'] === 'ready';
        $course = format_string((string)$row['coursename']);
        if (!empty($row['shortname'])) {
            $course .= ' [' . s((string)$row['shortname']) . ']';
        }
        if ((int)$row['targetcourseid'] > 0) {
            $course .= ' (#' . (int)$row['targetcourseid'] . ')';
        }
        $table->data[] = [
            format_string((string)$row['regionname']),
            format_string((string)$row['schoolname']),
            $course,
            local_coursepublisher_badge($ready ? 'ready' : 'blocked', $ready
                ? get_string('batchready', 'local_coursepublisher') : get_string('batchblocked', 'local_coursepublisher')),
            $ready ? '—' : s((string)$row['blockreason']),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap mt-3');

    if ((int)$preflight['blockedtargets'] > 0) {
        echo html_writer::div(get_string('batchconfirmwarning', 'local_coursepublisher'), 'cp-notice-warning');
    }
    if ((int)$preflight['readytargets'] > 0 && has_capability('local/coursepublisher:publish', $context)) {
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => (new moodle_url('/local/coursepublisher/batch.php'))->out(false),
            'class' => 'mt-3',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'stage', 'value' => 'create']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        local_coursepublisher_batch_hidden_values($values);
        foreach ($preflight['targets'] as $row) {
            echo html_writer::empty_tag('input', [
                'type' => 'hidden', 'name' => 'targetbindids[]', 'value' => (int)$row['targetbindid'],
            ]);
        }
        echo html_writer::tag('button', get_string('batchconfirm', 'local_coursepublisher', (int)$preflight['readytargets']), [
            'type' => 'submit', 'class' => 'btn btn-primary',
        ]);
        echo html_writer::end_tag('form');
    }
    local_coursepublisher_card_end();
}

local_coursepublisher_output_end();
