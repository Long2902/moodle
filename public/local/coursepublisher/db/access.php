<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/coursepublisher:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/coursepublisher:configureprograms' => [
        'captype' => 'write',
        'riskbitmask' => RISK_CONFIG,
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/coursepublisher:configuretopology' => [
        'captype' => 'write',
        'riskbitmask' => RISK_CONFIG,
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/coursepublisher:bindcourses' => [
        'captype' => 'write',
        'riskbitmask' => RISK_CONFIG,
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    'local/coursepublisher:preview' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
    // Reserved for CASE 2 enqueue/mutation operations. CASE 1 never requires this capability.
    'local/coursepublisher:publish' => [
        'captype' => 'write',
        'riskbitmask' => RISK_DATALOSS,
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => ['manager' => CAP_ALLOW],
    ],
];
