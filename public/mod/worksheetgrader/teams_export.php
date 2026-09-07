<?php
// This file is part of Moodle - http://moodle.org/.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
[$cm, $course, $activity, $context] = worksheetgrader_get_page_context($id);
require_capability('mod/worksheetgrader:manageteams', $context);
$session = $DB->get_record('wsg_session', [
    'id' => $sessionid,
    'worksheetgraderid' => $activity->id,
], '*', MUST_EXIST);
if ($session->teammode === 'individual') {
    throw new moodle_exception('csvnotindividual', 'mod_worksheetgrader');
}
$csv = \mod_worksheetgrader\service\team_csv_manager::export_csv($sessionid);
\core\session\manager::write_close();
$filename = clean_filename('chia-nhom-' . $session->name . '.csv');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csv));
echo $csv;
exit;
