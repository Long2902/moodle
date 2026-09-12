<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class school_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('select', 'regionid', get_string('region', 'local_coursepublisher'), service::region_options());
        $mform->addRule('regionid', null, 'required', null, 'client');
        $mform->addElement('text', 'code', get_string('schoolcode', 'local_coursepublisher'), [
            'placeholder' => get_string('schoolcodeplaceholder', 'local_coursepublisher'),
        ]);
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', null, 'required', null, 'client');
        $mform->addElement('text', 'name', get_string('schoolname', 'local_coursepublisher'), [
            'size' => 60,
            'placeholder' => get_string('schoolnameplaceholder', 'local_coursepublisher'),
        ]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('autocomplete', 'categoryid', get_string('category', 'local_coursepublisher'), service::category_options());
        $mform->addHelpButton('categoryid', 'schoolcategory', 'local_coursepublisher');
        $mform->addRule('categoryid', null, 'required', null, 'client');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_coursepublisher'));
        $mform->setDefault('enabled', 1);
        $this->add_action_buttons(true, get_string('save', 'local_coursepublisher'));
    }
}
