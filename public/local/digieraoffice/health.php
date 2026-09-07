<?php
require('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_url('/local/digieraoffice/health.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('health','local_digieraoffice'));
$PAGE->set_heading(get_string('health','local_digieraoffice'));
$config = get_config('local_digieraoffice');
$result = ['enabled' => !empty($config->enabled), 'configured' => !empty($config->documentserverurl) && !empty($config->jwtsecret), 'reachable' => false, 'httpcode' => 0];
if ($result['configured']) {
    require_once($CFG->libdir . '/filelib.php');
    $curl = new curl();
    $url = rtrim((string)$config->documentserverurl, '/') . '/healthcheck';
    $body = $curl->get($url, [], ['CURLOPT_TIMEOUT' => max(5,(int)($config->timeout ?? 30))]);
    $info = $curl->get_info();
    $result['httpcode'] = (int)($info['http_code'] ?? 0);
    $result['reachable'] = $result['httpcode'] === 200 && trim((string)$body) !== '';
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('health','local_digieraoffice'));
echo html_writer::start_tag('div',['class'=>'dgoffice-health card card-body']);
echo html_writer::tag('p','Gateway: '.($result['enabled']?'ON':'OFF'));
echo html_writer::tag('p','Config: '.($result['configured']?get_string('configured','local_digieraoffice'):get_string('notconfigured','local_digieraoffice')));
echo html_writer::tag('p','Health HTTP: '.(int)$result['httpcode']);
echo html_writer::end_tag('div');
echo $OUTPUT->footer();
