<?php
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_digieraoffice', get_string('pluginname', 'local_digieraoffice'));
    $ADMIN->add('localplugins', $settings);
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox('local_digieraoffice/enabled', get_string('enabled','local_digieraoffice'), get_string('enabled_desc','local_digieraoffice'), 0));
        $settings->add(new admin_setting_configtext('local_digieraoffice/documentserverurl', get_string('documentserverurl','local_digieraoffice'), get_string('documentserverurl_desc','local_digieraoffice'), '', PARAM_URL));
        $settings->add(new admin_setting_configpasswordunmask('local_digieraoffice/jwtsecret', get_string('jwtsecret','local_digieraoffice'), get_string('jwtsecret_desc','local_digieraoffice'), ''));
        $settings->add(new admin_setting_configduration('local_digieraoffice/timeout', get_string('timeout','local_digieraoffice'), get_string('timeout_desc','local_digieraoffice'), 30));
        $settings->add(new admin_setting_description('local_digieraoffice/healthlink', get_string('health','local_digieraoffice'), html_writer::link(new moodle_url('/local/digieraoffice/health.php'), get_string('health','local_digieraoffice'))));
    }
}
