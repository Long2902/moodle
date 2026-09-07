<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\form;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');

class session_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];

        $mform->addElement('hidden', 'id', (int)($custom['id'] ?? 0));
        $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'name', 'Tên buổi/lượt', ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addElement('date_time_selector', 'sessiondate', 'Ngày/giờ dạy', ['optional' => false]);

        $mform->addElement('select', 'contentmode', get_string('docspacecontentmode', 'mod_worksheetgrader'), [
            'html' => get_string('docspacecontentmode_html', 'mod_worksheetgrader'),
            'docspace' => get_string('docspacecontentmode_docspace', 'mod_worksheetgrader'),
        ]);
        $mform->setDefault('contentmode', 'html');
        if (!\mod_worksheetgrader\service\docspace_manager::configured()) {
            $mform->addElement('static', 'docspacenote', '',
                '<span class="text-muted">DocSpace chỉ hoạt động sau khi quản trị viên cấu hình URL và API key.</span>');
        }

        $workmodes = [];
        $workmodes[] = $mform->createElement('radio', 'workmode', '', 'Có chia nhóm', 'grouped');
        $workmodes[] = $mform->createElement('radio', 'workmode', '', 'Không chia nhóm — mỗi học sinh làm cá nhân', 'individual');
        $mform->addGroup($workmodes, 'workmodegroup', 'Hình thức làm bài', ['<br>'], false);
        $mform->setDefault('workmode', 'grouped');
        $mform->addHelpButton('workmodegroup', 'workmode', 'mod_worksheetgrader');

        $mform->addElement('select', 'teammode', 'Nguồn nhóm', [
            'temporary' => 'Nhóm tạm theo buổi',
            'moodlegroups' => 'Nạp Groups Moodle rồi chỉnh',
        ]);
        $mform->setDefault('teammode', 'temporary');
        $mform->hideIf('teammode', 'workmode', 'eq', 'individual');

        $mform->addElement('static', 'individualnote', '',
            '<span class="text-muted">Ở chế độ cá nhân, hệ thống không tạo sẵn hàng chục nhóm. ' .
            'Hồ sơ bài làm cá nhân chỉ được tạo khi học sinh mở phiếu lần đầu.</span>');
        $mform->hideIf('individualnote', 'workmode', 'neq', 'individual');

        $mform->addElement('text', 'maxpoints', 'Điểm tối đa buổi', ['size' => 8]);
        $mform->setType('maxpoints', PARAM_FLOAT);
        $mform->setDefault('maxpoints', 10);
        $mform->addRule('maxpoints', null, 'numeric', null, 'client');
        $mform->addElement('date_time_selector', 'timeopen', 'Mở từ', ['optional' => true]);
        $mform->addElement('date_time_selector', 'timeclose', 'Đóng lúc', ['optional' => true]);
        $this->add_action_buttons(true, 'Tạo buổi & nhập nội dung');
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!in_array((string)($data['contentmode'] ?? ''), ['html', 'docspace'], true)) {
            $errors['contentmode'] = get_string('invalidparameter');
        } else if (($data['contentmode'] ?? '') === 'docspace' &&
                !\mod_worksheetgrader\service\docspace_manager::configured()) {
            $errors['contentmode'] = get_string('docspacenotconfigured', 'mod_worksheetgrader');
        }
        if (!in_array((string)($data['workmode'] ?? ''), ['grouped', 'individual'], true)) {
            $errors['workmodegroup'] = 'Hãy chọn có chia nhóm hoặc không chia nhóm.';
        }
        if ((float)($data['maxpoints'] ?? 0) <= 0) {
            $errors['maxpoints'] = 'Điểm tối đa phải lớn hơn 0.';
        }
        $timeopen = (int)($data['timeopen'] ?? 0);
        $timeclose = (int)($data['timeclose'] ?? 0);
        if ($timeopen && $timeclose && $timeclose <= $timeopen) {
            $errors['timeclose'] = 'Thời gian đóng phải sau thời gian mở.';
        }
        return $errors;
    }
}
