<?php
defined('MOODLE_INTERNAL') || die();
$functions = [
 'mod_worksheetgrader_save_attempt' => ['classname'=>'mod_worksheetgrader\\external\\save_attempt','description'=>'Autosave a team attempt.','type'=>'write','ajax'=>true,'capabilities'=>'mod/worksheetgrader:submit'],
 'mod_worksheetgrader_save_team_layout' => ['classname'=>'mod_worksheetgrader\\external\\save_team_layout','description'=>'Save V12 team layout and return canonical state.','type'=>'write','ajax'=>true,'capabilities'=>'mod/worksheetgrader:manageteams'],
 'mod_worksheetgrader_select_worksheet' => ['classname'=>'mod_worksheetgrader\\external\\select_worksheet','description'=>'Attach a frozen published library version to a session.','type'=>'write','ajax'=>true,'capabilities'=>'mod/worksheetgrader:manageactivity'],
 'mod_worksheetgrader_request_office_save' => ['classname'=>'mod_worksheetgrader\\external\\request_office_save','description'=>'Request ONLYOFFICE force-save for an attempt.','type'=>'write','ajax'=>true,'capabilities'=>'mod/worksheetgrader:submit'],
 'mod_worksheetgrader_office_save_status' => ['classname'=>'mod_worksheetgrader\\external\\office_save_status','description'=>'Read current ONLYOFFICE save request state.','type'=>'read','ajax'=>true,'capabilities'=>'mod/worksheetgrader:submit'],
];
