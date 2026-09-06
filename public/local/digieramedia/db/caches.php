<?php

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'media' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'ttl' => 300,
    ],
];
