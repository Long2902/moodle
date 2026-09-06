<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/digieramedia:view' => ['captype'=>'read','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:insert' => ['captype'=>'write','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:upload' => ['captype'=>'write','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:editown' => ['captype'=>'write','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:editall' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:replace' => ['captype'=>'write','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:trashown' => ['captype'=>'write','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:trash' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:restore' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:viewusage' => ['captype'=>'read','contextlevel'=>CONTEXT_COURSE,'archetypes'=>['editingteacher'=>CAP_ALLOW,'manager'=>CAP_ALLOW]],
    'local/digieramedia:managevisibility' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:overridepath' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:viewall' => ['captype'=>'read','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:manageversions' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:migrate' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
    'local/digieramedia:purge' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>[]],
    'local/digieramedia:manage' => ['captype'=>'write','contextlevel'=>CONTEXT_SYSTEM,'archetypes'=>['manager'=>CAP_ALLOW]],
];
