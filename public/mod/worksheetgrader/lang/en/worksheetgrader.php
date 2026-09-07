<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'Worksheet group grader';
$string['modulename'] = 'Worksheet group grader';
$string['modulenameplural'] = 'Worksheet group graders';
$string['pluginadministration'] = 'Worksheet grader administration';
$string['worksheetgrader:addinstance'] = 'Add a worksheet grader activity';
$string['worksheetgrader:view'] = 'View worksheet grader activity';
$string['worksheetgrader:submit'] = 'Work on and submit a team attempt';
$string['worksheetgrader:manageactivity'] = 'Manage worksheet content';
$string['worksheetgrader:managesessions'] = 'Manage teaching sessions';
$string['worksheetgrader:manageteams'] = 'Manage temporary teams';
$string['worksheetgrader:grade'] = 'Grade team attempts';
$string['worksheetgrader:publishgrades'] = 'Publish grades to the gradebook';
$string['worksheetgrader:viewreports'] = 'View grading reports';
$string['worksheetgrader:export'] = 'Export grading reports';
$string['engineurl'] = 'Worksheet engine URL';
$string['engineurl_desc'] = 'Base URL of the Python worksheet conversion engine, for example https://engine.example.edu.';
$string['enginesecret'] = 'Engine shared secret';
$string['enginesecret_desc'] = 'A long random secret shared with WORKSHEET_ENGINE_SHARED_SECRET on the Python server.';
$string['enginetimeout'] = 'Engine request timeout';
$string['enginetimeout_desc'] = 'Maximum time allowed for DOCX/PDF conversion.';
$string['allowtemporaryteams'] = 'Allow temporary teams';
$string['allowtemporaryteams_desc'] = 'Allow teachers to create a new team arrangement for each session without changing Moodle course groups.';
$string['name'] = 'Activity name';
$string['contenthtml'] = 'Default worksheet HTML';
$string['contenthtml_help'] = 'This content is copied into new sessions. Placeholders such as [[C1]] become student input fields.';
$string['grade'] = 'Maximum grade';
$string['aggregation'] = 'Session grade aggregation';
$string['aggregation_average'] = 'Average';
$string['aggregation_best'] = 'Best session';
$string['aggregation_latest'] = 'Latest graded session';
$string['aggregation_sum'] = 'Sum, capped at activity maximum';
$string['allowgroup'] = 'Enable team attempts';
$string['allowadjust'] = 'Allow individual grade adjustments';
$string['representative'] = 'Who may edit the shared attempt';
$string['representative_anymember'] = 'Any current team member';
$string['representative_fixed'] = 'Only the selected representative';
$string['completiononsubmit'] = 'Complete for each member when the team submits';
$string['completionongrade'] = 'Complete for each member when a grade is published';
$string['sessions'] = 'Sessions';
$string['teams'] = 'Teams';
$string['grading'] = 'Grading';
$string['reports'] = 'Reports';
$string['importworksheet'] = 'Import worksheet';
$string['studentwork'] = 'Team work';
$string['privacy:metadata:wsg_attempt'] = 'Stores shared team answers and submission history.';
$string['privacy:metadata:wsg_attempt:submitteruserid'] = 'The account which submitted on behalf of the team.';
$string['privacy:metadata:wsg_attempt:answersjson'] = 'Answers entered by the team.';
$string['privacy:metadata:wsg_grade'] = 'Stores group grades and individual adjustments.';
$string['privacy:metadata:wsg_grade:userid'] = 'The learner receiving the grade.';
$string['privacy:metadata:wsg_grade:finalgrade'] = 'The final grade published for the learner.';
$string['privacy:metadata:wsg_log'] = 'Stores an audit trail of teacher and learner actions.';
$string['privacy:metadata:wsg_log:actorid'] = 'The user who performed the recorded action.';

