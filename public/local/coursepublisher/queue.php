<?php

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

use local_coursepublisher\local\job_service;

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

try {
    $job = job_service::create_or_reuse($programid, $gradekey, $sourcetype, $sourceid, $schoolid);
    $message = !empty($job->reused)
        ? get_string('jobreused', 'local_coursepublisher', $job->id)
        : get_string('jobqueued', 'local_coursepublisher', $job->id);
    redirect(
        new moodle_url('/local/coursepublisher/jobs.php', ['jobid' => $job->id]),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (Throwable $e) {
    redirect(
        new moodle_url('/local/coursepublisher/preview.php'),
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
