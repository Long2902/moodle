<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_digieramedia_search_media' => [
        'classname' => 'local_digieramedia\\external\\search_media',
        'methodname' => 'execute',
        'description' => 'Search DIGIERA Media visible in an editor context.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_create_reference' => [
        'classname' => 'local_digieramedia\\external\\create_reference',
        'methodname' => 'execute',
        'description' => 'Create one draft DIGIERA reference for TinyMCE insertion.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
