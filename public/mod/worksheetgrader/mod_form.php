<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_worksheetgrader_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('name', 'mod_worksheetgrader'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('editor', 'contenthtml_editor', get_string('contenthtml', 'mod_worksheetgrader'), null, ['maxfiles' => 0]);
        $mform->addHelpButton('contenthtml_editor', 'contenthtml', 'mod_worksheetgrader');
        $mform->setDefault('contenthtml_editor', ['text' => '<p>Nhập nội dung phiếu hoặc dùng nút Nhập phiếu sau khi tạo hoạt động.</p>', 'format' => FORMAT_HTML]);

        $mform->addElement('text', 'grade', get_string('grade', 'mod_worksheetgrader'), ['size' => 8]);
        $mform->setType('grade', PARAM_FLOAT);
        $mform->setDefault('grade', 10);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $mform->addElement('select', 'aggregation', get_string('aggregation', 'mod_worksheetgrader'), [
            'average' => get_string('aggregation_average', 'mod_worksheetgrader'),
            'best' => get_string('aggregation_best', 'mod_worksheetgrader'),
            'latest' => get_string('aggregation_latest', 'mod_worksheetgrader'),
            'sum' => get_string('aggregation_sum', 'mod_worksheetgrader'),
        ]);
        $mform->addElement('advcheckbox', 'allowgroup', get_string('allowgroup', 'mod_worksheetgrader'));
        $mform->setDefault('allowgroup', 1);
        $mform->addElement('advcheckbox', 'allowadjust', get_string('allowadjust', 'mod_worksheetgrader'));
        $mform->setDefault('allowadjust', 1);
        $mform->addElement('select', 'representative', get_string('representative', 'mod_worksheetgrader'), [
            'anymember' => get_string('representative_anymember', 'mod_worksheetgrader'),
            'fixed' => get_string('representative_fixed', 'mod_worksheetgrader'),
        ]);
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function add_completion_rules() {
        $mform = $this->_form;
        $mform->addElement('advcheckbox', 'completiononsubmit', get_string('completiononsubmit', 'mod_worksheetgrader'));
        $mform->addElement('advcheckbox', 'completionongrade', get_string('completionongrade', 'mod_worksheetgrader'));
        $mform->setDefault('completionongrade', 1);
        return ['completiononsubmit', 'completionongrade'];
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completiononsubmit']) || !empty($data['completionongrade']);
    }

    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        if (isset($defaultvalues['contenthtml'])) {
            $defaultvalues['contenthtml_editor'] = ['text' => $defaultvalues['contenthtml'], 'format' => FORMAT_HTML];
        }
    }

    public function get_data() {
        $data = parent::get_data();
        if ($data && isset($data->contenthtml_editor)) {
            $data->contenthtml = $data->contenthtml_editor['text'];
            unset($data->contenthtml_editor);
        }
        return $data;
    }
}
