<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class preview_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'programid', get_string('program', 'local_coursepublisher'), service::program_options());
        $mform->addRule('programid', null, 'required', null, 'client');
        $mform->addElement('select', 'gradekey', get_string('targetgroupgrade', 'local_coursepublisher'), service::target_group_options());
        $mform->addElement('select', 'sourcetype', get_string('sourcetype', 'local_coursepublisher'), [
            'section' => get_string('sourcesection', 'local_coursepublisher'),
            'activity' => get_string('sourceactivity', 'local_coursepublisher'),
        ]);
        $mform->addElement('text', 'sourceid', get_string('sourceid', 'local_coursepublisher'), [
            'placeholder' => get_string('sourceidplaceholder', 'local_coursepublisher'),
        ]);
        $mform->setType('sourceid', PARAM_INT);
        $mform->addHelpButton('sourceid', 'sourceid', 'local_coursepublisher');
        $mform->addRule('sourceid', null, 'required', null, 'client');
        $mform->addElement('select', 'regionid', get_string('region', 'local_coursepublisher'), service::region_options());
        $mform->addElement('autocomplete', 'schoolid', get_string('school', 'local_coursepublisher'), service::school_options());
        $this->add_action_buttons(false, get_string('preview', 'local_coursepublisher'));
    }
}
