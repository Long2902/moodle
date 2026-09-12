<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$matrix = service::health_matrix();
$stats = service::dashboard_stats($matrix);
$audits = service::recent_audits(8);

local_coursepublisher_output_start('dashboard', get_string('pluginname', 'local_coursepublisher'));

$kpis = [
    ['◆', get_string('count_programs', 'local_coursepublisher'), $stats->programs, get_string('enabledonly', 'local_coursepublisher')],
    ['◎', get_string('count_masters', 'local_coursepublisher'), $stats->masters, get_string('sourcebindings', 'local_coursepublisher')],
    ['▣', get_string('count_targets', 'local_coursepublisher'), $stats->targets, get_string('targetbindings', 'local_coursepublisher')],
    ['✓', get_string('readyroutes', 'local_coursepublisher'), $stats->ready, get_string('healthrouteok', 'local_coursepublisher')],
    ['!', get_string('needattention', 'local_coursepublisher'), $stats->warnings + $stats->errors,
        get_string('dashboardissuecount', 'local_coursepublisher', (object)['warning' => $stats->warnings, 'error' => $stats->errors])],
];

echo html_writer::start_div('cp-kpi-grid');
foreach ($kpis as [$icon, $label, $value, $note]) {
    echo html_writer::start_div('cp-kpi');
    echo html_writer::div(
        html_writer::span($icon, 'cp-kpi-icon', ['aria-hidden' => 'true']) . html_writer::span($label),
        'cp-kpi-top'
    );
    echo html_writer::div((string)$value, 'cp-kpi-value');
    echo html_writer::div($note, 'cp-kpi-note');
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::start_div('cp-grid-2');
local_coursepublisher_card_start(get_string('audit', 'local_coursepublisher'), get_string('latest8', 'local_coursepublisher'));
if (!$audits) {
    echo html_writer::div(
        html_writer::div('◇', 'cp-empty-icon') .
        html_writer::tag('strong', get_string('none', 'local_coursepublisher')) .
        html_writer::div(get_string('auditemptyhelp', 'local_coursepublisher'), 'cp-muted'),
        'cp-empty'
    );
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable cp-table';
    $table->head = [
        get_string('id', 'local_coursepublisher'),
        get_string('time', 'local_coursepublisher'),
        get_string('action', 'local_coursepublisher'),
        get_string('entity', 'local_coursepublisher'),
        get_string('user', 'local_coursepublisher'),
    ];
    foreach ($audits as $audit) {
        $username = trim(($audit->firstname ?? '') . ' ' . ($audit->lastname ?? ''));
        $table->data[] = [
            '#' . $audit->id,
            userdate($audit->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            s($audit->action),
            s($audit->entitytype) . ' #' . $audit->entityid,
            $username !== '' ? s($username) : '#' . $audit->userid,
        ];
    }
    echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
}
local_coursepublisher_card_end();

$latest = $audits[0] ?? null;
local_coursepublisher_card_start(get_string('configurationdetail', 'local_coursepublisher'));
if (!$latest) {
    echo html_writer::div(get_string('nodetail', 'local_coursepublisher'), 'cp-empty');
} else {
    $details = json_decode((string)$latest->details, true);
    $detailhtml = html_writer::start_tag('dl', ['class' => 'cp-detail-list']);
    $detailhtml .= html_writer::tag('dt', get_string('time', 'local_coursepublisher')) .
        html_writer::tag('dd', userdate($latest->timecreated));
    $detailhtml .= html_writer::tag('dt', get_string('action', 'local_coursepublisher')) .
        html_writer::tag('dd', s($latest->action));
    $detailhtml .= html_writer::tag('dt', get_string('entity', 'local_coursepublisher')) .
        html_writer::tag('dd', s($latest->entitytype) . ' #' . $latest->entityid);
    if (is_array($details)) {
        foreach ($details as $key => $value) {
            if (is_scalar($value)) {
                $detailhtml .= html_writer::tag('dt', s((string)$key)) .
                    html_writer::tag('dd', s((string)$value));
            }
        }
    }
    $detailhtml .= html_writer::end_tag('dl');
    echo $detailhtml;
}
local_coursepublisher_card_end();
echo html_writer::end_div();

local_coursepublisher_card_start(
    get_string('nav_health', 'local_coursepublisher'),
    html_writer::link(new moodle_url('/local/coursepublisher/health.php'), get_string('viewall', 'local_coursepublisher'), ['class' => 'cp-action-link'])
);
if (!$matrix) {
    echo html_writer::div(get_string('healthnoresults', 'local_coursepublisher'), 'cp-empty');
} else {
    $htable = new html_table();
    $htable->attributes['class'] = 'generaltable cp-table';
    $htable->head = [
        get_string('program', 'local_coursepublisher'),
        get_string('region', 'local_coursepublisher'),
        get_string('schoolunit', 'local_coursepublisher'),
        get_string('targetgroupgrade', 'local_coursepublisher'),
    ];
    $shown = 0;
    foreach ($matrix as $group) {
        foreach ($group->rows as $row) {
            if ($shown >= 6) {
                break 2;
            }
            $routeparts = [];
            foreach ($group->targetgroups as $targetgroup) {
                $routeparts[] = html_writer::span(
                    format_string($targetgroup->name) . ': ' . local_coursepublisher_health_cell($row->grades[(string)$targetgroup->groupkey]),
                    'me-2'
                );
            }
            $htable->data[] = [
                format_string($group->program->name),
                s($row->school->regionname),
                format_string($row->school->name),
                implode(' ', $routeparts),
            ];
            $shown++;
        }
    }
    echo html_writer::div(html_writer::table($htable), 'cp-table-wrap');
}
local_coursepublisher_card_end();

local_coursepublisher_output_end();
