<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class import_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];

        $mform->addElement('hidden', 'id', (int)($custom['id'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', (int)($custom['sessionid'] ?? 0));
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('hidden', 'contentaction', 'importfile');
        $mform->setType('contentaction', PARAM_ALPHA);

        $mform->addElement('filepicker', 'sourcefile', 'File phiếu học tập', null, [
            'accepted_types' => ['.docx', '.pdf', '.txt', '.png', '.jpg', '.jpeg', '.zip', '.rar', '.7z'],
            'maxbytes' => 512 * 1024 * 1024,
        ]);
        $mform->addRule('sourcefile', null, 'required', null, 'client');
        $mform->addElement('select', 'mode', 'Chế độ nhận diện', [
            'auto' => 'Tự động',
            'cloze' => 'Cloze',
            'essay_table' => 'Bảng tự luận',
        ]);
        $mform->addElement('static', 'enginehint', '',
            '<div class="alert alert-secondary mb-0">File được gửi từ máy chủ Moodle sang Worksheet Engine bằng HTTPS. '
            . 'Nếu engine chưa cấu hình, bạn vẫn có thể nhập HTML thủ công ở biểu mẫu phía trên.</div>');
        $this->add_action_buttons(true, 'Chuyển đổi & xem trước');
    }
}
