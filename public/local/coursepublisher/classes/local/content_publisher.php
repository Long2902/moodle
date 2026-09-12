<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Narrow real-content publisher for CASE 2 pilot increments.
 *
 * The mutation itself delegates to Moodle Core backup/restore. 0.2.2 adds a
 * second Core operation after restore: move the newly-created CM into an
 * explicitly selected target section/position using moveto_module().
 */
final class content_publisher {
    /**
     * Copy one Page activity into an existing target course.
     *
     * @param int $sourcecmid Source course_modules.id.
     * @param int $targetcourseid Target course id.
     * @param int $userid Actor id used by Moodle backup/restore.
     * @param array|null $placement Optional exact placement contract:
     *        targetsectionid:int, position:start|end|before, beforecmid:int.
     * @return array{targetcmid:int,newcmids:int[],warnings:array,targetsectionid:int,position:string,beforecmid:int}
     */
    public static function copy_page_activity(
        int $sourcecmid,
        int $targetcourseid,
        int $userid,
        ?array $placement = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $cm = $DB->get_record('course_modules', ['id' => $sourcecmid], '*', MUST_EXIST);
        if (!empty($cm->deletioninprogress)) {
            throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
        }
        $module = $DB->get_record('modules', ['id' => $cm->module], 'id,name', MUST_EXIST);
        if ((string)$module->name !== 'page') {
            throw new \moodle_exception('pilotpageonly', 'local_coursepublisher');
        }
        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        $normalisedplacement = null;
        if ($placement !== null) {
            $normalisedplacement = self::validate_placement($targetcourseid, $placement);
        }

        $before = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $beforemap = array_fill_keys($before, true);

        $backupid = null;
        $bc = null;
        $rc = null;
        $warnings = [];
        try {
            // Core import mechanism used for a single course module.
            $bc = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                $sourcecmid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $userid
            );
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            $bc->destroy();
            $bc = null;

            $rc = new \restore_controller(
                $backupid,
                $targetcourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_SAMESITE,
                $userid,
                \backup::TARGET_EXISTING_ADDING
            );
            $precheckok = $rc->execute_precheck();
            if (!$precheckok) {
                $precheck = $rc->get_precheck_results();
                if (!empty($precheck['errors'])) {
                    throw new \moodle_exception(
                        'pilotrestoreprecheck',
                        'local_coursepublisher',
                        '',
                        implode(' | ', array_map('strip_tags', (array)$precheck['errors']))
                    );
                }
                $warnings = array_values((array)($precheck['warnings'] ?? []));
            }
            $rc->execute_plan();
            $rc->destroy();
            $rc = null;
        } finally {
            if ($bc) {
                $bc->destroy();
            }
            if ($rc) {
                $rc->destroy();
            }
        }

        // TYPE_1ACTIVITY for mod_page should create exactly one new CM. The
        // Course Publisher target lock makes the target-course delta safe to
        // use for identification during this worker run.
        $after = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $newcmids = [];
        foreach ($after as $cmid) {
            if (!isset($beforemap[$cmid])) {
                $newcmids[] = $cmid;
            }
        }
        sort($newcmids, SORT_NUMERIC);

        if (count($newcmids) !== 1) {
            throw new \moodle_exception(
                'pilotcopyambiguous',
                'local_coursepublisher',
                '',
                implode(',', $newcmids)
            );
        }

        $newcm = $DB->get_record(
            'course_modules',
            ['id' => $newcmids[0], 'course' => $targetcourseid],
            '*',
            MUST_EXIST
        );
        $newmodule = $DB->get_record('modules', ['id' => $newcm->module], 'id,name', MUST_EXIST);
        if ((string)$newmodule->name !== 'page' || !$DB->record_exists('page', ['id' => $newcm->instance])) {
            throw new \moodle_exception('pilotcopypostcondition', 'local_coursepublisher');
        }

        $resultplacement = [
            'targetsectionid' => (int)$newcm->section,
            'position' => 'restore_default',
            'beforecmid' => 0,
        ];

        if ($normalisedplacement !== null) {
            $resultplacement = self::place_coursemodule(
                $targetcourseid,
                (int)$newcm->id,
                $normalisedplacement
            );
        }

