<?php
namespace local_coursepublisher\form;
use local_coursepublisher\local\service;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class discovery_form extends \moodleform {
    public function definition(): void {
        $mform=$this->_form;
        $mform->addElement('select','programid',get_string('program','local_coursepublisher'),service::program_options());
        $mform->addRule('programid',null,'required',null,'client');
        $mform->addElement('autocomplete','targetgroupids',get_string('targetgroupgrade','local_coursepublisher'),service::target_group_id_options(),['multiple'=>true]);
        $mform->addHelpButton('targetgroupids','discoverygroups','local_coursepublisher');
        $mform->addElement('autocomplete','containerids',get_string('discoverycontainers','local_coursepublisher'),service::discovery_container_options(),['multiple'=>true]);
        $mform->addHelpButton('containerids','discoverycontainers','local_coursepublisher');
        $mform->addElement('advcheckbox','showunmatched',get_string('discoveryshowunmatched','local_coursepublisher'));
        $mform->setDefault('showunmatched', 0);
        $this->add_action_buttons(false,get_string('scantopology','local_coursepublisher'));
    }
}
