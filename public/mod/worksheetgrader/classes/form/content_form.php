<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

/** Form used to visually design or edit the worksheet HTML stored for one session. */
class content_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];

        $mform->addElement('hidden', 'id', (int)($custom['id'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'sessionid', (int)($custom['sessionid'] ?? 0));
        $mform->setType('sessionid', PARAM_INT);
        $mform->addElement('hidden', 'contentaction', 'savehtml');
        $mform->setType('contentaction', PARAM_ALPHA);

        // The visual designer synchronises into this textarea before form submit.
        // It stays visible as a server-side fallback until the AMD editor initialises.
        $mform->addElement('textarea', 'contenthtml', '', [
            'rows' => 12,
            'class' => 'wsg-designer-source form-control',
            'aria-label' => 'Mã HTML dự phòng của phiếu',
        ]);
        $mform->setType('contenthtml', PARAM_RAW);

        $mform->addElement('html', '<div id="wsg-worksheet-designer" class="wsg-designer-host"></div>');
        $mform->addElement('static', 'designerhelp', '',
            '<div class="alert alert-info mb-0"><strong>Trình thiết kế phiếu:</strong> chèn bảng, checkbox, '
            . 'ô trả lời ngắn/dài, trường thông tin Moodle và vùng nộp ảnh trực tiếp. '
            . 'Có thể chuyển sang tab “Mã HTML” khi cần chỉnh nâng cao.</div>');

        $this->add_action_buttons(false, 'Lưu phiếu & xem trước');
    }
}
