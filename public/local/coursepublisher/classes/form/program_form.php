<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class program_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'code', get_string('programcode', 'local_coursepublisher'), [
            'placeholder' => get_string('programcodeplaceholder', 'local_coursepublisher'),
        ]);
        $mform->setType('code', PARAM_ALPHANUMEXT);
        $mform->addRule('code', null, 'required', null, 'client');
        $mform->addElement('text', 'name', get_string('programname', 'local_coursepublisher'), [
            'size' => 50,
            'placeholder' => get_string('programnameplaceholder', 'local_coursepublisher'),
        ]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('autocomplete', 'discoveryrootcategoryid', get_string('discoveryroot', 'local_coursepublisher'), service::category_options());
        $mform->addHelpButton('discoveryrootcategoryid', 'discoveryroot', 'local_coursepublisher');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_coursepublisher'));
        $mform->setDefault('enabled', 1);
        $this->add_action_buttons(true, get_string('save', 'local_coursepublisher'));
    }
}
