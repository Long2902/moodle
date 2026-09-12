<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\batch_service;
use local_coursepublisher\local\batch_status;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:view', $context);

$batchid = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/batch_view.php', ['id' => $batchid]));
$PAGE->set_title(get_string('batchdetail', 'local_coursepublisher', $batchid));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

if ($action !== '') {
    require_capability('local/coursepublisher:publish', $context);
    require_sesskey();
    switch ($action) {
        case 'pause':
            batch_service::pause($batchid, 'operator');
            break;
        case 'resume':
            batch_service::resume($batchid);
            break;
        case 'cancel':
            batch_service::cancel($batchid);
            break;
        case 'recheck':
            batch_service::recheck_blocked($batchid);
            break;
        case 'retry':
            batch_service::retry_safe_failures($batchid);
            break;
        default:
            throw new moodle_exception('invalidparameter');
    }
    redirect(
        new moodle_url('/local/coursepublisher/batch_view.php', ['id' => $batchid]),
        get_string('batchactiondone', 'local_coursepublisher'), null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$batch = batch_service::reconcile($batchid);
$targets = batch_service::targets($batchid);

/** Render a compact, auditable physical placement summary for a Batch Target. */
function local_coursepublisher_batch_resolved_placement(?string $json): string {
    if (empty($json)) {
        return '—';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'resolved' || !is_array($decoded['placement'] ?? null)) {
        return '—';
    }
    $placement = $decoded['placement'];
    $position = (string)($placement['position'] ?? '');
    if (isset($placement['targetsectionid'])) {
        $text = 'Section #' . (int)$placement['targetsectionid'] . ' · ' . $position;
        if ($position === 'before' && !empty($placement['beforecmid'])) {
            $text .= ' CM #' . (int)$placement['beforecmid'];
        }
        return $text;
    }
    if (isset($placement['anchorsectionid']) || isset($placement['beforesectionid'])) {
        $anchor = (int)($placement['anchorsectionid'] ?? $placement['beforesectionid'] ?? 0);
        return $position . ($anchor > 0 ? ' Section #' . $anchor : '');
    }
    return $position !== '' ? $position : '—';
}

local_coursepublisher_output_start('batches', get_string('batchdetail', 'local_coursepublisher', $batchid));

local_coursepublisher_card_start(
    get_string('batchsummary', 'local_coursepublisher'),
    local_coursepublisher_batch_badge((string)$batch->status)
);
echo html_writer::start_div('cp-preview-summary');
$summary = [
    [get_string('batchid', 'local_coursepublisher'), '#' . (int)$batch->id],
    [get_string('program', 'local_coursepublisher'), '#' . (int)$batch->programid . ' / ' . service::target_group_label((int)$batch->programid, (string)$batch->gradekey)],
    [get_string('batchsource', 'local_coursepublisher'), s((string)$batch->sourcetype) . ' #' . (int)$batch->sourceid . ' · course #' . (int)$batch->sourcecourseid],
    [get_string('batchstatus', 'local_coursepublisher'), local_coursepublisher_batch_badge((string)$batch->status)],
    [get_string('batchselected', 'local_coursepublisher'), (string)$batch->totaltargets],
    [get_string('batchready', 'local_coursepublisher'), (string)$batch->readytargets],
    [get_string('batchblocked', 'local_coursepublisher'), (string)$batch->blockedtargets],
    [get_string('batchwaiting', 'local_coursepublisher'), (string)$batch->waitingtargets],
    [get_string('batchqueued', 'local_coursepublisher'), (string)$batch->queuedtargets],
    [get_string('batchrunning', 'local_coursepublisher'), (string)$batch->runningtargets],
    [get_string('batchsucceeded', 'local_coursepublisher'), (string)$batch->succeededtargets],
    [get_string('batchfailed', 'local_coursepublisher'), (string)$batch->failedtargets],
    [get_string('batchmanualreview', 'local_coursepublisher'), (string)$batch->manualreviewtargets],
];
foreach ($summary as [$label, $value]) {
    echo html_writer::start_div('cp-summary-tile');
    echo html_writer::div($label, 'cp-summary-label');
    echo html_writer::div($value, 'cp-summary-value');
    echo html_writer::end_div();
}
echo html_writer::end_div();

$readydenom = max(1, (int)$batch->readytargets);
$pct = min(100, round(((int)$batch->succeededtargets / $readydenom) * 100, 1));
echo html_writer::start_div('cp-batch-progress mt-3');
echo html_writer::div(
    get_string('batchprogress', 'local_coursepublisher') . ': ' . (int)$batch->succeededtargets . '/' . (int)$batch->readytargets . ' (' . $pct . '%)',
    'cp-batch-progress-label'
);
echo html_writer::div(html_writer::div('', 'cp-batch-progress-bar', ['style' => 'width:' . $pct . '%']), 'cp-batch-progress-track');
echo html_writer::end_div();
if (!empty($batch->pausereason)) {
    echo html_writer::div(s((string)$batch->pausereason), 'cp-notice-warning mt-3');
}

if (has_capability('local/coursepublisher:publish', $context)) {
    echo html_writer::start_div('cp-batch-actions mt-3');
    $actions = [];
    if ((string)$batch->status === batch_status::PAUSED) {
        $actions['resume'] = get_string('batchresume', 'local_coursepublisher');
    } else if ((string)$batch->status !== batch_status::CANCELLED && !batch_status::is_terminal((string)$batch->status)) {
        $actions['pause'] = get_string('batchpause', 'local_coursepublisher');
    }
    if ((int)$batch->blockedtargets > 0 && (string)$batch->status !== batch_status::CANCELLED) {
        $actions['recheck'] = get_string('batchrecheckblocked', 'local_coursepublisher');
    }
    if ((int)$batch->failedtargets > 0 && (string)$batch->status !== batch_status::CANCELLED) {
        $actions['retry'] = get_string('batchretrysafe', 'local_coursepublisher');
    }
    if ((string)$batch->status !== batch_status::CANCELLED && !in_array((string)$batch->status, [batch_status::SUCCEEDED], true)) {
        $actions['cancel'] = get_string('batchcancel', 'local_coursepublisher');
    }
    foreach ($actions as $key => $label) {
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => (new moodle_url('/local/coursepublisher/batch_view.php'))->out(false),
            'class' => 'd-inline-block me-2 mb-2',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $batchid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $key]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $classes = $key === 'cancel' ? 'btn btn-outline-danger' : 'btn btn-secondary';
        echo html_writer::tag('button', $label, ['type' => 'submit', 'class' => $classes]);
        echo html_writer::end_tag('form');
    }
    echo html_writer::end_div();
}
local_coursepublisher_card_end();

local_coursepublisher_card_start(get_string('batchtargets', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($targets)));
$table = new html_table();
$table->attributes['class'] = 'generaltable cp-table';
$table->head = [
    get_string('batchregion', 'local_coursepublisher'), get_string('batchschool', 'local_coursepublisher'),
    get_string('batchcourse', 'local_coursepublisher'), get_string('batchinitialpreflight', 'local_coursepublisher'),
    get_string('batchplacement', 'local_coursepublisher'), get_string('status', 'local_coursepublisher'),
    get_string('batchreason', 'local_coursepublisher'), get_string('batchattempt', 'local_coursepublisher'),
    get_string('batchlatestjob', 'local_coursepublisher'), get_string('batchmutationstate', 'local_coursepublisher'),
];
foreach ($targets as $target) {
    $course = !empty($target->coursename) ? format_string((string)$target->coursename) : '#' . (int)$target->targetcourseid;
    if (!empty($target->shortname)) {
        $course .= ' [' . s((string)$target->shortname) . ']';
    }
    $joblink = '—';
    if ((int)$target->latestjobid > 0) {
        $joblink = html_writer::link(new moodle_url('/local/coursepublisher/jobs.php', ['jobid' => (int)$target->latestjobid]), '#' . (int)$target->latestjobid);
    }
    $table->data[] = [
        format_string((string)($target->regionname ?? '')),
        format_string((string)($target->schoolname ?? '')),
        $course,
        local_coursepublisher_batch_target_badge((string)$target->preflightstatus),
        s(local_coursepublisher_batch_resolved_placement($target->resolvedplacementjson ?? null)),
        local_coursepublisher_batch_target_badge((string)$target->status),
        !empty($target->blockreason) ? s((string)$target->blockreason) : '—',
        (string)$target->attempts,
        $joblink,
        !empty($target->latestmutationstate) ? s((string)$target->latestmutationstate) : '—',
    ];
}
echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
local_coursepublisher_card_end();

local_coursepublisher_output_end();
