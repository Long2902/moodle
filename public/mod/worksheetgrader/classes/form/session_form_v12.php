<?php
namespace mod_worksheetgrader\form;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
final class session_form_v12 extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id'); $mform->setType('id', PARAM_INT);
        $mform->addElement('text', 'name', 'Tên buổi', ['class'=>'form-control']); $mform->setType('name', PARAM_TEXT); $mform->addRule('name', null, 'required');
        $mform->addElement('date_time_selector', 'sessiondate', 'Ngày tổ chức');
        $mform->addElement('textarea', 'description', get_string('sessiondescription','mod_worksheetgrader'), ['rows'=>3]); $mform->setType('description', PARAM_TEXT);
        $mform->addElement('select', 'workmode', get_string('workmode','mod_worksheetgrader'), [
            'grouped'=>get_string('workmodegrouped','mod_worksheetgrader'),
            'individual'=>get_string('workmodeindividual','mod_worksheetgrader'),
        ]);
        $mform->addElement('text', 'maxpoints', 'Điểm tối đa'); $mform->setType('maxpoints', PARAM_FLOAT); $mform->addRule('maxpoints', null, 'numeric');
        $mform->addElement('date_time_selector', 'timeopen', 'Mở từ', ['optional'=>true]);
        $mform->addElement('date_time_selector', 'timeclose', 'Đóng lúc', ['optional'=>true]);
        $this->add_action_buttons(false, 'Tạo buổi & chọn phiếu');
    }
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['timeopen']) && !empty($data['timeclose']) && $data['timeclose'] <= $data['timeopen']) {
            $errors['timeclose'] = 'Thời gian đóng phải sau thời gian mở.';
        }
        if ((float)($data['maxpoints'] ?? 0) <= 0) { $errors['maxpoints'] = 'Điểm tối đa phải lớn hơn 0.'; }
        return $errors;
    }
}
