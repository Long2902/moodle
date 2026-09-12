<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class target_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'programid', get_string('program', 'local_coursepublisher'), service::program_options());
        $mform->addRule('programid', null, 'required', null, 'client');
        $mform->addElement('autocomplete', 'schoolid', get_string('school', 'local_coursepublisher'), service::school_options());
        $mform->addRule('schoolid', null, 'required', null, 'client');
        $mform->addElement('select', 'gradekey', get_string('targetgroupgrade', 'local_coursepublisher'), service::target_group_options());
        $mform->addElement('autocomplete', 'courseid', get_string('targetcourse', 'local_coursepublisher'), service::course_options());
        $mform->addHelpButton('courseid', 'targetcourse', 'local_coursepublisher');
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_coursepublisher'));
        $mform->setDefault('enabled', 1);
        $this->add_action_buttons(true, get_string('save', 'local_coursepublisher'));
    }
}
