<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

use local_coursepublisher\local\content_publisher;
use local_coursepublisher\local\job_service;
use local_coursepublisher\local\service;

require_login();
$context = context_system::instance();
require_capability('local/coursepublisher:publish', $context);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new moodle_exception('invalidparameter');
}
require_sesskey();

$programid = required_param('programid', PARAM_INT);
$gradekey = required_param('gradekey', PARAM_ALPHANUMEXT);
$sourcetype = required_param('sourcetype', PARAM_ALPHA);
$sourceid = required_param('sourceid', PARAM_INT);
$schoolid = required_param('schoolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$placementkey = optional_param('placementkey', '', PARAM_RAW_TRIMMED);
$publishvariant = optional_param('publishvariant', '', PARAM_ALPHA);

$school = $DB->get_record('local_cp_school', ['id' => $schoolid, 'enabled' => 1], '*', MUST_EXIST);
$result = service::resolve_preview($programid, $gradekey, $sourcetype, $sourceid, (int)$school->regionid, $schoolid);
if (count($result['targets']) !== 1) {
    throw new moodle_exception('jobexactroute', 'local_coursepublisher');
}
$route = reset($result['targets']);
if (!$route || $route->status !== 'ready') {
    throw new moodle_exception('jobpreflightnotready', 'local_coursepublisher');
}

$manifest = job_service::build_manifest($sourcetype, $sourceid, (int)$result['mastercourse']->id);
$mode = '';
$placementoptions = [];
$titlekey = '';
$subtitlekey = '';
$warningkey = '';
$helpkey = '';
$buttonkey = '';
$queuedkey = '';

if ($sourcetype === 'activity') {
    if (count($manifest) !== 1) {
        throw new moodle_exception('invalidparameter');
    }
    $activity = content_publisher::validate_backup_capable_activity(
        (int)$manifest[0]['sourcecmid'],
        (int)$result['mastercourse']->id
    );
    $modname = (string)$activity['modname'];
    if ($modname === 'subsection') {
        throw new moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
    }

    $mode = job_service::MODE_ACTIVITY_GENERIC_PLACEMENT;
    $placementoptions = local_coursepublisher_build_activity_placement_options((int)$route->courseid);
    $titlekey = 'genericactivityconfirmtitle';
    $subtitlekey = 'genericactivityconfirmsubtitle';
    $warningkey = 'genericactivityrealwarning';
    $helpkey = 'genericactivityplacementhelp';
    $buttonkey = 'genericactivityconfirmbutton';
    $queuedkey = 'genericactivityqueued';
} else if ($sourcetype === 'section') {
    $sourcesection = $DB->get_record(
        'course_sections',
        ['id' => $sourceid, 'course' => (int)$result['mastercourse']->id],
        'id,course,section,component,itemid',
        MUST_EXIST
    );
    content_publisher::validate_source_section_for_generic_copy(
        $sourceid,
        (int)$result['mastercourse']->id
    );
    foreach ($manifest as $row) {
        content_publisher::validate_backup_capable_activity(
            (int)$row['sourcecmid'],
            (int)$result['mastercourse']->id
        );
    }

    if ($publishvariant === 'subsectiontree') {
        if ((string)$sourcesection->component !== 'mod_subsection') {
            throw new moodle_exception('subsectionpilotnotdelegated', 'local_coursepublisher');
        }
        content_publisher::validate_source_delegated_subsection(
            $sourceid,
            (int)$result['mastercourse']->id
        );
        $mode = job_service::MODE_SUBSECTION_TREE_PLACEMENT;
        $placementoptions = local_coursepublisher_build_activity_placement_options((int)$route->courseid);
        $titlekey = 'subsectiongenericconfirmtitle';
        $subtitlekey = 'subsectiongenericconfirmsubtitle';
        $warningkey = 'subsectiongenericrealwarning';
        $helpkey = 'subsectiongenericplacementhelp';
        $buttonkey = 'subsectiongenericconfirmbutton';
        $queuedkey = 'subsectiongenericqueued';
    } else {
        // Default section real-copy mode: materialise the source as a normal
        // same-level section, including delegated subsection sources.
        $publishvariant = 'peersection';
        $mode = job_service::MODE_SECTION_GENERIC_PEER;
        $placementoptions = local_coursepublisher_build_section_placement_options((int)$route->courseid);
        $titlekey = 'sectionpeerconfirmtitle';
        $subtitlekey = 'sectionpeerconfirmsubtitle';
        $warningkey = 'sectionpeerrealwarning';
        $helpkey = 'sectionpeerplacementhelp';
        $buttonkey = 'sectionpeerconfirmbutton';
        $queuedkey = 'sectionpeerqueued';
    }
} else {
    throw new moodle_exception('invalidparameter');
}

if ($confirm) {
    if ($placementkey === '' || !isset($placementoptions[$placementkey])) {
        $sectionlevelmode = in_array($mode, [
            job_service::MODE_SECTION_PAGE_PLACEMENT,
            job_service::MODE_SECTION_MIXED_PLACEMENT,
            job_service::MODE_SECTION_GENERIC_PEER,
        ], true);
        throw new moodle_exception(
            $sectionlevelmode ? 'sectionplacementinvalid' : 'placementinvalid',
            'local_coursepublisher'
        );
    }

    if ($mode === job_service::MODE_SECTION_GENERIC_PEER || in_array($mode, [job_service::MODE_SECTION_PAGE_PLACEMENT, job_service::MODE_SECTION_MIXED_PLACEMENT], true)) {
        $placement = local_coursepublisher_parse_section_placement_key($placementkey);
        $placement = content_publisher::validate_section_placement((int)$route->courseid, $placement);
    } else {
        $placement = local_coursepublisher_parse_activity_placement_key($placementkey);
        $placement = content_publisher::validate_placement((int)$route->courseid, $placement);
    }

    $job = job_service::create_or_reuse(
        $programid,
        $gradekey,
        $sourcetype,
        $sourceid,
        $schoolid,
        $mode,
        $placement
    );
    $message = !empty($job->manualreview)
        ? get_string('jobmanualreviewrequired', 'local_coursepublisher', $job->id)
        : (!empty($job->reused)
            ? get_string('jobreused', 'local_coursepublisher', $job->id)
            : get_string($queuedkey, 'local_coursepublisher', $job->id));
    $type = !empty($job->manualreview)
        ? \core\output\notification::NOTIFY_WARNING
        : \core\output\notification::NOTIFY_SUCCESS;
    redirect(new moodle_url('/local/coursepublisher/jobs.php', ['jobid' => $job->id]), $message, null, $type);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursepublisher/publish.php'));
$PAGE->set_title(get_string($titlekey, 'local_coursepublisher'));
$PAGE->set_heading(get_string('pluginname', 'local_coursepublisher'));

local_coursepublisher_output_start('preview', get_string($titlekey, 'local_coursepublisher'));

local_coursepublisher_card_start(get_string($titlekey, 'local_coursepublisher'));
echo html_writer::div(get_string($warningkey, 'local_coursepublisher'), 'cp-notice-error');

$sourcevalue = format_string($result['source']->displayname)
    . ' — ' . $sourcetype . ' #' . $sourceid;
if ($sourcetype === 'section') {
    $sourcevalue .= ' — ' . get_string('sectiongenericcount', 'local_coursepublisher', count($manifest));
}

echo html_writer::start_div('cp-preview-summary mt-3');
foreach ([
    [get_string('source', 'local_coursepublisher'), $sourcevalue],
    [get_string('mastercourse', 'local_coursepublisher'), format_string($result['mastercourse']->fullname) . ' (#' . $result['mastercourse']->id . ')'],
    [get_string('school', 'local_coursepublisher'), format_string($school->name)],
    [get_string('targetcourse', 'local_coursepublisher'), format_string($route->coursename) . ' (#' . $route->courseid . ')'],
] as [$label, $value]) {
    echo html_writer::start_div('cp-summary-tile');
    echo html_writer::div($label, 'cp-summary-label');
    echo html_writer::div($value, 'cp-summary-value');
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/coursepublisher/publish.php'))->out(false),
    'class' => 'mt-3',
]);
foreach ([
    'programid' => $programid,
    'gradekey' => $gradekey,
    'sourcetype' => $sourcetype,
    'sourceid' => $sourceid,
    'schoolid' => $schoolid,
    'publishvariant' => $publishvariant,
    'confirm' => 1,
    'sesskey' => sesskey(),
] as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}

echo html_writer::start_div('form-group');
$usessectionplacement = in_array($mode, [job_service::MODE_SECTION_PAGE_PLACEMENT, job_service::MODE_SECTION_MIXED_PLACEMENT, job_service::MODE_SECTION_GENERIC_PEER], true);
echo html_writer::tag('label', get_string(
    $usessectionplacement ? 'sectionplacementfield' : 'placementfield',
    'local_coursepublisher'
), [
    'for' => 'id_placementkey',
    'class' => 'font-weight-bold d-block mb-2',
]);
echo html_writer::select(
    $placementoptions,
    'placementkey',
    '',
    ['' => get_string(
        $usessectionplacement ? 'sectionplacementchoose' : 'placementchoose',
        'local_coursepublisher'
    )],
    ['id' => 'id_placementkey', 'class' => 'form-control', 'required' => 'required']
);
echo html_writer::end_div();

echo html_writer::start_div('d-flex gap-2 flex-wrap mt-3');
echo html_writer::tag('button', get_string($buttonkey, 'local_coursepublisher'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::link(
    new moodle_url('/local/coursepublisher/preview.php'),
    get_string('cancel'),
    ['class' => 'btn btn-secondary']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');
local_coursepublisher_card_end();

local_coursepublisher_output_end();

/**
 * Build one explicit option per target activity insertion point.
 */
function local_coursepublisher_build_activity_placement_options(int $targetcourseid): array {
    global $DB;

    $course = get_course($targetcourseid);
    rebuild_course_cache($targetcourseid, true);
    $modinfo = get_fast_modinfo($targetcourseid);
    $sections = $DB->get_records('course_sections', ['course' => $targetcourseid], 'section ASC');
    $options = [];

    foreach ($sections as $section) {
        if (!empty($section->component)) {
            continue;
        }
        $sectionlabel = get_section_name($course, $section);
        $prefix = get_string('placementsectionprefix', 'local_coursepublisher', (object)[
            'number' => (int)$section->section,
            'name' => $sectionlabel,
        ]);
        $options[$section->id . '|start|0'] = $prefix . ' — ' . get_string('placementstart', 'local_coursepublisher');
        $options[$section->id . '|end|0'] = $prefix . ' — ' . get_string('placementend', 'local_coursepublisher');

        $sequence = array_values(array_filter(array_map('intval', explode(',', trim((string)$section->sequence)))));
        foreach ($sequence as $cmid) {
            try {
                $cm = $modinfo->get_cm($cmid);
                if (!empty($cm->deletioninprogress)) {
                    continue;
                }
                $cmname = format_string($cm->name, true, ['context' => \context_module::instance($cmid)]);
                $options[$section->id . '|before|' . $cmid] = $prefix . ' — ' . get_string(
                    'placementbefore',
                    'local_coursepublisher',
                    (object)['name' => $cmname, 'cmid' => $cmid]
                );
            } catch (\Throwable $ignored) {
                // A stale sequence entry should not make the confirmation page unusable.
            }
        }
    }

    return $options;
}

/**
 * Build section-level insertion points for a brand-new section.
 */
function local_coursepublisher_build_section_placement_options(int $targetcourseid): array {
    global $DB;

    $course = get_course($targetcourseid);
    $sections = $DB->get_records('course_sections', ['course' => $targetcourseid], 'section ASC');
    $options = [
        'start|0' => get_string('sectionplacementstart', 'local_coursepublisher'),
        'end|0' => get_string('sectionplacementend', 'local_coursepublisher'),
    ];

    foreach ($sections as $section) {
        if ((int)$section->section <= 0 || !empty($section->component)) {
            continue;
        }
        $name = get_section_name($course, $section);
        $data = (object)[
            'number' => (int)$section->section,
            'name' => $name,
            'sectionid' => (int)$section->id,
        ];
        $options['before|' . $section->id] = get_string(
            'sectionplacementbefore',
            'local_coursepublisher',
            $data
        );
        $options['after|' . $section->id] = get_string(
            'sectionplacementafter',
            'local_coursepublisher',
            $data
        );
    }

    return $options;
}

/** Parse the server-generated activity placement key. */
function local_coursepublisher_parse_activity_placement_key(string $key): array {
    if (!preg_match('/^(\d+)\|(start|end|before)\|(\d+)$/', $key, $matches)) {
        throw new moodle_exception('placementinvalid', 'local_coursepublisher');
    }
    return [
        'targetsectionid' => (int)$matches[1],
        'position' => (string)$matches[2],
        'beforecmid' => (int)$matches[3],
    ];
}

/** Parse the server-generated section placement key. */
function local_coursepublisher_parse_section_placement_key(string $key): array {
    if (!preg_match('/^(start|end|before|after)\|(\d+)$/', $key, $matches)) {
        throw new moodle_exception('sectionplacementinvalid', 'local_coursepublisher');
    }
    return [
        'position' => (string)$matches[1],
        'beforesectionid' => (int)$matches[2],
        'anchorsectionid' => (int)$matches[2],
    ];
}
