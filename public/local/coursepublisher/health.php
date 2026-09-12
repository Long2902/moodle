<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/health.php'));
$PAGE->set_title(get_string('nav_health', 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

$matrix = service::health_matrix();
$stats = service::dashboard_stats($matrix);

local_coursepublisher_output_start('health', get_string('nav_health', 'local_coursepublisher'), get_string('healthsubtitle', 'local_coursepublisher'));

echo html_writer::start_div('cp-kpi-grid');
$kpis = [
    ['✓', get_string('readyroutes', 'local_coursepublisher'), $stats->ready, get_string('healthrouteok', 'local_coursepublisher')],
    ['!', get_string('warnings', 'local_coursepublisher'), $stats->warnings, get_string('healthwarningnote', 'local_coursepublisher')],
    ['×', get_string('errors', 'local_coursepublisher'), $stats->errors, get_string('healtherrornote', 'local_coursepublisher')],
    ['◆', get_string('count_programs', 'local_coursepublisher'), $stats->programs, get_string('enabledonly', 'local_coursepublisher')],
    ['▤', get_string('count_schools', 'local_coursepublisher'), $stats->schools, get_string('enabledonly', 'local_coursepublisher')],
];
foreach ($kpis as [$icon, $label, $value, $note]) {
    echo html_writer::start_div('cp-kpi');
    echo html_writer::div(html_writer::span($icon, 'cp-kpi-icon') . html_writer::span($label), 'cp-kpi-top');
    echo html_writer::div((string)$value, 'cp-kpi-value');
    echo html_writer::div($note, 'cp-kpi-note');
    echo html_writer::end_div();
}
echo html_writer::end_div();

if (!$matrix) {
    local_coursepublisher_card_start(get_string('nav_health', 'local_coursepublisher'));
    echo html_writer::div(get_string('healthnoresults', 'local_coursepublisher'), 'cp-empty');
    local_coursepublisher_card_end();
} else {
    foreach ($matrix as $group) {
        local_coursepublisher_card_start(
            format_string($group->program->name) . ' [' . s($group->program->code) . ']',
            get_string('healthpreflight', 'local_coursepublisher')
        );
        if (!$group->rows) {
            echo html_writer::div(get_string('healthnoschools', 'local_coursepublisher'), 'cp-empty');
        } else {
            $table = new html_table();
            $table->attributes['class'] = 'generaltable cp-table';
            $table->head = [
                get_string('region', 'local_coursepublisher'),
                get_string('schoolunit', 'local_coursepublisher'),
            ];
            foreach ($group->targetgroups as $targetgroup) {
                $table->head[] = format_string($targetgroup->name) . ' [' . s($targetgroup->groupkey) . ']';
            }
            foreach ($group->rows as $row) {
                $data = [
                    format_string($row->school->regionname),
                    format_string($row->school->name) . ' [' . s($row->school->code) . ']',
                ];
                foreach ($group->targetgroups as $targetgroup) {
                    $data[] = local_coursepublisher_health_cell($row->grades[(string)$targetgroup->groupkey]);
                }
                $table->data[] = $data;
            }
            echo html_writer::div(html_writer::table($table), 'cp-table-wrap');
        }
        local_coursepublisher_card_end();
    }
}

local_coursepublisher_card_start(get_string('healthrules', 'local_coursepublisher'));
$rules = [
    get_string('healthrule_schoolregion', 'local_coursepublisher'),
    get_string('healthrule_targetschool', 'local_coursepublisher'),
    get_string('healthrule_unique', 'local_coursepublisher'),
    get_string('healthrule_mastertarget', 'local_coursepublisher'),
    get_string('healthrule_courseexists', 'local_coursepublisher'),
];
echo html_writer::start_tag('ul', ['class' => 'mb-0']);
foreach ($rules as $rule) {
    echo html_writer::tag('li', $rule, ['class' => 'mb-2']);
}
echo html_writer::end_tag('ul');
local_coursepublisher_card_end();

local_coursepublisher_output_end();
