<?php
namespace local_coursepublisher\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class discovery_container_form extends \moodleform {
    public function definition(): void {
        $mform=$this->_form;
        $mform->addElement('hidden','formkind','container'); $mform->setType('formkind',PARAM_ALPHA);
        $mform->addElement('hidden','id',0); $mform->setType('id',PARAM_INT);
        $mform->addElement('text','name',get_string('containerrulename','local_coursepublisher'),['size'=>42]); $mform->setType('name',PARAM_TEXT); $mform->addRule('name',null,'required',null,'client');
        $mform->addElement('select','matchtype',get_string('matchtype','local_coursepublisher'),['contains'=>get_string('matchcontains','local_coursepublisher'),'starts_with'=>get_string('matchstartswith','local_coursepublisher'),'regex'=>get_string('matchregex','local_coursepublisher')]);
        $mform->addElement('textarea','pattern',get_string('pattern','local_coursepublisher'),['rows'=>2,'cols'=>55]); $mform->setType('pattern',PARAM_RAW_TRIMMED); $mform->addRule('pattern',null,'required',null,'client');
        $mform->addElement('text','sortorder',get_string('sortorder','local_coursepublisher'),['size'=>8]); $mform->setType('sortorder',PARAM_INT); $mform->setDefault('sortorder',0);
        $mform->addElement('advcheckbox','enabled',get_string('enabled','local_coursepublisher')); $mform->setDefault('enabled',1);
        $this->add_action_buttons(true,get_string('save','local_coursepublisher'));
    }
}