$string['cannotopenwithoutcontent'] = 'The session cannot be opened before worksheet content is added.';
$string['cannotopenwithoutteams'] = 'A session cannot be opened until teams have been created.';
$string['teamslocked'] = 'The team membership is locked.';
$string['duplicateuserteam'] = 'A learner appears in more than one team.';
$string['cannotreplaceteamswithattempts'] = 'The team layout cannot be replaced after attempts exist.';
$string['attemptnoteditable'] = 'The attempt is no longer editable.';
$string['attemptconflict'] = 'The attempt was changed on another device. Reload the page.';
$string['attemptalreadysubmitted'] = 'The attempt has already been submitted.';
$string['notassignedteam'] = 'You have not been assigned to a team for this session.';
$string['enginenotconfigured'] = 'The Worksheet Engine is not configured in Site administration.';
$string['enginehttp'] = 'The Worksheet Engine returned HTTP {$a}.';
$string['engineinvalidresponse'] = 'The Worksheet Engine returned an invalid response.';
$string['overview'] = 'Overview';
$string['worksheetcontent'] = 'Worksheet content';
$string['nosessionyet'] = 'No teaching session exists yet. Create a session before adding content or teams.';
$string['content_save_failed'] = 'Could not save content: {$a}';
$string['content_import_failed'] = 'Could not convert the file: {$a}';
$string['cannoteditclosedteams'] = 'Teams cannot be changed in a closed session.';
$string['invalidteamid'] = 'The team does not belong to the selected session.';
$string['cannotmovesubmittedteam'] = 'Members of a submitted or graded team cannot be changed because the historical snapshot must be preserved.';
$string['cannotdeleteteamwithattempt'] = 'A team with an attempt cannot be deleted.';
$string['membershipbeingedited'] = 'The teacher is adjusting teams. The attempt is temporarily read-only.';
$string['randomonlydraft'] = 'Random grouping, Moodle group import, and copying teams are available before the session opens.';
$string['imageuploadfailed'] = 'The image for field {$a} could not be uploaded.';
$string['imageuploadinvalid'] = 'The image for field {$a} is invalid or larger than 10 MB.';
$string['imageuploadtype'] = 'Unsupported image format: {$a}. Use JPG, PNG, WEBP, or GIF.';

$string['attemptsavefailed'] = 'The worksheet could not be saved or submitted: {$a}';

$string['attemptlocktimeout'] = 'The attempt is being saved by another request. Please try again in a few seconds.';

$string['workmode'] = 'Work mode';
$string['workmode_help'] = 'Choose grouped work for a shared attempt, or individual work for one attempt per student. Individual records are created lazily when students start.';
$string['invalidindividualsession'] = 'This session is not an individual-work session.';
$string['randomwithattempts'] = 'The entire layout cannot be randomized after attempts exist.';
$string['csvinvalidfile'] = 'The CSV file is empty, unreadable, or larger than 2 MB.';
$string['csvnotindividual'] = 'Individual sessions do not use team CSV import/export.';
$string['csvmissingteam'] = 'The CSV must contain a team_name column.';
$string['csvrowerror'] = '{$a}';
$string['csvempty'] = 'The CSV contains no valid teams.';

// ONLYOFFICE DocSpace Cloud integration.
$string['docspaceheading'] = 'ONLYOFFICE DocSpace Cloud';
$string['docspaceheading_desc'] = 'Embed DocSpace documents in Moodle. The API key remains server-side; every editable template or attempt receives its own Public Room token so the full editor works without a DocSpace login.';
$string['docspaceenabled'] = 'Enable DocSpace Cloud';
$string['docspaceenabled_desc'] = 'Allow teachers to choose DocSpace worksheets alongside HTML worksheets.';
$string['docspaceurl'] = 'DocSpace portal URL';
$string['docspaceurl_desc'] = 'For example https://school.onlyoffice.com. HTTPS is required.';
$string['docspaceapikey'] = 'DocSpace API key';
$string['docspaceapikey_desc'] = 'API key with Rooms and Files read/write permissions. It is never sent to the browser.';
$string['docspacesdkversion'] = 'Embed SDK version';
$string['docspacesdkversion_desc'] = 'Keep auto unless required. The plugin prefers SDK 2.2.0 and falls back to 2.0.0 for older tenants.';
$string['docspacetimeout'] = 'DocSpace API timeout';
$string['docspacetimeout_desc'] = 'Maximum server-side API request duration.';
$string['docspacetest'] = 'Connection test';
$string['docspacetestlink'] = 'Open DocSpace connection test';
$string['docspacenotconfigured'] = 'DocSpace Cloud is not configured or enabled.';
$string['docspacehttpsrequired'] = 'The DocSpace URL must use HTTPS.';
$string['docspacehttp'] = 'DocSpace returned HTTP {$a}.';
$string['docspacehttpdetail'] = 'DocSpace returned HTTP {$a->code}: {$a->message}';
$string['docspaceinvalidresponse'] = 'DocSpace returned invalid data or omitted {$a}.';
$string['docspacetemplatemissing'] = 'This session has no DocSpace template.';
$string['docspacetemplatehasattempts'] = 'The DocSpace template cannot be replaced after attempts exist.';
$string['docspaceoperation'] = 'Template creation method';
$string['docspaceuploadtemplate'] = 'Upload an Office/PDF document';
$string['docspaceblanktemplate'] = 'Create a blank document in DocSpace';
$string['docspacefile'] = 'Template file';
$string['docspacefilename'] = 'Blank document name';
$string['docspaceconfirmreplace'] = 'I understand that this replaces the current DocSpace template document for this session.';
$string['docspacecreatetemplate'] = 'Create template document & open ONLYOFFICE';
$string['docspacecontentmode'] = 'Worksheet content type';
$string['docspacecontentmode_html'] = 'Interactive HTML worksheet';
$string['docspacecontentmode_docspace'] = 'ONLYOFFICE DocSpace document';
$string['docspaceembedfailed'] = 'ONLYOFFICE could not be loaded inside Moodle.';
$string['docspaceopenfallback'] = 'Open the fallback document frame';
$string['docspacesubmitwarning'] = 'Wait for ONLYOFFICE to save before submitting. Moodle will retain a DOCX/PDF snapshot.';
$string['docspacesnapshotmissing'] = 'No Moodle-owned document snapshot is available.';
$string['docspacesnapshotsaved'] = 'A document snapshot was saved in Moodle.';
$string['docspacepublicwarning'] = 'Isolated Rooms uses a dedicated Public Room for each template/attempt. It provides stronger separation but consumes room quota.';
$string['docspacetestsuccess'] = 'DocSpace connection succeeded.';
$string['docspacetestfailed'] = 'DocSpace connection failed: {$a}';
$string['docspaceoriginhelp'] = 'In DocSpace → Developer Tools → Embed SDK, add the exact Moodle origin.';

