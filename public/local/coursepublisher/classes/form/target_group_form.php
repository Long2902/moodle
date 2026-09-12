<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class target_group_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'programid', get_string('program', 'local_coursepublisher'), service::program_options(false));
        $mform->addRule('programid', null, 'required', null, 'client');
        $mform->addElement('text', 'groupkey', get_string('targetgroupkey', 'local_coursepublisher'), ['size' => 24]);
        $mform->setType('groupkey', PARAM_ALPHANUMEXT);
        $mform->addRule('groupkey', null, 'required', null, 'client');
        $mform->addElement('text', 'name', get_string('targetgroupname', 'local_coursepublisher'), ['size' => 50]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'local_coursepublisher'), ['size' => 8]);
        $mform->setType('sortorder', PARAM_INT);
        $mform->setDefault('sortorder', 0);
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_coursepublisher'));
        $mform->setDefault('enabled', 1);
        $this->add_action_buttons(true, get_string('save', 'local_coursepublisher'));
    }
}
