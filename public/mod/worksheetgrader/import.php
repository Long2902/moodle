<?php
// Backward-compatible entry point. The import workflow now lives in content.php.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:manageactivity', $context);
$DB->get_record('wsg_session', ['id' => $sessionid, 'worksheetgraderid' => $activity->id], 'id', MUST_EXIST);
worksheetgrader_redirect(new moodle_url('/mod/worksheetgrader/content.php', [
    'id' => $id,
    'sessionid' => $sessionid,
]));
