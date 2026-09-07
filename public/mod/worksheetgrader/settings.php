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

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'mod_worksheetgrader/engineurl',
        get_string('engineurl', 'mod_worksheetgrader'),
        get_string('engineurl_desc', 'mod_worksheetgrader'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_worksheetgrader/enginesecret',
        get_string('enginesecret', 'mod_worksheetgrader'),
        get_string('enginesecret_desc', 'mod_worksheetgrader'),
        ''
    ));
    $settings->add(new admin_setting_configduration(
        'mod_worksheetgrader/enginetimeout',
        get_string('enginetimeout', 'mod_worksheetgrader'),
        get_string('enginetimeout_desc', 'mod_worksheetgrader'),
        120
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/allowtemporaryteams',
        get_string('allowtemporaryteams', 'mod_worksheetgrader'),
        get_string('allowtemporaryteams_desc', 'mod_worksheetgrader'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_worksheetgrader/noticeheading',
        get_string('noticeheading', 'mod_worksheetgrader'),
        get_string('noticeheading_desc', 'mod_worksheetgrader')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/showguidancenotices',
        get_string('showguidancenotices', 'mod_worksheetgrader'),
        get_string('showguidancenotices_desc', 'mod_worksheetgrader'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/showdocspacestatus',
        get_string('showdocspacestatus', 'mod_worksheetgrader'),
        get_string('showdocspacestatus_desc', 'mod_worksheetgrader'),
        0
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/showerrornotices',
        get_string('showerrornotices', 'mod_worksheetgrader'),
        get_string('showerrornotices_desc', 'mod_worksheetgrader'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'mod_worksheetgrader/docspaceheading',
        get_string('docspaceheading', 'mod_worksheetgrader'),
        get_string('docspaceheading_desc', 'mod_worksheetgrader')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/docspaceenabled',
        get_string('docspaceenabled', 'mod_worksheetgrader'),
        get_string('docspaceenabled_desc', 'mod_worksheetgrader'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'mod_worksheetgrader/docspaceurl',
        get_string('docspaceurl', 'mod_worksheetgrader'),
        get_string('docspaceurl_desc', 'mod_worksheetgrader'),
        '',
        PARAM_URL
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_worksheetgrader/docspaceapikey',
        get_string('docspaceapikey', 'mod_worksheetgrader'),
        get_string('docspaceapikey_desc', 'mod_worksheetgrader'),
        ''
    ));
    $settings->add(new admin_setting_configselect(
        'mod_worksheetgrader/docspaceworkspacemode',
        get_string('docspaceworkspacemode', 'mod_worksheetgrader'),
        get_string('docspaceworkspacemode_desc', 'mod_worksheetgrader'),
        'sharedfolder',
        [
            'sharedfolder' => get_string('docspaceworkspacemode_shared', 'mod_worksheetgrader'),
            'isolatedrooms' => get_string('docspaceworkspacemode_isolated', 'mod_worksheetgrader'),
        ]
    ));
    $settings->add(new admin_setting_configtext(
        'mod_worksheetgrader/docspacefolderid',
        get_string('docspacefolderid', 'mod_worksheetgrader'),
        get_string('docspacefolderid_desc', 'mod_worksheetgrader'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configpasswordunmask(
        'mod_worksheetgrader/docspacerequesttoken',
        get_string('docspacerequesttoken', 'mod_worksheetgrader'),
        get_string('docspacerequesttoken_desc', 'mod_worksheetgrader'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'mod_worksheetgrader/docspacesdkversion',
        get_string('docspacesdkversion', 'mod_worksheetgrader'),
        get_string('docspacesdkversion_desc', 'mod_worksheetgrader'),
        'auto',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configduration(
        'mod_worksheetgrader/docspacetimeout',
        get_string('docspacetimeout', 'mod_worksheetgrader'),
        get_string('docspacetimeout_desc', 'mod_worksheetgrader'),
        60
    ));
    $settings->add(new admin_setting_description(
        'mod_worksheetgrader/docspacetest',
        get_string('docspacetest', 'mod_worksheetgrader'),
        html_writer::link(new moodle_url('/mod/worksheetgrader/docspace_test.php'),
            get_string('docspacetestlink', 'mod_worksheetgrader'), ['class' => 'btn btn-secondary'])
    ));

    $settings->add(new admin_setting_heading(
        'mod_worksheetgrader/v12heading',
        get_string('v12heading', 'mod_worksheetgrader'),
        get_string('v12heading_desc', 'mod_worksheetgrader')
    ));
    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/v12enabled',
        get_string('v12enabled', 'mod_worksheetgrader'),
        get_string('v12enabled_desc', 'mod_worksheetgrader'),
        0
    ));
    $settings->add(new admin_setting_configtext(
        'mod_worksheetgrader/v12pilotcourseids',
        get_string('v12pilotcourseids', 'mod_worksheetgrader'),
        get_string('v12pilotcourseids_desc', 'mod_worksheetgrader'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'mod_worksheetgrader/backuprestoreverified',
        get_string('backuprestoreverified', 'mod_worksheetgrader'),
        get_string('backuprestoreverified_desc', 'mod_worksheetgrader'),
        0
    ));
}