        return [
            'targetcmid' => (int)$newcm->id,
            'newcmids' => $newcmids,
            'warnings' => $warnings,
            'targetsectionid' => (int)$resultplacement['targetsectionid'],
            'position' => (string)$resultplacement['position'],
            'beforecmid' => (int)$resultplacement['beforecmid'],
        ];
    }

    /**
     * Copy one Quiz activity into an existing target course.
     *
     * Uses the same Moodle Core TYPE_1ACTIVITY backup/restore path as the
     * validated Page pilot, then verifies the restored quiz slot count
     * matches the source before optional exact placement.
     *
     * @return array{targetcmid:int,newcmids:int[],warnings:array,targetsectionid:int,position:string,beforecmid:int,sourcequizslots:int,targetquizslots:int}
     */
    public static function copy_quiz_activity(
        int $sourcecmid,
        int $targetcourseid,
        int $userid,
        ?array $placement = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $cm = $DB->get_record('course_modules', ['id' => $sourcecmid], '*', MUST_EXIST);
        if (!empty($cm->deletioninprogress)) {
            throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
        }
        $module = $DB->get_record('modules', ['id' => $cm->module], 'id,name', MUST_EXIST);
        if ((string)$module->name !== 'quiz') {
            throw new \moodle_exception('pilotquizonly', 'local_coursepublisher');
        }
        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        $normalisedplacement = null;
        if ($placement !== null) {
            $normalisedplacement = self::validate_placement($targetcourseid, $placement);
        }

        $before = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $beforemap = array_fill_keys($before, true);

        $backupid = null;
        $bc = null;
        $rc = null;
        $warnings = [];
        try {
            // Core import mechanism used for a single course module.
            $bc = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                $sourcecmid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $userid
            );
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            $bc->destroy();
            $bc = null;

            $rc = new \restore_controller(
                $backupid,
                $targetcourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_SAMESITE,
                $userid,
                \backup::TARGET_EXISTING_ADDING
            );
            $precheckok = $rc->execute_precheck();
            if (!$precheckok) {
                $precheck = $rc->get_precheck_results();
                if (!empty($precheck['errors'])) {
                    throw new \moodle_exception(
                        'pilotquizrestoreprecheck',
                        'local_coursepublisher',
                        '',
                        implode(' | ', array_map('strip_tags', (array)$precheck['errors']))
                    );
                }
                $warnings = array_values((array)($precheck['warnings'] ?? []));
            }
            $rc->execute_plan();
            $rc->destroy();
            $rc = null;
        } finally {
            if ($bc) {
                $bc->destroy();
            }
            if ($rc) {
                $rc->destroy();
            }
        }

        // TYPE_1ACTIVITY for mod_quiz should create exactly one new CM. The
        // Course Publisher target lock makes the target-course delta safe to
        // use for identification during this worker run.
        $after = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $newcmids = [];
        foreach ($after as $cmid) {
            if (!isset($beforemap[$cmid])) {
                $newcmids[] = $cmid;
            }
        }
        sort($newcmids, SORT_NUMERIC);

        if (count($newcmids) !== 1) {
            throw new \moodle_exception(
                'pilotquizcopyambiguous',
                'local_coursepublisher',
                '',
                implode(',', $newcmids)
            );
        }

        $newcm = $DB->get_record(
            'course_modules',
            ['id' => $newcmids[0], 'course' => $targetcourseid],
            '*',
            MUST_EXIST
        );
        $newmodule = $DB->get_record('modules', ['id' => $newcm->module], 'id,name', MUST_EXIST);
        if ((string)$newmodule->name !== 'quiz' || !$DB->record_exists('quiz', ['id' => $newcm->instance])) {
            throw new \moodle_exception('pilotquizpostcondition', 'local_coursepublisher');
        }

        // Narrow quiz integrity check: Moodle restore must preserve the number
        // of quiz slots. Question-bank records are restored by Moodle Core.
        $sourceslots = array_values($DB->get_records(
            'quiz_slots',
            ['quizid' => (int)$cm->instance],
            'slot ASC',
            'id,slot,page,requireprevious,maxmark'
        ));
        $targetslots = array_values($DB->get_records(
            'quiz_slots',
            ['quizid' => (int)$newcm->instance],
            'slot ASC',
            'id,slot,page,requireprevious,maxmark'
        ));
        $sourcequizslots = count($sourceslots);
        $targetquizslots = count($targetslots);
        if ($sourcequizslots !== $targetquizslots) {
            throw new \moodle_exception(
                'pilotquizslotmismatch',
                'local_coursepublisher',
                '',
                (object)['source' => $sourcequizslots, 'target' => $targetquizslots]
            );
        }

        $normaliseslots = static function(array $slots): array {
            return array_map(
                static fn(\stdClass $slot): array => [
                    'slot' => (int)$slot->slot,
                    'page' => (int)$slot->page,
                    'requireprevious' => (int)$slot->requireprevious,
                    'maxmark' => (string)$slot->maxmark,
                ],
                $slots
            );
        };
        if ($normaliseslots($sourceslots) !== $normaliseslots($targetslots)) {
            throw new \moodle_exception('pilotquizstructuremismatch', 'local_coursepublisher');
        }

        $resultplacement = [
            'targetsectionid' => (int)$newcm->section,
            'position' => 'restore_default',
            'beforecmid' => 0,
        ];

        if ($normalisedplacement !== null) {
            $resultplacement = self::place_coursemodule(
                $targetcourseid,
                (int)$newcm->id,
                $normalisedplacement
            );
        }

        return [
            'targetcmid' => (int)$newcm->id,
            'newcmids' => $newcmids,
            'warnings' => $warnings,
            'targetsectionid' => (int)$resultplacement['targetsectionid'],
            'position' => (string)$resultplacement['position'],
            'beforecmid' => (int)$resultplacement['beforecmid'],
            'sourcequizslots' => $sourcequizslots,
            'targetquizslots' => $targetquizslots,
        ];
    }

    /**
     * Validate that an activity can participate in Moodle 2 backup/restore.
     *
     * This is intentionally capability-driven instead of maintaining a hard-
     * coded module allow-list. Standard Moodle modules and installed custom
     * modules are eligible when they explicitly declare FEATURE_BACKUP_MOODLE2.
     *
     * @return array{cm:\stdClass,module:\stdClass,modname:string}
     */
    public static function validate_backup_capable_activity(
        int $sourcecmid,
        int $sourcecourseid = 0
    ): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/moodlelib.php');

        $cm = $DB->get_record('course_modules', ['id' => $sourcecmid], '*', MUST_EXIST);
        if ($sourcecourseid > 0 && (int)$cm->course !== $sourcecourseid) {
            throw new \moodle_exception('genericactivitywrongcourse', 'local_coursepublisher');
        }
        if (!empty($cm->deletioninprogress)) {
            throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
        }
        $module = $DB->get_record('modules', ['id' => $cm->module], 'id,name', MUST_EXIST);
        $modname = (string)$module->name;

        if (!defined('FEATURE_BACKUP_MOODLE2') ||
                !plugin_supports('mod', $modname, FEATURE_BACKUP_MOODLE2, false)) {
            throw new \moodle_exception('genericbackupunsupported', 'local_coursepublisher', '', $modname);
        }

        return [
            'cm' => $cm,
            'module' => $module,
            'modname' => $modname,
        ];
    }

    /**
     * Copy any single-CM backup-capable Moodle activity/resource.
     *
     * mod_subsection is structural and may restore multiple CMs, therefore it
     * is handled by copy_delegated_subsection() through the Section workflow.
     *
     * @return array{targetcmid:int,newcmids:int[],warnings:array,targetsectionid:int,position:string,beforecmid:int,modname:string}
     */
    public static function copy_generic_activity(
        int $sourcecmid,
        int $targetcourseid,
        int $userid,
        ?array $placement = null
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $source = self::validate_backup_capable_activity($sourcecmid);
        $cm = $source['cm'];
        $modname = (string)$source['modname'];
        if ($modname === 'subsection') {
            throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
        }
        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        $normalisedplacement = null;
        if ($placement !== null) {
            $normalisedplacement = self::validate_placement($targetcourseid, $placement);
        }

        $before = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $beforemap = array_fill_keys($before, true);

        $backupid = null;
        $bc = null;
        $rc = null;
        $warnings = [];
        try {
            $bc = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                $sourcecmid,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $userid
            );
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            $bc->destroy();
            $bc = null;

            $rc = new \restore_controller(
                $backupid,
                $targetcourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_SAMESITE,
                $userid,
                \backup::TARGET_EXISTING_ADDING
            );
            $precheckok = $rc->execute_precheck();
            if (!$precheckok) {
                $precheck = $rc->get_precheck_results();
                if (!empty($precheck['errors'])) {
                    throw new \moodle_exception(
                        'genericrestoreprecheck',
                        'local_coursepublisher',
                        '',
                        implode(' | ', array_map('strip_tags', (array)$precheck['errors']))
                    );
                }
                $warnings = array_values((array)($precheck['warnings'] ?? []));
            }
            $rc->execute_plan();
            $rc->destroy();
            $rc = null;
        } finally {
            if ($bc) {
                $bc->destroy();
            }
            if ($rc) {
                $rc->destroy();
            }
        }

        $after = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $newcmids = [];
        foreach ($after as $cmid) {
            if (!isset($beforemap[$cmid])) {
                $newcmids[] = $cmid;
            }
        }
        sort($newcmids, SORT_NUMERIC);

        if (count($newcmids) !== 1) {
            throw new \moodle_exception(
                'genericcopyambiguous',
                'local_coursepublisher',
                '',
                $modname . ': ' . implode(',', $newcmids)
            );
        }

        $newcm = $DB->get_record(
            'course_modules',
            ['id' => $newcmids[0], 'course' => $targetcourseid],
            '*',
            MUST_EXIST
        );
        $targetmodname = (string)$DB->get_field('modules', 'name', ['id' => $newcm->module], MUST_EXIST);
        if ($targetmodname !== $modname || !empty($newcm->deletioninprogress)) {
            throw new \moodle_exception('genericcopypostcondition', 'local_coursepublisher');
        }

        // Retain the stronger Quiz structural postcondition from the pilot.
        if ($modname === 'quiz') {
            self::assert_quiz_slot_structure_matches($sourcecmid, (int)$newcm->id);
        }

        $resultplacement = [
            'targetsectionid' => (int)$newcm->section,
            'position' => 'restore_default',
            'beforecmid' => 0,
        ];
        if ($normalisedplacement !== null) {
            $resultplacement = self::place_coursemodule(
                $targetcourseid,
                (int)$newcm->id,
                $normalisedplacement
            );
        }

        return [
            'targetcmid' => (int)$newcm->id,
            'newcmids' => $newcmids,
            'warnings' => $warnings,
            'targetsectionid' => (int)$resultplacement['targetsectionid'],
            'position' => (string)$resultplacement['position'],
            'beforecmid' => (int)$resultplacement['beforecmid'],
            'modname' => $modname,
        ];
    }

    /**
     * Validate an exact target placement without changing the course.
     *
     * @return array{targetsectionid:int,position:string,beforecmid:int}
     */
    public static function validate_placement(int $targetcourseid, array $placement): array {
        global $DB;

        $targetsectionid = (int)($placement['targetsectionid'] ?? 0);
        $position = (string)($placement['position'] ?? '');
        $beforecmid = (int)($placement['beforecmid'] ?? 0);

        if ($targetsectionid <= 0 || !in_array($position, ['start', 'end', 'before'], true)) {
            throw new \moodle_exception('placementinvalid', 'local_coursepublisher');
        }

        $section = $DB->get_record(
            'course_sections',
            ['id' => $targetsectionid, 'course' => $targetcourseid],
            'id,course,section,sequence,name,component,itemid',
            IGNORE_MISSING
        );
        if (!$section) {
            throw new \moodle_exception('placementtargetsectionmissing', 'local_coursepublisher');
        }

        if (!empty($section->component)) {
            throw new \moodle_exception('placementdelegatedunsupported', 'local_coursepublisher');
        }

        if ($position === 'before') {
            if ($beforecmid <= 0) {
                throw new \moodle_exception('placementbeforeinvalid', 'local_coursepublisher');
            }
            $before = $DB->get_record(
                'course_modules',
                ['id' => $beforecmid, 'course' => $targetcourseid, 'section' => $targetsectionid],
                'id,course,section,deletioninprogress',
                IGNORE_MISSING
            );
            if (!$before || !empty($before->deletioninprogress)) {
                throw new \moodle_exception('placementbeforeinvalid', 'local_coursepublisher');
            }
        } else {
            $beforecmid = 0;
        }

        return [
            'targetsectionid' => (int)$section->id,
            'position' => $position,
            'beforecmid' => $beforecmid,
        ];
    }

    /**
     * Move a newly restored module to the exact requested location using Core.
     *
     * @return array{targetsectionid:int,position:string,beforecmid:int}
     */
    private static function place_coursemodule(int $targetcourseid, int $targetcmid, array $placement): array {
        global $DB;

        $placement = self::validate_placement($targetcourseid, $placement);
        $targetsection = $DB->get_record(
            'course_sections',
            ['id' => $placement['targetsectionid'], 'course' => $targetcourseid],
            '*',
            MUST_EXIST
        );

        // Determine the Core before-mod argument. "start" means before the
        // first pre-existing CM in this section; "end" passes null.
        $beforecmid = 0;
        if ($placement['position'] === 'before') {
            $beforecmid = (int)$placement['beforecmid'];
        } else if ($placement['position'] === 'start') {
            $sequence = array_values(array_filter(array_map('intval', explode(',', trim((string)$targetsection->sequence)))));
            foreach ($sequence as $candidate) {
                if ($candidate !== $targetcmid) {
                    $beforecmid = $candidate;
                    break;
                }
            }
        }

        rebuild_course_cache($targetcourseid, true);
        $modinfo = get_fast_modinfo($targetcourseid);
        $targetsectioninfo = $modinfo->get_section_info((int)$targetsection->section);
        if (!$targetsectioninfo) {
            throw new \moodle_exception('placementtargetsectionmissing', 'local_coursepublisher');
        }
        $targetcminfo = $modinfo->get_cm($targetcmid);

        // Moodle 5.1 Core API: move the module and update section sequences.
        moveto_module($targetcminfo, $targetsectioninfo, $beforecmid > 0 ? $beforecmid : null);
        rebuild_course_cache($targetcourseid, true);

        // Strong postcondition: CM belongs to the selected section and appears
        // at the requested sequence location.
        $placedcm = $DB->get_record(
            'course_modules',
            ['id' => $targetcmid, 'course' => $targetcourseid],
            'id,course,section',
            MUST_EXIST
        );
        if ((int)$placedcm->section !== (int)$targetsection->id) {
            throw new \moodle_exception('placementpostcondition', 'local_coursepublisher');
        }

        $placedsection = $DB->get_record('course_sections', ['id' => $targetsection->id], 'id,sequence', MUST_EXIST);
        $sequence = array_values(array_filter(array_map('intval', explode(',', trim((string)$placedsection->sequence)))));
        $index = array_search($targetcmid, $sequence, true);
        if ($index === false) {
            throw new \moodle_exception('placementpostcondition', 'local_coursepublisher');
        }

        if ($placement['position'] === 'start' && $index !== 0) {
            throw new \moodle_exception('placementpostcondition', 'local_coursepublisher');
        }
        if ($placement['position'] === 'end' && $index !== count($sequence) - 1) {
            throw new \moodle_exception('placementpostcondition', 'local_coursepublisher');
        }
        if ($placement['position'] === 'before') {
            $beforeindex = array_search((int)$placement['beforecmid'], $sequence, true);
            if ($beforeindex === false || $index + 1 !== $beforeindex) {
                throw new \moodle_exception('placementpostcondition', 'local_coursepublisher');
            }
        }

        return [
            'targetsectionid' => (int)$targetsection->id,
            'position' => (string)$placement['position'],
            'beforecmid' => (int)$placement['beforecmid'],
        ];
    }

    /**
     * Validate that a source section is a Moodle delegated subsection and
     * return both the section record and the delegating mod_subsection CM.
     *
     * Moodle 5.1 represents subsection content as a delegated course section
     * whose component is mod_subsection and whose itemid is the subsection
     * activity instance id.
     *
     * @return array{section:\stdClass,delegatecm:\stdClass}
     */
    public static function validate_source_delegated_subsection(
        int $sourcesectionid,
        int $sourcecourseid
    ): array {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['id' => $sourcesectionid, 'course' => $sourcecourseid],
            '*',
            MUST_EXIST
        );
        if ((int)$section->section <= 0 || (string)$section->component !== 'mod_subsection' || (int)$section->itemid <= 0) {
            throw new \moodle_exception('subsectionpilotnotdelegated', 'local_coursepublisher');
        }
        if (!class_exists('\\mod_subsection\\manager')) {
            throw new \moodle_exception('subsectionpilotunavailable', 'local_coursepublisher');
        }

        $delegatecm = get_coursemodule_from_instance(
            'subsection',
            (int)$section->itemid,
            $sourcecourseid,
            false,
            MUST_EXIST
        );
        if (!empty($delegatecm->deletioninprogress)) {
            throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
        }

        $manager = \mod_subsection\manager::create_from_coursemodule($delegatecm);
        $delegated = $manager->get_delegated_section_info();
        if (!$delegated || (int)$delegated->id !== (int)$section->id) {
            throw new \moodle_exception('subsectionpilotinconsistent', 'local_coursepublisher');
        }

        return [
            'section' => $section,
            'delegatecm' => $delegatecm,
        ];
    }

    /**
     * Resolve the delegated section owned by a mod_subsection course module.
     */
    public static function delegated_section_from_subsection_cmid(
        int $sourcecmid,
        int $sourcecourseid
    ): \stdClass {
        global $DB;

        $activity = self::validate_backup_capable_activity($sourcecmid, $sourcecourseid);
        if ((string)$activity['modname'] !== 'subsection') {
            throw new \moodle_exception('genericactivitysubsectionuse_section', 'local_coursepublisher');
        }
        if (!class_exists('\mod_subsection\manager')) {
            throw new \moodle_exception('subsectionpilotunavailable', 'local_coursepublisher');
        }
        $manager = \mod_subsection\manager::create_from_coursemodule($activity['cm']);
        $section = $manager->get_delegated_section_info();
        if (!$section || (string)$section->component !== 'mod_subsection') {
            throw new \moodle_exception('subsectionpilotinconsistent', 'local_coursepublisher');
        }
        return $DB->get_record(
            'course_sections',
            ['id' => (int)$section->id, 'course' => $sourcecourseid],
            '*',
            MUST_EXIST
        );
    }

    /**
     * Copy a Moodle delegated subsection as one Core TYPE_1ACTIVITY backup.
     *
     * Moodle's backup plan for mod_subsection automatically includes the
     * delegated section and the activities inside it. After restore we move
     * only the restored subsection activity to the requested parent section;
     * its delegated section and child activities remain linked to it.
     *
     * @param array $manifest Source child manifest in source section order.
     * @param array $placement Exact parent-section placement for the new subsection activity.
     * @return array{targetdelegatecmid:int,targetdelegatedsectionid:int,childmap:array,warnings:array,targetsectionid:int,position:string,beforecmid:int,newcmids:array}
     */
    public static function copy_delegated_subsection(
        int $sourcesectionid,
        int $sourcecourseid,
        int $targetcourseid,
        int $userid,
        array $manifest,
        array $placement
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $source = self::validate_source_delegated_subsection($sourcesectionid, $sourcecourseid);
        $delegatecm = $source['delegatecm'];
        $normalisedplacement = self::validate_placement($targetcourseid, $placement);
        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        foreach ($manifest as $row) {
            self::validate_backup_capable_activity((int)$row['sourcecmid'], $sourcecourseid);
        }

        $before = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $beforemap = array_fill_keys($before, true);

        $backupid = null;
        $bc = null;
        $rc = null;
        $warnings = [];
        try {
            // Crucial Moodle 5.1 behavior: backing up the mod_subsection CM
            // also adds its delegated section and child activities to the plan.
            $bc = new \backup_controller(
                \backup::TYPE_1ACTIVITY,
                (int)$delegatecm->id,
                \backup::FORMAT_MOODLE,
                \backup::INTERACTIVE_NO,
                \backup::MODE_IMPORT,
                $userid
            );
            $backupid = $bc->get_backupid();
            $bc->execute_plan();
            $bc->destroy();
            $bc = null;

            $rc = new \restore_controller(
                $backupid,
                $targetcourseid,
                \backup::INTERACTIVE_NO,
                \backup::MODE_SAMESITE,
                $userid,
                \backup::TARGET_EXISTING_ADDING
            );
            $precheckok = $rc->execute_precheck();
            if (!$precheckok) {
                $precheck = $rc->get_precheck_results();
                if (!empty($precheck['errors'])) {
                    throw new \moodle_exception(
                        'subsectionpilotrestoreprecheck',
                        'local_coursepublisher',
                        '',
                        implode(' | ', array_map('strip_tags', (array)$precheck['errors']))
                    );
                }
                $warnings = array_values((array)($precheck['warnings'] ?? []));
            }
            $rc->execute_plan();
            $rc->destroy();
            $rc = null;
        } finally {
            if ($bc) {
                $bc->destroy();
            }
            if ($rc) {
                $rc->destroy();
            }
        }

        $after = array_map('intval', $DB->get_fieldset_select(
            'course_modules', 'id', 'course = ?', [$targetcourseid]
        ));
        $newcmids = [];
        foreach ($after as $cmid) {
            if (!isset($beforemap[$cmid])) {
                $newcmids[] = $cmid;
            }
        }
        sort($newcmids, SORT_NUMERIC);

        // Find exactly one restored mod_subsection CM inside the target delta.
        $subsectionmoduleid = (int)$DB->get_field('modules', 'id', ['name' => 'subsection'], MUST_EXIST);
        $targetdelegatecm = null;
        foreach ($newcmids as $newcmid) {
            $candidate = $DB->get_record(
                'course_modules',
                ['id' => $newcmid, 'course' => $targetcourseid],
                'id,course,module,instance,section,deletioninprogress',
                MUST_EXIST
            );
            if ((int)$candidate->module === $subsectionmoduleid) {
                if ($targetdelegatecm !== null) {
                    throw new \moodle_exception('subsectionpilotcopyambiguous', 'local_coursepublisher');
                }
                $targetdelegatecm = $candidate;
            }
        }
        if (!$targetdelegatecm || !empty($targetdelegatecm->deletioninprogress)) {
            throw new \moodle_exception('subsectionpilotcopyambiguous', 'local_coursepublisher');
        }

        $manager = \mod_subsection\manager::create_from_coursemodule($targetdelegatecm);
        $targetdelegated = $manager->get_delegated_section_info();
        if (!$targetdelegated || (string)$targetdelegated->component !== 'mod_subsection') {
            throw new \moodle_exception('subsectionpilottargetmissing', 'local_coursepublisher');
        }

        // The visual position of a subsection is the position of its delegate
        // course module in a normal parent section.
        $parentplacement = self::place_coursemodule(
            $targetcourseid,
            (int)$targetdelegatecm->id,
            $normalisedplacement
        );

        rebuild_course_cache($targetcourseid, true);
        $targetdelegated = $DB->get_record(
            'course_sections',
            ['id' => (int)$targetdelegated->id, 'course' => $targetcourseid],
            '*',
            MUST_EXIST
        );
        if ((string)$targetdelegated->component !== 'mod_subsection' ||
                (int)$targetdelegated->itemid !== (int)$targetdelegatecm->instance) {
            throw new \moodle_exception('subsectionpilottargetmissing', 'local_coursepublisher');
        }

        $targetsequence = array_values(array_filter(array_map(
            'intval',
            explode(',', trim((string)$targetdelegated->sequence))
        )));
        if (count($targetsequence) !== count($manifest)) {
            throw new \moodle_exception(
                'subsectionpilotitemcountmismatch',
                'local_coursepublisher',
                '',
                (object)['source' => count($manifest), 'target' => count($targetsequence)]
            );
        }

        $childmap = [];
        foreach ($manifest as $index => $row) {
            $targetcmid = (int)$targetsequence[$index];
            $targetcm = $DB->get_record(
                'course_modules',
                ['id' => $targetcmid, 'course' => $targetcourseid, 'section' => (int)$targetdelegated->id],
                'id,course,module,instance,section,deletioninprogress',
                MUST_EXIST
            );
            if (!empty($targetcm->deletioninprogress)) {
                throw new \moodle_exception('subsectionpilotitempostcondition', 'local_coursepublisher');
            }
            $targetmodname = (string)$DB->get_field('modules', 'name', ['id' => $targetcm->module], MUST_EXIST);
            if ($targetmodname !== (string)$row['sourcemodule']) {
                throw new \moodle_exception('subsectionpilotorderpostcondition', 'local_coursepublisher');
            }

            if ($targetmodname === 'quiz') {
                self::assert_quiz_slot_structure_matches((int)$row['sourcecmid'], $targetcmid);
            }

            $childmap[] = [
                'sourcecmid' => (int)$row['sourcecmid'],
                'targetcmid' => $targetcmid,
                'modname' => $targetmodname,
            ];
        }

        return [
            'targetdelegatecmid' => (int)$targetdelegatecm->id,
            'targetdelegatedsectionid' => (int)$targetdelegated->id,
            'childmap' => $childmap,
            'warnings' => $warnings,
            'targetsectionid' => (int)$parentplacement['targetsectionid'],
            'position' => (string)$parentplacement['position'],
            'beforecmid' => (int)$parentplacement['beforecmid'],
            'newcmids' => $newcmids,
        ];
    }

    /** Verify Quiz slot structure for a source/target course-module pair. */
    private static function assert_quiz_slot_structure_matches(int $sourcecmid, int $targetcmid): void {
        global $DB;

        $sourcecm = $DB->get_record('course_modules', ['id' => $sourcecmid], 'id,module,instance', MUST_EXIST);
        $targetcm = $DB->get_record('course_modules', ['id' => $targetcmid], 'id,module,instance', MUST_EXIST);
        $quizmoduleid = (int)$DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST);
        if ((int)$sourcecm->module !== $quizmoduleid || (int)$targetcm->module !== $quizmoduleid) {
            throw new \moodle_exception('pilotquizonly', 'local_coursepublisher');
        }

        $sourceslots = array_values($DB->get_records(
            'quiz_slots',
            ['quizid' => (int)$sourcecm->instance],
            'slot ASC',
            'id,slot,page,requireprevious,maxmark'
        ));
        $targetslots = array_values($DB->get_records(
            'quiz_slots',
            ['quizid' => (int)$targetcm->instance],
            'slot ASC',
            'id,slot,page,requireprevious,maxmark'
        ));
        if (count($sourceslots) !== count($targetslots)) {
            throw new \moodle_exception(
                'pilotquizslotmismatch',
                'local_coursepublisher',
                '',
                (object)['source' => count($sourceslots), 'target' => count($targetslots)]
            );
        }

        $normalise = static function(array $slots): array {
            return array_map(
                static fn(\stdClass $slot): array => [
                    'slot' => (int)$slot->slot,
                    'page' => (int)$slot->page,
                    'requireprevious' => (int)$slot->requireprevious,
                    'maxmark' => (string)$slot->maxmark,
                ],
                $slots
            );
        };
        if ($normalise($sourceslots) !== $normalise($targetslots)) {
            throw new \moodle_exception('pilotquizstructuremismatch', 'local_coursepublisher');
        }
    }

    /**
     * Validate placement for creating a brand-new peer target section.
     *
     * Supported positions:
     * - start: create as section 1.
     * - end: create at the end of the course.
     * - before: create immediately before an existing standard section.
     * - after: create immediately after an existing standard section.
     *
     * The anchor is stored in the legacy beforesectionid field so existing
     * job snapshots remain readable while 0.2.6 adds "after" semantics.
     *
     * @return array{position:string,beforesectionid:int,anchorsectionid:int}
     */
    public static function validate_section_placement(int $targetcourseid, array $placement): array {
        global $DB;

        $position = (string)($placement['position'] ?? '');
        $anchorsectionid = (int)($placement['anchorsectionid'] ?? ($placement['beforesectionid'] ?? 0));

        if (!in_array($position, ['start', 'end', 'before', 'after'], true)) {
            throw new \moodle_exception('sectionplacementinvalid', 'local_coursepublisher');
        }

        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        if (in_array($position, ['before', 'after'], true)) {
            $anchor = $DB->get_record(
                'course_sections',
                ['id' => $anchorsectionid, 'course' => $targetcourseid],
                'id,course,section,component,itemid',
                IGNORE_MISSING
            );
            if (!$anchor || (int)$anchor->section <= 0 || !empty($anchor->component)) {
                throw new \moodle_exception('sectionplacementanchorinvalid', 'local_coursepublisher');
            }
        } else {
            $anchorsectionid = 0;
        }

        return [
            'position' => $position,
            'beforesectionid' => $anchorsectionid,
            'anchorsectionid' => $anchorsectionid,
        ];
    }

    /**
     * Validate any non-General source section for peer-section copying.
     *
     * Both standard sections and delegated mod_subsection sections are
     * accepted. A delegated section can therefore be promoted/flattened into
     * a normal same-level section in the target course.
     */
    public static function validate_source_section_for_generic_copy(
        int $sourcesectionid,
        int $sourcecourseid
    ): \stdClass {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['id' => $sourcesectionid, 'course' => $sourcecourseid],
            '*',
            MUST_EXIST
        );
        if ((int)$section->section <= 0) {
            throw new \moodle_exception('sectionpilotgeneralunsupported', 'local_coursepublisher');
        }
        if (!empty($section->component) && (string)$section->component !== 'mod_subsection') {
            throw new \moodle_exception('sectiongenericdelegatedunsupported', 'local_coursepublisher', '', (string)$section->component);
        }
        // Section summary file areas need a separate file-copy contract. Do
        // not silently break embedded files during this generic increment.
        if (strpos((string)$section->summary, '@@PLUGINFILE@@') !== false) {
            throw new \moodle_exception('sectionpilotsummaryfilesunsupported', 'local_coursepublisher');
        }
        return $section;
    }

    /**
     * Preflight the source section for the 0.2.3 Page-only section pilot.
     */
    public static function validate_source_section_for_page_pilot(int $sourcesectionid, int $sourcecourseid): \stdClass {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['id' => $sourcesectionid, 'course' => $sourcecourseid],
            '*',
            MUST_EXIST
        );
        if ((int)$section->section <= 0) {
            throw new \moodle_exception('sectionpilotgeneralunsupported', 'local_coursepublisher');
        }
        if (!empty($section->component)) {
            throw new \moodle_exception('sectionpilotdelegatedunsupported', 'local_coursepublisher');
        }
        if (strpos((string)$section->summary, '@@PLUGINFILE@@') !== false) {
            throw new \moodle_exception('sectionpilotsummaryfilesunsupported', 'local_coursepublisher');
        }
        return $section;
    }

    /**
     * Create the target section at an exact section-level position and copy
     * safe section metadata using Moodle Core course APIs.
     *
     * @return array{targetsectionid:int,targetsectionnum:int,position:string,beforesectionid:int}
     */
    public static function create_target_section_from_source(
        int $sourcesectionid,
        int $sourcecourseid,
        int $targetcourseid,
        array $placement
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $source = self::validate_source_section_for_page_pilot($sourcesectionid, $sourcecourseid);
        $placement = self::validate_section_placement($targetcourseid, $placement);

        $targetcourse = get_course($targetcourseid);
        $createposition = 0;
        if ($placement['position'] === 'start') {
            $createposition = 1;
        } else if ($placement['position'] === 'before') {
            $anchor = $DB->get_record(
                'course_sections',
                ['id' => $placement['beforesectionid'], 'course' => $targetcourseid],
                'id,section',
                MUST_EXIST
            );
            $createposition = (int)$anchor->section;
        }

        $newsection = course_create_section($targetcourse, $createposition);
        if (!$newsection || empty($newsection->id)) {
            throw new \moodle_exception('sectionpilotcreatefailed', 'local_coursepublisher');
        }

        // Copy only metadata proven safe in this pilot. Availability/delegation
        // rules and section-summary files are intentionally excluded.
        course_update_section($targetcourse, $newsection, [
            'name' => (string)($source->name ?? ''),
            'summary' => (string)($source->summary ?? ''),
            'summaryformat' => (int)($source->summaryformat ?? FORMAT_HTML),
            'visible' => (int)($source->visible ?? 1),
        ]);
        rebuild_course_cache($targetcourseid, true);

        $created = $DB->get_record(
            'course_sections',
            ['id' => $newsection->id, 'course' => $targetcourseid],
            'id,course,section,name,summary,summaryformat,visible,component,itemid',
            MUST_EXIST
        );
        if (!empty($created->component) || (int)$created->section <= 0) {
            throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
        }

        if ($placement['position'] === 'start' && (int)$created->section !== 1) {
            throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
        }
        if ($placement['position'] === 'before') {
            $anchor = $DB->get_record(
                'course_sections',
                ['id' => $placement['beforesectionid'], 'course' => $targetcourseid],
                'id,section',
                MUST_EXIST
            );
            if ((int)$created->section + 1 !== (int)$anchor->section) {
                throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
            }
        }
        if ($placement['position'] === 'end') {
            $maxsection = (int)$DB->get_field_sql(
                'SELECT MAX(section) FROM {course_sections} WHERE course = :courseid',
                ['courseid' => $targetcourseid]
            );
            if ((int)$created->section !== $maxsection) {
                throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
            }
        }

        return [
            'targetsectionid' => (int)$created->id,
            'targetsectionnum' => (int)$created->section,
            'position' => (string)$placement['position'],
            'beforesectionid' => (int)$placement['beforesectionid'],
        ];
    }

    /**
     * Create a normal same-level target section from either a standard source
     * section or a delegated mod_subsection section.
     *
     * This is the 0.2.6 "promote to peer section" path. The source delegated
     * section itself is not recreated as a subsection; its immediate contents
     * are copied into this new standard section by the job worker.
     *
     * @return array{targetsectionid:int,targetsectionnum:int,position:string,beforesectionid:int,anchorsectionid:int}
     */
    public static function create_peer_target_section_from_source(
        int $sourcesectionid,
        int $sourcecourseid,
        int $targetcourseid,
        array $placement
    ): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $source = self::validate_source_section_for_generic_copy($sourcesectionid, $sourcecourseid);
        $placement = self::validate_section_placement($targetcourseid, $placement);
        $targetcourse = get_course($targetcourseid);

        $createposition = 0;
        if ($placement['position'] === 'start') {
            $createposition = 1;
        } else if (in_array($placement['position'], ['before', 'after'], true)) {
            $anchor = $DB->get_record(
                'course_sections',
                ['id' => (int)$placement['anchorsectionid'], 'course' => $targetcourseid],
                'id,section',
                MUST_EXIST
            );
            $createposition = (int)$anchor->section;
            if ($placement['position'] === 'after') {
                $createposition++;
            }
        }

        $newsection = course_create_section($targetcourse, $createposition);
        if (!$newsection || empty($newsection->id)) {
            throw new \moodle_exception('sectionpilotcreatefailed', 'local_coursepublisher');
        }

        $sourcename = (string)($source->name ?? '');
        if ($sourcename === '' && (string)($source->component ?? '') === 'mod_subsection') {
            $delegated = self::validate_source_delegated_subsection($sourcesectionid, $sourcecourseid);
            $subsection = $DB->get_record(
                'subsection',
                ['id' => (int)$delegated['delegatecm']->instance],
                'id,name',
                IGNORE_MISSING
            );
            if ($subsection && trim((string)$subsection->name) !== '') {
                $sourcename = (string)$subsection->name;
            }
        }

        course_update_section($targetcourse, $newsection, [
            'name' => $sourcename,
            'summary' => (string)($source->summary ?? ''),
            'summaryformat' => (int)($source->summaryformat ?? FORMAT_HTML),
            'visible' => (int)($source->visible ?? 1),
        ]);
        rebuild_course_cache($targetcourseid, true);

        $created = $DB->get_record(
            'course_sections',
            ['id' => $newsection->id, 'course' => $targetcourseid],
            'id,course,section,name,summary,summaryformat,visible,component,itemid',
            MUST_EXIST
        );
        if (!empty($created->component) || (int)$created->section <= 0) {
            throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
        }

        if ($placement['position'] === 'start' && (int)$created->section !== 1) {
            throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
        }
        if (in_array($placement['position'], ['before', 'after'], true)) {
            $anchor = $DB->get_record(
                'course_sections',
                ['id' => (int)$placement['anchorsectionid'], 'course' => $targetcourseid],
                'id,section',
                MUST_EXIST
            );
            if ($placement['position'] === 'before' &&
                    (int)$created->section + 1 !== (int)$anchor->section) {
                throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
            }
            if ($placement['position'] === 'after' &&
                    (int)$created->section !== (int)$anchor->section + 1) {
                throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
            }
        }
        if ($placement['position'] === 'end') {
            $standardsections = $DB->get_records(
                'course_sections',
                ['course' => $targetcourseid],
                'section ASC',
                'id,section,component'
            );
            $maxsection = 0;
            foreach ($standardsections as $standardsection) {
                if (empty($standardsection->component)) {
                    $maxsection = max($maxsection, (int)$standardsection->section);
                }
            }
            if ((int)$created->section !== $maxsection) {
                throw new \moodle_exception('sectionpilotpostcondition', 'local_coursepublisher');
            }
        }

        return [
            'targetsectionid' => (int)$created->id,
            'targetsectionnum' => (int)$created->section,
            'position' => (string)$placement['position'],
            'beforesectionid' => (int)$placement['anchorsectionid'],
            'anchorsectionid' => (int)$placement['anchorsectionid'],
        ];
    }

    /**
     * Verify an existing target section is still usable by a recovered job.
     */
    public static function validate_created_target_section(int $targetcourseid, int $targetsectionid): \stdClass {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['id' => $targetsectionid, 'course' => $targetcourseid],
            '*',
            IGNORE_MISSING
        );
        if (!$section || (int)$section->section <= 0 || !empty($section->component)) {
            throw new \moodle_exception('sectionpilottargetmissing', 'local_coursepublisher');
        }
        return $section;
    }

}
