<?php
namespace local_coursepublisher\form;
use local_coursepublisher\local\service;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
class discovery_rule_form extends \moodleform {
    public function definition(): void {
        $mform=$this->_form;
        $mform->addElement('hidden','formkind','rule'); $mform->setType('formkind',PARAM_ALPHA);
        $mform->addElement('hidden','id',0); $mform->setType('id',PARAM_INT);
        $mform->addElement('select','targetgroupid',get_string('targetgroupgrade','local_coursepublisher'),service::target_group_id_options());
        $mform->addRule('targetgroupid',null,'required',null,'client');
        $mform->addElement('text','name',get_string('rulename','local_coursepublisher'),['size'=>42]); $mform->setType('name',PARAM_TEXT); $mform->addRule('name',null,'required',null,'client');
        $mform->addElement('select','fieldname',get_string('rulefield','local_coursepublisher'),['fullname'=>get_string('coursefullname','local_coursepublisher'),'shortname'=>get_string('courseshortname','local_coursepublisher')]);
        $mform->addElement('select','matchtype',get_string('matchtype','local_coursepublisher'),['contains'=>get_string('matchcontains','local_coursepublisher'),'starts_with'=>get_string('matchstartswith','local_coursepublisher'),'regex'=>get_string('matchregex','local_coursepublisher')]);
        $mform->addElement('textarea','pattern',get_string('pattern','local_coursepublisher'),['rows'=>2,'cols'=>55]); $mform->setType('pattern',PARAM_RAW_TRIMMED); $mform->addRule('pattern',null,'required',null,'client');
        $mform->addElement('text','priority',get_string('priority','local_coursepublisher'),['size'=>8]); $mform->setType('priority',PARAM_INT); $mform->setDefault('priority',100);
        $mform->addElement('text','sortorder',get_string('sortorder','local_coursepublisher'),['size'=>8]); $mform->setType('sortorder',PARAM_INT); $mform->setDefault('sortorder',0);
        $mform->addElement('advcheckbox','enabled',get_string('enabled','local_coursepublisher')); $mform->setDefault('enabled',1);
        $this->add_action_buttons(true,get_string('save','local_coursepublisher'));
    }
}
