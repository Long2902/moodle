<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_worksheetlibrary';
$plugin->version = 2026090701;
$plugin->requires = 2025100600;
$plugin->maturity = MATURITY_RC;
$plugin->release = '1.0.0-preview0.1';
$plugin->dependencies = [
    'local_digieraoffice' => 2026090501,
    'local_digieranative' => 2026090501,
];
