<?php
defined('MOODLE_INTERNAL') || die();
class backup_worksheetgrader_activity_structure_step extends backup_activity_structure_step {
 protected function define_structure() {
  $userinfo=$this->get_setting_value('userinfo');
  $activity=new backup_nested_element('worksheetgrader',['id'],['name','intro','introformat','contenthtml','grade','aggregation','allowgroup','allowadjust','representative','completiononsubmit','completionongrade','timecreated','timemodified']);
  $sessions=new backup_nested_element('sessions');
  $session=new backup_nested_element('session',['id'],['name','sessiondate','status','teammode','contentmode','groupingid','maxpoints','contenthtml','contentformat','sourcefilename','metadatajson','membershiplocked','timeopen','timeclose','libraryitemid','libraryversionid','worksheetkind','worksheethash','migrationstatus','setupcomplete','timecreated','timemodified']);
  $teams=new backup_nested_element('teams');$team=new backup_nested_element('team',['id'],['name','representativeuserid','sourcegroupid','locked','timecreated','timemodified']);
  $members=new backup_nested_element('members');$member=new backup_nested_element('member',['id'],['userid','role','status','addedby','timecreated']);
  $attempts=new backup_nested_element('attempts');$attempt=new backup_nested_element('attempt',['id'],['attemptnumber','status','submitteruserid','answersjson','submissionhtml','membersnapshot','groupgrade','groupfeedback','gradedby','timestarted','timemodified','timesubmitted','timegraded','version','officerevision','officefilename']);
  $grades=new backup_nested_element('grades');$grade=new backup_nested_element('grade',['id'],['userid','groupgrade','adjustment','finalgrade','feedback','published','publishedby','timecreated','timemodified']);
  $activity->add_child($sessions);$sessions->add_child($session);$session->add_child($teams);$teams->add_child($team);$team->add_child($members);$members->add_child($member);$team->add_child($attempts);$attempts->add_child($attempt);$attempt->add_child($grades);$grades->add_child($grade);
  $activity->set_source_table('worksheetgrader',['id'=>backup::VAR_ACTIVITYID]);
  $session->set_source_table('wsg_session',['worksheetgraderid'=>backup::VAR_PARENTID]);
  if($userinfo){
   $team->set_source_table('wsg_team',['sessionid'=>backup::VAR_PARENTID]);$member->set_source_table('wsg_member',['teamid'=>backup::VAR_PARENTID]);$attempt->set_source_table('wsg_attempt',['teamid'=>backup::VAR_PARENTID]);$grade->set_source_table('wsg_grade',['attemptid'=>backup::VAR_PARENTID]);
   $team->annotate_ids('user','representativeuserid');$member->annotate_ids('user','userid');$member->annotate_ids('user','addedby');$attempt->annotate_ids('user','submitteruserid');$attempt->annotate_ids('user','gradedby');$grade->annotate_ids('user','userid');$grade->annotate_ids('user','publishedby');
  }
  // Portable session content is always included; legacy remote DocSpace IDs are intentionally absent.
  $session->annotate_files('mod_worksheetgrader','source','id');$session->annotate_files('mod_worksheetgrader','docspacetemplate','id');$session->annotate_files('mod_worksheetgrader','officetemplate','id');
  if($userinfo){$attempt->annotate_files('mod_worksheetgrader','attemptimage','id');$attempt->annotate_files('mod_worksheetgrader','docspacesnapshot','id');$attempt->annotate_files('mod_worksheetgrader','officesubmission','id');}
  return $this->prepare_activity_structure($activity);
 }
}
