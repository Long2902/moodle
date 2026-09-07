<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

function worksheetgrader_supports($feature) {
    return match ($feature) {
        FEATURE_MOD_ARCHETYPE => MOD_ARCHETYPE_OTHER,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_ASSESSMENT,
        FEATURE_MOD_INTRO => true,
        FEATURE_GROUPS => true,
        FEATURE_GRADE_HAS_GRADE => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_BACKUP_MOODLE2 => (bool)get_config('mod_worksheetgrader', 'backuprestoreverified'),
        default => null,
    };
}

function worksheetgrader_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = $data->timecreated;
    $data->grade = isset($data->grade) ? (float)$data->grade : 10.0;
    $id = $DB->insert_record('worksheetgrader', $data);
    $data->id = $id;
    worksheetgrader_grade_item_update($data);
    return $id;
}

function worksheetgrader_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('worksheetgrader', $data);
    worksheetgrader_grade_item_update($data);
    worksheetgrader_update_grades($data);
    return $result;
}

function worksheetgrader_delete_instance($id) {
    global $DB;
    if (!$activity = $DB->get_record('worksheetgrader', ['id' => $id])) {
        return false;
    }
    // Release dedicated DocSpace rooms before removing their local mappings.
    \mod_worksheetgrader\service\docspace_manager::delete_remote_rooms_for_activity((int)$id);
    $transaction = $DB->start_delegated_transaction();
    $sessions = $DB->get_records('wsg_session', ['worksheetgraderid' => $id], '', 'id');
    foreach ($sessions as $session) {
        $teams = $DB->get_records('wsg_team', ['sessionid' => $session->id], '', 'id');
        foreach ($teams as $team) {
            $attempts = $DB->get_records('wsg_attempt', ['teamid' => $team->id], '', 'id');
            foreach ($attempts as $attempt) {
                $DB->delete_records('wsg_grade', ['attemptid' => $attempt->id]);
            }
            $DB->delete_records('wsg_attempt', ['teamid' => $team->id]);
            $DB->delete_records('wsg_member', ['teamid' => $team->id]);
        }
        $DB->delete_records('wsg_team', ['sessionid' => $session->id]);
    }
    $DB->delete_records('wsg_docspace', ['worksheetgraderid' => $id]);
    $DB->delete_records('wsg_session', ['worksheetgraderid' => $id]);
    $DB->delete_records('wsg_log', ['worksheetgraderid' => $id]);
    $DB->delete_records('worksheetgrader', ['id' => $id]);
    $transaction->allow_commit();
    worksheetgrader_grade_item_delete($activity);
    return true;
}

function worksheetgrader_grade_item_update($activity, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $item = [
        'itemname' => clean_param($activity->name, PARAM_NOTAGS),
        'gradetype' => GRADE_TYPE_VALUE,
        'grademin' => 0,
        'grademax' => (float)$activity->grade,
    ];
    return grade_update('mod/worksheetgrader', $activity->course, 'mod', 'worksheetgrader', $activity->id, 0, $grades, $item);
}

function worksheetgrader_grade_item_delete($activity) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/worksheetgrader', $activity->course, 'mod', 'worksheetgrader', $activity->id, 0, null, ['deleted' => 1]);
}

function worksheetgrader_update_grades($activity, $userid = 0, $nullifnone = true) {
    require_once(__DIR__ . '/locallib.php');
    $grades = \mod_worksheetgrader\service\grading_manager::get_gradebook_grades($activity, $userid);
    if (!$grades && $nullifnone) {
        $grades = null;
    }
    return worksheetgrader_grade_item_update($activity, $grades);
}

function worksheetgrader_get_user_grades($activity, $userid = 0) {
    require_once(__DIR__ . '/locallib.php');
    return \mod_worksheetgrader\service\grading_manager::get_gradebook_grades($activity, $userid);
}

function worksheetgrader_get_coursemodule_info($coursemodule) {
    global $DB;
    $activity = $DB->get_record('worksheetgrader', ['id' => $coursemodule->instance], 'id,name,intro,introformat', IGNORE_MISSING);
    if (!$activity) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $activity->name;
    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('worksheetgrader', $activity, $coursemodule->id, false);
    }
    return $info;
}

function worksheetgrader_extend_navigation($navigation, $course, $module, $cm) {
    $context = context_module::instance($cm->id);
    if (has_capability('mod/worksheetgrader:managesessions', $context)) {
        $navigation->add(get_string('sessions', 'mod_worksheetgrader'), new moodle_url('/mod/worksheetgrader/sessions.php', ['id' => $cm->id]));
        $navigation->add(get_string('grading', 'mod_worksheetgrader'), new moodle_url('/mod/worksheetgrader/grade.php', ['id' => $cm->id]));
        $navigation->add(get_string('reports', 'mod_worksheetgrader'), new moodle_url('/mod/worksheetgrader/report.php', ['id' => $cm->id]));
    }
}

function worksheetgrader_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;
    if ($context->contextlevel !== CONTEXT_MODULE || !in_array($filearea, ['source', 'attemptimage', 'docspacetemplate', 'docspacesnapshot', 'officetemplate', 'officeworking', 'officesubmission'], true)) {
        return false;
    }
    require_login($course, true, $cm);
    require_capability('mod/worksheetgrader:view', $context);
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = '/' . (count($args) ? implode('/', $args) . '/' : '');

    if (in_array($filearea, ['docspacetemplate', 'officetemplate'], true) && !has_capability('mod/worksheetgrader:manageactivity', $context)) {
        return false;
    }

    if (in_array($filearea, ['attemptimage', 'docspacesnapshot', 'officeworking', 'officesubmission'], true)) {
        $attempt = $DB->get_record('wsg_attempt', ['id' => $itemid], 'id,teamid,membersnapshot', IGNORE_MISSING);
        if (!$attempt) {
            return false;
        }
        $canviewall = has_capability('mod/worksheetgrader:grade', $context) ||
            has_capability('mod/worksheetgrader:manageteams', $context);
        $members = json_decode($attempt->membersnapshot ?: '[]', true) ?: [];
        if (!$members) {
            $members = \mod_worksheetgrader\service\team_manager::get_member_ids((int)$attempt->teamid);
        }
        if (!$canviewall && !in_array((int)$USER->id, array_map('intval', $members), true)) {
            return false;
        }
        $forcedownload = in_array($filearea, ['docspacesnapshot', 'officesubmission'], true);
        $options['cacheability'] = 'private';
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'mod_worksheetgrader',
        $filearea,
        $itemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
