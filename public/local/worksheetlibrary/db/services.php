<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_worksheetlibrary_save_native_draft' => [
        'classname' => 'local_worksheetlibrary\\external\\save_native_draft',
        'methodname' => 'execute',
        'description' => 'Persist a Native worksheet draft with optimistic revision protection.',
        'type' => 'write',
        'ajax' => true,
    ],
];
