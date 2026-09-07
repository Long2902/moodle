<?php
defined('MOODLE_INTERNAL') || die();
$capabilities = [
 'local/worksheetlibrary:view' => ['captype'=>'read','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['user'=>CAP_ALLOW,'editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
 'local/worksheetlibrary:manage' => ['captype'=>'write','riskbitmask'=>RISK_DATALOSS,'contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
];
