<?php

namespace local_coursepublisher\form;

use local_coursepublisher\local\job_service;
use local_coursepublisher\local\service;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Source and placement setup for multi-target fan-out. */
final class batch_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('select', 'programid', get_string('program', 'local_coursepublisher'), service::program_options());
        $mform->addRule('programid', null, 'required', null, 'client');
        $mform->addElement('select', 'gradekey', get_string('targetgroupgrade', 'local_coursepublisher'), service::target_group_options());
        $mform->addElement('select', 'sourcetype', get_string('sourcetype', 'local_coursepublisher'), [
            'activity' => get_string('sourceactivity', 'local_coursepublisher'),
            'section' => get_string('sourcesection', 'local_coursepublisher'),
        ]);
        $mform->addElement('text', 'sourceid', get_string('sourceid', 'local_coursepublisher'));
        $mform->setType('sourceid', PARAM_INT);
        $mform->addRule('sourceid', null, 'required', null, 'client');
        $mform->addElement('select', 'publishmode', get_string('batchpublishmode', 'local_coursepublisher'), [
            job_service::MODE_ACTIVITY_GENERIC_PLACEMENT => get_string('batchmode_activity', 'local_coursepublisher'),
            job_service::MODE_SECTION_GENERIC_PEER => get_string('batchmode_section', 'local_coursepublisher'),
            job_service::MODE_SUBSECTION_TREE_PLACEMENT => get_string('batchmode_subsection', 'local_coursepublisher'),
        ]);
        $mform->addElement('select', 'placementmode', get_string('batchplacementmode', 'local_coursepublisher'), [
            'auto' => get_string('batchplacementauto', 'local_coursepublisher'),
            'override' => get_string('batchplacementoverride', 'local_coursepublisher'),
        ]);
        $mform->addElement('text', 'manual_section_name', get_string('batchoverridesection', 'local_coursepublisher'));
        $mform->setType('manual_section_name', PARAM_TEXT);
        $mform->hideIf('manual_section_name', 'placementmode', 'neq', 'override');
        $mform->addElement('select', 'manual_position', get_string('batchoverrideposition', 'local_coursepublisher'), [
            'start' => 'Start', 'end' => 'End', 'before' => 'Before', 'after' => 'After',
        ]);
        $mform->hideIf('manual_position', 'placementmode', 'neq', 'override');
        $mform->addElement('text', 'manual_anchor_name', get_string('batchoverrideanchor', 'local_coursepublisher'));
        $mform->setType('manual_anchor_name', PARAM_TEXT);
        $mform->hideIf('manual_anchor_name', 'placementmode', 'neq', 'override');
        $mform->addElement('text', 'manual_anchor_modname', get_string('batchoverrideanchormod', 'local_coursepublisher'));
        $mform->setType('manual_anchor_modname', PARAM_ALPHANUMEXT);
        $mform->hideIf('manual_anchor_modname', 'placementmode', 'neq', 'override');
        $this->add_action_buttons(false, get_string('batchloadtargets', 'local_coursepublisher'));
    }
}
