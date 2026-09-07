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

require('../../config.php');
$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);
$PAGE->set_url('/mod/worksheetgrader/index.php', ['id' => $id]);
$PAGE->set_title(get_string('modulenameplural', 'mod_worksheetgrader'));
$PAGE->set_heading(format_string($course->fullname));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_worksheetgrader'));
echo $OUTPUT->box_start();
foreach (get_all_instances_in_course('worksheetgrader', $course) as $activity) {
    echo html_writer::div(html_writer::link(new moodle_url('/mod/worksheetgrader/view.php', ['id' => $activity->coursemodule]), format_string($activity->name)), 'mb-2');
}
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