$string['docspaceexistingattemptswitch'] = 'This session already has attempts and cannot be switched in place. Create an ONLYOFFICE copy to preserve submission history.';
$string['docspaceclonebutton'] = 'Create an ONLYOFFICE session copy';
$string['docspaceautocreated'] = 'The DocSpace document was created and the full ONLYOFFICE Editor is ready.';

$string['docspaceworkspacemode'] = 'DocSpace workspace mode';
$string['docspaceworkspacemode_desc'] = 'Shared Folder reuses one Public Room Folder ID + requestToken and opens each file directly in Editor mode. Isolated Rooms creates a room per template/attempt for stricter isolation.';
$string['docspaceworkspacemode_shared'] = 'Shared Public Room Folder — save room quota';
$string['docspaceworkspacemode_isolated'] = 'Isolated Public Rooms — one room per template/attempt';
$string['docspacefolderid'] = 'Shared DocSpace Folder ID';
$string['docspacefolderid_desc'] = 'Folder ID inside an editable Public Room. Files are uploaded/copied here server-side with the API key.';
$string['docspacerequesttoken'] = 'Shared Public Room requestToken';
$string['docspacerequesttoken_desc'] = 'Request token of the Public Room containing the Folder ID. It is passed to initEditor(fileId); do not put the admin API key here.';
$string['docspacesharedwarning'] = 'Shared Folder saves room quota but its requestToken is scoped to the whole Public Room. Moodle only opens the assigned file, but technically skilled users can inspect client-side tokens.';

$string['noticeheading'] = 'Interface & notifications';
$string['noticeheading_desc'] = 'Control optional instructional and technical notices shown to teachers and students.';
$string['showguidancenotices'] = 'Show guidance notices';
$string['showguidancenotices_desc'] = 'Show explanatory help/warning boxes such as Shared Folder guidance and workflow tips. Disabled by default for a cleaner interface.';
$string['showdocspacestatus'] = 'Show ONLYOFFICE/DocSpace runtime status';
$string['showdocspacestatus_desc'] = 'Show SDK loading, authentication, and Editor/Viewer runtime status above the ONLYOFFICE frame. Disabled by default.';
$string['showerrornotices'] = 'Show supplemental error notices';
$string['showerrornotices_desc'] = 'Show supplemental ONLYOFFICE/DocSpace runtime error notices. Errors required to complete an operation remain visible to prevent silent failures.';

// V12 worksheet platform.
$string['v12heading'] = 'Worksheet Platform V12';
$string['v12heading_desc'] = 'Additive V12 workflow. Keep disabled until ONLYOFFICE and pilot-course gates pass.';
$string['v12enabled'] = 'Enable V12 workflow';
$string['v12enabled_desc'] = 'When enabled, courses selected below use Create session → Choose worksheet → Teams → Ready & open.';
$string['v12pilotcourseids'] = 'V12 pilot course IDs';
$string['v12pilotcourseids_desc'] = 'Comma-separated course IDs. Leave empty to enable V12 for all courses after the global switch is on.';
$string['sessiondescription'] = 'Teacher note';
$string['sessiondescription_help'] = 'Optional note shown on the setup screen.';
$string['workmodeindividual'] = 'Individual';
$string['workmodegrouped'] = 'Groups';
$string['chooseworksheet'] = 'Choose worksheet';
$string['teamstudio'] = 'Team Studio';
$string['readyopen'] = 'Check & open';
$string['readiness'] = 'Readiness';
$string['officesaveprogress'] = 'Save progress';
$string['officesubmit'] = 'Submit';
$string['officeworking'] = 'Shared working document';
$string['officesaving'] = 'Saving latest document…';
$string['officesaved'] = 'Saved';
$string['officesavefailed'] = 'Could not save the latest Office document.';

$string['backuprestoreverified'] = 'Backup/Restore & Course Publisher verified';
$string['backuprestoreverified_desc'] = 'Enable ONLY after a real cross-course single-activity restore and Course Publisher smoke test pass. Default off so eligibility fails closed.';
