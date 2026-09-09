<?php

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'filter_digieramedia';
$plugin->version = 2026090902;
$plugin->requires = 2025100600;
$plugin->maturity = MATURITY_RC;
$plugin->release = '1.0.0-rc1-fasttrack-lifecycle';
$plugin->dependencies = [
    'local_digieramedia' => 2026090902,
];
