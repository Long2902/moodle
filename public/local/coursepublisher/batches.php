<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\batch_service;
use local_coursepublisher\local\batch_status;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:view', $context);

$statusfilter = optional_param('status', '', PARAM_ALPHANUMEXT);
$programfilter = optional_param('programid', 0, PARAM_INT);
$gradefilter = optional_param('gradekey', '', PARAM_ALPHANUMEXT);
$fromdate = optional_param('fromdate', '', PARAM_RAW_TRIMMED);
$todate = optional_param('todate', '', PARAM_RAW_TRIMMED);

$validstatuses = [
    batch_status::DRAFT, batch_status::PREFLIGHT, batch_status::QUEUED, batch_status::RUNNING,
    batch_status::PAUSED, batch_status::PARTIAL, batch_status::SUCCEEDED, batch_status::FAILED,
    batch_status::CANCELLED,
];
if (!in_array($statusfilter, $validstatuses, true)) {
    $statusfilter = '';
}
if ($gradefilter !== '') {
    try { service::assert_grade($gradefilter); } catch (Throwable $e) { $gradefilter = ''; }
}

/** Convert an ISO date filter in the current user's timezone to a timestamp. */
function local_coursepublisher_batch_filter_date(string $value, bool $endofday = false): int {
    if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
        return 0;
    }
    $timezone = \core_date::get_user_timezone_object();
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
    if (!$date || $date->format('Y-m-d') !== $value) {
        return 0;
    }
    if ($endofday) {
        $date = $date->modify('+1 day')->modify('-1 second');
    }
    return $date->getTimestamp();
}

$filters = [
    'status' => $statusfilter,
    'programid' => $programfilter,
    'gradekey' => $gradefilter,
    'timefrom' => local_coursepublisher_batch_filter_date($fromdate),
    'timeto' => local_coursepublisher_batch_filter_date($todate, true),
];

$urlparams = array_filter([
    'status' => $statusfilter,
    'programid' => $programfilter ?: null,
    'gradekey' => $gradefilter,
    'fromdate' => $fromdate,
    'todate' => $todate,
], static fn($value): bool => $value !== '' && $value !== null);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/batches.php', $urlparams));
$PAGE->set_title(get_string('nav_batches', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

local_coursepublisher_output_start('batches', get_string('nav_batches', 'local_coursepublisher'));

local_coursepublisher_card_start(
    get_string('nav_batches', 'local_coursepublisher'),
    has_capability('local/coursepublisher:publish', $context)
        ? html_writer::link(new moodle_url('/local/coursepublisher/batch.php'), get_string('newbatch', 'local_coursepublisher'), ['class' => 'btn btn-primary btn-sm'])
        : ''
);

$statusoptions = ['' => get_string('batchfilterall', 'local_coursepublisher')];
foreach ($validstatuses as $status) {
    $statusoptions[$status] = get_string('batchstatus_' . $status, 'local_coursepublisher');
}
$gradeoptions = ['' => get_string('batchfilterall', 'local_coursepublisher')] + service::target_group_options(0, false);
$programoptions = service::program_options(false);
$programoptions[0] = get_string('batchfilterall', 'local_coursepublisher');

echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => (new moodle_url('/local/coursepublisher/batches.php'))->out(false),
    'class' => 'cp-batch-filter mb-3',
]);
echo html_writer::start_div('row g-2 align-items-end');
$controls = [
    ['status', get_string('status', 'local_coursepublisher'), html_writer::select($statusoptions, 'status', $statusfilter, false, ['class' => 'form-select'])],
    ['programid', get_string('program', 'local_coursepublisher'), html_writer::select($programoptions, 'programid', $programfilter, false, ['class' => 'form-select'])],
    ['gradekey', get_string('targetgroupgrade', 'local_coursepublisher'), html_writer::select($gradeoptions, 'gradekey', $gradefilter, false, ['class' => 'form-select'])],
    ['fromdate', get_string('batchfilterfrom', 'local_coursepublisher'), html_writer::empty_tag('input', ['type' => 'date', 'name' => 'fromdate', 'value' => $fromdate, 'class' => 'form-control'])],
    ['todate', get_string('batchfilterto', 'local_coursepublisher'), html_writer::empty_tag('input', ['type' => 'date', 'name' => 'todate', 'value' => $todate, 'class' => 'form-control'])],
];
foreach ($controls as [$name, $label, $control]) {
    echo html_writer::start_div('col-12 col-md-6 col-xl');
    echo html_writer::label($label, 'cp-filter-' . $name, false, ['class' => 'form-label mb-1']);
    // html_writer::select/input already carries its Moodle-safe field name; id is optional for these compact filters.
    echo $control;
    echo html_writer::end_div();
}
echo html_writer::start_div('col-12 col-xl-auto');
echo html_writer::tag('button', get_string('batchfilterapply', 'local_coursepublisher'), ['type' => 'submit', 'class' => 'btn btn-secondary me-2']);
echo html_writer::link(new moodle_url('/local/coursepublisher/batches.php'), get_string('batchfilterreset', 'local_coursepublisher'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('form');

$batches = batch_service::recent_batches(100, $filters);
if (!$batches) {
    echo html_writer::div(get_string('none', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('batchid', 'local_coursepublisher'), get_string('time', 'local_coursepublisher'),
        get_string('program', 'local_coursepublisher'), get_string('batchsource', 'local_coursepublisher'),
        get_string('batchtargets', 'local_coursepublisher'), get_string('batchprogress', 'local_coursepublisher'),
        get_string('batchblocked', 'local_coursepublisher'),
        get_string('batchfailed', 'local_coursepublisher') . ' / ' . get_string('batchmanualreview', 'local_coursepublisher'),
        get_string('status', 'local_coursepublisher'),
    ];
    foreach ($batches as $batch) {
        $readydenom = max(1, (int)$batch->readytargets);
        $pct = min(100, round(((int)$batch->succeededtargets / $readydenom) * 100, 1));
        $targets = (int)$batch->totaltargets . ' / ' . get_string('batchready', 'local_coursepublisher') . ': ' . (int)$batch->readytargets .
            ' / ' . get_string('batchblocked', 'local_coursepublisher') . ': ' . (int)$batch->blockedtargets;
        $progress = (int)$batch->succeededtargets . '/' . (int)$batch->readytargets . ' (' . $pct . '%)';
        $table->data[] = [
            html_writer::link(new moodle_url('/local/coursepublisher/batch_view.php', ['id' => (int)$batch->id]), '#' . (int)$batch->id),
            userdate((int)$batch->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            format_string((string)$batch->programname) . ' / ' . service::target_group_label((int)$batch->programid, (string)$batch->gradekey),
            s((string)$batch->sourcetype) . ' #' . (int)$batch->sourceid,
            s($targets),
            s($progress),
            (string)$batch->blockedtargets,
            get_string('batchfailed', 'local_coursepublisher') . ': ' . (int)$batch->failedtargets .
                ' / ' . get_string('batchmanualreview', 'local_coursepublisher') . ': ' . (int)$batch->manualreviewtargets,
            local_coursepublisher_batch_badge((string)$batch->status),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();
local_coursepublisher_output_end();
