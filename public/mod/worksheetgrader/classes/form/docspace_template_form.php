<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Teacher form for creating a DocSpace worksheet template. */
final class docspace_template_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];
        $mform->addElement('hidden', 'id', (int)($custom['id'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', (int)($custom['sessionid'] ?? 0));
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('hidden', 'contentaction', 'docspace');
        $mform->setType('contentaction', PARAM_ALPHA);

        $mform->addElement('select', 'docspaceoperation', get_string('docspaceoperation', 'mod_worksheetgrader'), [
            'upload' => get_string('docspaceuploadtemplate', 'mod_worksheetgrader'),
            'blank' => get_string('docspaceblanktemplate', 'mod_worksheetgrader'),
        ]);
        $mform->setDefault('docspaceoperation', 'upload');
        $mform->addElement('filepicker', 'docspacefile', get_string('docspacefile', 'mod_worksheetgrader'), null, [
            'accepted_types' => ['.docx', '.xlsx', '.pptx', '.pdf'],
            'maxbytes' => min(10 * 1024 * 1024, (int)get_max_upload_file_size()),
            'maxfiles' => 1,
        ]);
        $mform->hideIf('docspacefile', 'docspaceoperation', 'neq', 'upload');
        $mform->addElement('text', 'docspacefilename', get_string('docspacefilename', 'mod_worksheetgrader'), [
            'size' => 48,
            'placeholder' => 'Phieu-hoc-tap.docx',
        ]);
        $mform->setType('docspacefilename', PARAM_FILE);
        $mform->setDefault('docspacefilename', 'Phieu-hoc-tap.docx');
        $mform->hideIf('docspacefilename', 'docspaceoperation', 'neq', 'blank');
        $mform->addElement('advcheckbox', 'docspaceconfirmreplace', get_string('docspaceconfirmreplace', 'mod_worksheetgrader'));
        $mform->addRule('docspaceconfirmreplace', null, 'required', null, 'client');
        $this->add_action_buttons(false, get_string('docspacecreatetemplate', 'mod_worksheetgrader'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $operation = (string)($data['docspaceoperation'] ?? '');
        if (!in_array($operation, ['upload', 'blank'], true)) {
            $errors['docspaceoperation'] = get_string('invalidparameter');
        }
        if ($operation === 'upload' && empty($data['docspacefile'])) {
            $errors['docspacefile'] = get_string('required');
        }
        if ($operation === 'blank' && trim((string)($data['docspacefilename'] ?? '')) === '') {
            $errors['docspacefilename'] = get_string('required');
        }
        return $errors;
    }
}
