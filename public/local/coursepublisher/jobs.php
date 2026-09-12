<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\job_service;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:view', $context);

$jobid = optional_param('jobid', 0, PARAM_INT);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/jobs.php', $jobid ? ['jobid' => $jobid] : []));
$PAGE->set_title(get_string('nav_jobs', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

local_coursepublisher_output_start('jobs', get_string('nav_jobs', 'local_coursepublisher'));

if ($jobid) {
    $job = job_service::get_job($jobid);
    $items = job_service::items($jobid);
    $logs = job_service::logs($jobid);
    $snapshot = json_decode((string)$job->preflightjson, true);
    $storedplacement = is_array($snapshot) && !empty($snapshot['placement']) && is_array($snapshot['placement'])
        ? $snapshot['placement']
        : [];

    local_coursepublisher_card_start(
        get_string('jobdetail', 'local_coursepublisher', $jobid),
        local_coursepublisher_job_badge((string)$job->status)
    );
    echo html_writer::start_div('cp-preview-summary');
    $summary = [
        [get_string('jobmode', 'local_coursepublisher'), s($job->mode)],
        [get_string('program', 'local_coursepublisher'), '#' . $job->programid . ' / ' . service::target_group_label((int)$job->programid, (string)$job->gradekey)],
        [get_string('school', 'local_coursepublisher'), '#' . $job->schoolid],
        [get_string('source', 'local_coursepublisher'), s($job->sourcetype) . ' #' . $job->sourceid],
        [get_string('targetcourse', 'local_coursepublisher'), '#' . $job->targetcourseid],
        [get_string('jobattempts', 'local_coursepublisher'), (string)$job->attempts],
    ];
    if ((int)($job->batchid ?? 0) > 0) {
        $summary[] = [get_string('batchid', 'local_coursepublisher'),
            html_writer::link(new moodle_url('/local/coursepublisher/batch_view.php', ['id' => (int)$job->batchid]), '#' . (int)$job->batchid)];
        $summary[] = [get_string('batchtargets', 'local_coursepublisher'), '#' . (int)$job->batchtargetid];
        $summary[] = [get_string('batchattempt', 'local_coursepublisher'), (string)$job->attemptno];
        $summary[] = [get_string('batchmutationstate', 'local_coursepublisher'), s((string)$job->mutationstate)];
    }
    if (in_array((string)$job->mode, [job_service::MODE_PAGE_PLACEMENT, job_service::MODE_QUIZ_PLACEMENT, job_service::MODE_SUBSECTION_TREE_PLACEMENT, job_service::MODE_ACTIVITY_GENERIC_PLACEMENT], true) && $storedplacement) {
        $summary[] = [get_string('jobtargetsection', 'local_coursepublisher'), '#' . (int)$storedplacement['targetsectionid']];
        $positiontext = (string)($storedplacement['position'] ?? '');
        if ($positiontext === 'before' && !empty($storedplacement['beforecmid'])) {
            $positiontext .= ' #' . (int)$storedplacement['beforecmid'];
        }
        $summary[] = [get_string('jobtargetposition', 'local_coursepublisher'), s($positiontext)];
    } else if (in_array((string)$job->mode, [job_service::MODE_SECTION_PAGE_PLACEMENT, job_service::MODE_SECTION_MIXED_PLACEMENT, job_service::MODE_SECTION_GENERIC_PEER], true) && $storedplacement) {
        $createdsection = (int)($job->targetsectionid ?? 0);
        $summary[] = [get_string('jobtargetsection', 'local_coursepublisher'),
            $createdsection > 0 ? '#' . $createdsection : get_string('notavailable')];
        $positiontext = (string)($storedplacement['position'] ?? '');
        if (in_array($positiontext, ['before', 'after'], true) && !empty($storedplacement['beforesectionid'])) {
            $positiontext .= ' section-id #' . (int)$storedplacement['beforesectionid'];
        }
        $summary[] = [get_string('jobtargetposition', 'local_coursepublisher'), s($positiontext)];
    }
    foreach ($summary as [$label, $value]) {
        echo html_writer::start_div('cp-summary-tile');
        echo html_writer::div($label, 'cp-summary-label');
        echo html_writer::div($value, 'cp-summary-value');
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    if (!empty($job->lasterror)) {
        echo html_writer::div(s($job->lasterror), 'cp-notice-error mt-3');
    }
    if ((string)$job->status === \local_coursepublisher\local\job_status::FAILED) {
        echo html_writer::div(
            get_string('jobpagepilotfailedreview', 'local_coursepublisher'),
            'cp-notice-warning mt-2'
        );
    }
    local_coursepublisher_card_end();

    local_coursepublisher_card_start(get_string('jobitems', 'local_coursepublisher'), get_string('recordcount', 'local_coursepublisher', count($items)));
    if (!$items) {
        echo html_writer::div(get_string('jobitemsnone', 'local_coursepublisher'), 'cp-empty');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable cp-table';
        $table->head = ['#', 'CMID', get_string('module', 'local_coursepublisher'), get_string('targetcmid', 'local_coursepublisher'), get_string('status', 'local_coursepublisher'), get_string('reason', 'local_coursepublisher')];
        foreach ($items as $item) {
            $table->data[] = [
                (int)$item->sortorder + 1,
                '#' . $item->sourcecmid,
                s($item->sourcemodule),
                (int)$item->targetcmid > 0 ? '#' . (int)$item->targetcmid : '—',
                local_coursepublisher_job_badge((string)$item->status),
                s((string)$item->errormessage),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
    }
    local_coursepublisher_card_end();

    local_coursepublisher_card_start(get_string('jobaudit', 'local_coursepublisher'));
    if (!$logs) {
        echo html_writer::div(get_string('none', 'local_coursepublisher'), 'cp-empty');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable cp-table';
        $table->head = [get_string('time', 'local_coursepublisher'), get_string('event', 'local_coursepublisher'), get_string('level', 'local_coursepublisher'), get_string('details', 'local_coursepublisher')];
        foreach ($logs as $log) {
            $table->data[] = [
                userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                s($log->event),
                s($log->level),
                s($log->message),
            ];
        }
        echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
    }
    local_coursepublisher_card_end();
}

local_coursepublisher_card_start(get_string('recentjobs', 'local_coursepublisher'));
$jobs = job_service::recent_jobs(50);
if (!$jobs) {
    echo html_writer::div(get_string('jobsnone', 'local_coursepublisher'), 'cp-empty');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [get_string('id', 'local_coursepublisher'), get_string('batchid', 'local_coursepublisher'), get_string('time', 'local_coursepublisher'), get_string('program', 'local_coursepublisher'), get_string('school', 'local_coursepublisher'), get_string('source', 'local_coursepublisher'), get_string('targetcourse', 'local_coursepublisher'), get_string('status', 'local_coursepublisher')];
    foreach ($jobs as $job) {
        $name = trim(($job->firstname ?? '') . ' ' . ($job->lastname ?? ''));
        $table->data[] = [
            html_writer::link(new moodle_url('/local/coursepublisher/jobs.php', ['jobid' => $job->id]), '#' . $job->id),
            (int)($job->batchid ?? 0) > 0
                ? html_writer::link(new moodle_url('/local/coursepublisher/batch_view.php', ['id' => (int)$job->batchid]), '#' . (int)$job->batchid)
                : '—',
            userdate($job->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            format_string((string)$job->programname) . ' / ' . service::target_group_label((int)$job->programid, (string)$job->gradekey),
            format_string((string)$job->schoolname),
            s($job->sourcetype) . ' #' . $job->sourceid,
            format_string((string)$job->targetname) . ' (#' . $job->targetcourseid . ')',
            local_coursepublisher_job_badge((string)$job->status),
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();

local_coursepublisher_output_end();
