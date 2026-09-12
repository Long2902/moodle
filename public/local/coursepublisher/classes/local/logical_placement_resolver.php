<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/** Resolve portable logical placement contracts to one target course. */
final class logical_placement_resolver {
    public const VERSION = 1;

    /** Build the AUTO logical placement contract from the source master. */
    public static function build_source_contract(string $sourcetype, int $sourceid, int $sourcecourseid,
            string $publishmode): array {
        if ($sourcetype === 'activity') {
            return self::build_activity_contract($sourceid, $sourcecourseid);
        }
        if ($sourcetype !== 'section') {
            throw new \moodle_exception('invalidparameter');
        }
        if ($publishmode === job_service::MODE_SUBSECTION_TREE_PLACEMENT) {
            $source = content_publisher::validate_source_delegated_subsection($sourceid, $sourcecourseid);
            return self::build_activity_contract((int)$source['delegatecm']->id, $sourcecourseid, true, $sourceid);
        }
        return self::build_section_contract($sourceid, $sourcecourseid);
    }

    /**
     * Build a manual logical contract from operator input.
     *
     * This contract contains names/types, never target Moodle IDs.
     */
    public static function build_manual_contract(string $sourcetype, string $publishmode, array $input): array {
        $position = (string)($input['position'] ?? '');
        if ($sourcetype === 'activity' || $publishmode === job_service::MODE_SUBSECTION_TREE_PLACEMENT) {
            if (!in_array($position, ['start', 'end', 'before', 'after'], true)) {
                throw new \moodle_exception('placementinvalid', 'local_coursepublisher');
            }
            $sectionname = logical_placement::normalise_text((string)($input['sectionname'] ?? ''));
            if ($sectionname === '') {
                throw new \moodle_exception('placementinvalid', 'local_coursepublisher');
            }
            $contract = [
                'resolverversion' => self::VERSION,
                'kind' => 'activity_placement',
                'mode' => 'override',
                'targetsection' => ['kind' => 'section', 'sectiontype' => 'normal', 'name' => $sectionname],
                'position' => $position,
            ];
            if (in_array($position, ['before', 'after'], true)) {
                $anchorname = logical_placement::normalise_text((string)($input['anchorname'] ?? ''));
                if ($anchorname === '') {
                    throw new \moodle_exception('placementinvalid', 'local_coursepublisher');
                }
                $contract[$position] = [
                    'kind' => 'activity',
                    'name' => $anchorname,
                    'modname' => (string)($input['anchormodname'] ?? ''),
                ];
            }
            return $contract;
        }

        if (!in_array($position, ['start', 'end', 'before', 'after'], true)) {
            throw new \moodle_exception('sectionplacementinvalid', 'local_coursepublisher');
        }
        $contract = [
            'resolverversion' => self::VERSION,
            'kind' => 'section_placement',
            'mode' => 'override',
            'position' => $position,
        ];
        if (in_array($position, ['before', 'after'], true)) {
            $anchorname = logical_placement::normalise_text((string)($input['anchorname'] ?? ''));
            if ($anchorname === '') {
                throw new \moodle_exception('sectionplacementinvalid', 'local_coursepublisher');
            }
            $contract['anchor'] = ['kind' => 'section', 'sectiontype' => 'normal', 'name' => $anchorname];
        }
        return $contract;
    }

    /** Resolve one logical contract against a target course. */
    public static function resolve_for_target(array $contract, int $targetcourseid, string $publishmode): array {
        global $DB;
        $DB->get_record('course', ['id' => $targetcourseid], 'id', MUST_EXIST);

        if (($contract['kind'] ?? '') === 'activity_placement') {
            return self::resolve_activity_contract($contract, $targetcourseid);
        }
        if (($contract['kind'] ?? '') === 'section_placement') {
            return self::resolve_section_contract($contract, $targetcourseid);
        }
        return self::blocked('invalid_contract', 'Unsupported logical placement contract.');
    }

    private static function build_activity_contract(int $sourcecmid, int $sourcecourseid, bool $subsectiondelegate = false,
            int $sourcesectionid = 0): array {
        global $DB;
        $cm = $DB->get_record('course_modules', ['id' => $sourcecmid, 'course' => $sourcecourseid], '*', MUST_EXIST);
        $section = $DB->get_record('course_sections', ['id' => $cm->section, 'course' => $sourcecourseid], '*', MUST_EXIST);
        $ids = self::sequence_ids((string)$section->sequence);
        $index = array_search($sourcecmid, $ids, true);
        if ($index === false) {
            throw new \moodle_exception('jobmanifestbroken', 'local_coursepublisher', '', $sourcecmid);
        }
        $previous = $index > 0 ? self::activity_signature((int)$ids[$index - 1], $sourcecourseid, false) : null;
        $next = $index < count($ids) - 1 ? self::activity_signature((int)$ids[$index + 1], $sourcecourseid, false) : null;
        return [
            'resolverversion' => self::VERSION,
            'kind' => 'activity_placement',
            'mode' => 'auto',
            'sourcecourseid' => $sourcecourseid,
            'source' => self::activity_signature($sourcecmid, $sourcecourseid, true),
            'targetsection' => self::section_signature((int)$section->id, $sourcecourseid, true),
            'previous' => $previous,
            'next' => $next,
            'sourceordinal' => (int)$index,
            'atstart' => $index === 0,
            'atend' => $index === count($ids) - 1,
            'subsectiondelegate' => $subsectiondelegate,
            'sourcesectionid' => $sourcesectionid,
        ];
    }

    private static function build_section_contract(int $sourcesectionid, int $sourcecourseid): array {
        global $DB;
        $source = $DB->get_record('course_sections', ['id' => $sourcesectionid, 'course' => $sourcecourseid], '*', MUST_EXIST);
        if ((int)$source->section <= 0) {
            throw new \moodle_exception('sectionpilotgeneralunsupported', 'local_coursepublisher');
        }
        $sections = array_values($DB->get_records_select(
            'course_sections', 'course = :courseid AND section > 0 AND (component IS NULL OR component = :empty)',
            ['courseid' => $sourcecourseid, 'empty' => ''], 'section ASC'
        ));
        $index = null;
        foreach ($sections as $i => $section) {
            if ((int)$section->id === $sourcesectionid) {
                $index = $i;
                break;
            }
        }
        // Delegated source sections may not be part of the normal peer list. Use their section number to find nearest normal peers.
        if ($index === null) {
            $previous = null;
            $next = null;
            foreach ($sections as $section) {
                if ((int)$section->section < (int)$source->section) {
                    $previous = $section;
                } else if ((int)$section->section > (int)$source->section) {
                    $next = $section;
                    break;
                }
            }
            return [
                'resolverversion' => self::VERSION,
                'kind' => 'section_placement',
                'mode' => 'auto',
                'sourcecourseid' => $sourcecourseid,
                'source' => self::section_signature($sourcesectionid, $sourcecourseid, true),
                'previous' => $previous ? self::section_signature((int)$previous->id, $sourcecourseid, false) : null,
                'next' => $next ? self::section_signature((int)$next->id, $sourcecourseid, false) : null,
                'sourceordinal' => (int)$source->section,
                'atstart' => $previous === null,
                'atend' => $next === null,
            ];
        }
        return [
            'resolverversion' => self::VERSION,
            'kind' => 'section_placement',
            'mode' => 'auto',
            'sourcecourseid' => $sourcecourseid,
            'source' => self::section_signature($sourcesectionid, $sourcecourseid, true),
            'previous' => $index > 0 ? self::section_signature((int)$sections[$index - 1]->id, $sourcecourseid, false) : null,
            'next' => $index < count($sections) - 1 ? self::section_signature((int)$sections[$index + 1]->id, $sourcecourseid, false) : null,
            'sourceordinal' => $index,
            'atstart' => $index === 0,
            'atend' => $index === count($sections) - 1,
        ];
    }

    private static function resolve_activity_contract(array $contract, int $targetcourseid): array {
        $sourcecourseid = (int)($contract['sourcecourseid'] ?? 0);
        $sectionresult = self::resolve_section_signature(
            (array)$contract['targetsection'],
            $targetcourseid,
            false,
            $sourcecourseid
        );
        if ($sectionresult['status'] !== 'resolved') {
            return self::blocked('section_' . $sectionresult['status'], 'Target logical section could not be resolved uniquely.', $sectionresult);
        }
        $section = $sectionresult['candidate'];
        $sectionid = (int)$section['id'];
        $activities = self::activity_candidates_for_section($targetcourseid, $sectionid);

        if (($contract['mode'] ?? '') === 'override') {
            $position = (string)$contract['position'];
            if ($position === 'start' || $position === 'end') {
                $physical = content_publisher::validate_placement($targetcourseid, [
                    'targetsectionid' => $sectionid, 'position' => $position, 'beforecmid' => 0,
                ]);
                return self::resolved($physical, array_merge($sectionresult['evidence'], ['manual_' . $position]));
            }
            $anchorsignature = (array)($contract[$position] ?? []);
            $anchor = logical_placement::choose_unique_candidate($anchorsignature, $activities);
            if ($anchor['status'] !== 'resolved') {
                return self::blocked('anchor_' . $anchor['status'], 'Manual activity anchor could not be resolved uniquely.', $anchor);
            }
            if ($position === 'before') {
                $physical = content_publisher::validate_placement($targetcourseid, [
                    'targetsectionid' => $sectionid, 'position' => 'before', 'beforecmid' => (int)$anchor['candidate']['id'],
                ]);
                return self::resolved($physical, array_merge($sectionresult['evidence'], $anchor['evidence'], ['manual_before']));
            }

            // The low-level Activity placement contract has no explicit
            // `after`. Translate logical AFTER to BEFORE the anchor's next CM,
            // or END when the anchor is currently last in the Section.
            $ids = array_map(static fn(array $row): int => (int)$row['id'], $activities);
            $index = array_search((int)$anchor['candidate']['id'], $ids, true);
            if ($index === false) {
                return self::blocked('anchor_not_found', 'Manual activity anchor is outside the resolved Section.');
            }
            $nextcmid = isset($ids[$index + 1]) ? (int)$ids[$index + 1] : 0;
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid,
                'position' => $nextcmid ? 'before' : 'end',
                'beforecmid' => $nextcmid,
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], $anchor['evidence'], ['manual_after']));
        }

        $prev = !empty($contract['previous'])
            ? self::resolve_candidate_with_origin(
                (array)$contract['previous'], $activities, $sourcecourseid, $targetcourseid, 'activity'
            )
            : ['status' => 'not_applicable'];
        $next = !empty($contract['next'])
            ? self::resolve_candidate_with_origin(
                (array)$contract['next'], $activities, $sourcecourseid, $targetcourseid, 'activity'
            )
            : ['status' => 'not_applicable'];

        if (($prev['status'] ?? '') === 'origin_invalid' || ($next['status'] ?? '') === 'origin_invalid') {
            return self::blocked(
                'anchor_origin_invalid',
                'A durable Activity origin mapping exists but is outside the resolved target Section.'
            );
        }
        if (($prev['status'] ?? '') === 'ambiguous' || ($next['status'] ?? '') === 'ambiguous') {
            return self::blocked('anchor_ambiguous', 'Activity placement anchor is ambiguous.');
        }
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $activities);
        if (($prev['status'] ?? '') === 'resolved' && ($next['status'] ?? '') === 'resolved') {
            $pi = array_search((int)$prev['candidate']['id'], $ids, true);
            $ni = array_search((int)$next['candidate']['id'], $ids, true);
            if ($pi === false || $ni === false || $pi >= $ni) {
                return self::blocked('anchors_contradict', 'Activity placement anchors contradict target order.');
            }
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid, 'position' => 'before', 'beforecmid' => (int)$next['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], $prev['evidence'], $next['evidence'], ['between_anchors']));
        }
        if (($prev['status'] ?? '') === 'resolved') {
            $pi = array_search((int)$prev['candidate']['id'], $ids, true);
            $nextcmid = ($pi !== false && isset($ids[$pi + 1])) ? (int)$ids[$pi + 1] : 0;
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid,
                'position' => $nextcmid ? 'before' : 'end',
                'beforecmid' => $nextcmid,
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], $prev['evidence'], ['previous_anchor']));
        }
        if (($next['status'] ?? '') === 'resolved') {
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid, 'position' => 'before', 'beforecmid' => (int)$next['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], $next['evidence'], ['next_anchor']));
        }
        if (!empty($contract['atstart'])) {
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid, 'position' => 'start', 'beforecmid' => 0,
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], ['source_start_boundary']));
        }
        if (!empty($contract['atend'])) {
            $physical = content_publisher::validate_placement($targetcourseid, [
                'targetsectionid' => $sectionid, 'position' => 'end', 'beforecmid' => 0,
            ]);
            return self::resolved($physical, array_merge($sectionresult['evidence'], ['source_end_boundary']));
        }
        return self::blocked('anchors_not_found', 'No unique activity placement anchor exists in this target.');
    }

    private static function resolve_section_contract(array $contract, int $targetcourseid): array {
        $sections = self::section_candidates($targetcourseid, true);
        if (($contract['mode'] ?? '') === 'override') {
            $position = (string)$contract['position'];
            if ($position === 'start' || $position === 'end') {
                $physical = content_publisher::validate_section_placement($targetcourseid, ['position' => $position]);
                return self::resolved($physical, ['manual_' . $position]);
            }
            $anchor = logical_placement::choose_unique_candidate((array)$contract['anchor'], $sections);
            if ($anchor['status'] !== 'resolved') {
                return self::blocked('anchor_' . $anchor['status'], 'Manual section anchor could not be resolved uniquely.', $anchor);
            }
            $physical = content_publisher::validate_section_placement($targetcourseid, [
                'position' => $position, 'anchorsectionid' => (int)$anchor['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($anchor['evidence'], ['manual_' . $position]));
        }

        $sourcecourseid = (int)($contract['sourcecourseid'] ?? 0);
        $prev = !empty($contract['previous'])
            ? self::resolve_candidate_with_origin(
                (array)$contract['previous'], $sections, $sourcecourseid, $targetcourseid, 'section'
            )
            : ['status' => 'not_applicable'];
        $next = !empty($contract['next'])
            ? self::resolve_candidate_with_origin(
                (array)$contract['next'], $sections, $sourcecourseid, $targetcourseid, 'section'
            )
            : ['status' => 'not_applicable'];
        if (($prev['status'] ?? '') === 'origin_invalid' || ($next['status'] ?? '') === 'origin_invalid') {
            return self::blocked(
                'anchor_origin_invalid',
                'A durable Section origin mapping exists but is outside the target peer-Section scope.'
            );
        }
        if (($prev['status'] ?? '') === 'ambiguous' || ($next['status'] ?? '') === 'ambiguous') {
            return self::blocked('anchor_ambiguous', 'Section placement anchor is ambiguous.');
        }
        if (($prev['status'] ?? '') === 'resolved' && ($next['status'] ?? '') === 'resolved') {
            $sectionids = array_map(static fn(array $row): int => (int)$row['id'], $sections);
            $pi = array_search((int)$prev['candidate']['id'], $sectionids, true);
            $ni = array_search((int)$next['candidate']['id'], $sectionids, true);
            if ($pi === false || $ni === false || $pi >= $ni) {
                return self::blocked('section_anchors_contradict', 'Section placement anchors contradict target order.');
            }
            $physical = content_publisher::validate_section_placement($targetcourseid, [
                'position' => 'before', 'anchorsectionid' => (int)$next['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($prev['evidence'], $next['evidence'], ['between_anchors']));
        }
        if (($prev['status'] ?? '') === 'resolved') {
            $physical = content_publisher::validate_section_placement($targetcourseid, [
                'position' => 'after', 'anchorsectionid' => (int)$prev['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($prev['evidence'], ['previous_anchor']));
        }
        if (($next['status'] ?? '') === 'resolved') {
            $physical = content_publisher::validate_section_placement($targetcourseid, [
                'position' => 'before', 'anchorsectionid' => (int)$next['candidate']['id'],
            ]);
            return self::resolved($physical, array_merge($next['evidence'], ['next_anchor']));
        }
        if (!empty($contract['atstart'])) {
            return self::resolved(content_publisher::validate_section_placement($targetcourseid, ['position' => 'start']), ['source_start_boundary']);
        }
        if (!empty($contract['atend'])) {
            return self::resolved(content_publisher::validate_section_placement($targetcourseid, ['position' => 'end']), ['source_end_boundary']);
        }
        return self::blocked('anchors_not_found', 'No unique section placement anchor exists in this target.');
    }

    private static function resolve_section_signature(array $signature, int $courseid, bool $normalonly,
            int $sourcecourseid = 0): array {
        return self::resolve_candidate_with_origin(
            $signature,
            self::section_candidates($courseid, $normalonly),
            $sourcecourseid,
            $courseid,
            'section'
        );
    }

    /**
     * Resolve a source signature by exact durable origin identity first, then
     * fall back to deterministic structural matching. A current origin map is
     * authoritative for identity but still must remain inside the caller's
     * structural candidate scope.
     */
    private static function resolve_candidate_with_origin(array $signature, array $candidates, int $sourcecourseid,
            int $targetcourseid, string $sourcetype): array {
        if ($sourcecourseid > 0 && !empty($signature['id'])) {
            $mapping = origin_map_service::find_current(
                $sourcecourseid,
                $sourcetype,
                (int)$signature['id'],
                $targetcourseid
            );
            if ($mapping) {
                if ((string)$mapping->targettype !== $sourcetype) {
                    return [
                        'status' => 'origin_invalid',
                        'candidate' => null,
                        'evidence' => ['origin_map_type_mismatch'],
                        'candidates' => 0,
                    ];
                }
                foreach ($candidates as $candidate) {
                    if ((int)($candidate['id'] ?? 0) === (int)$mapping->targetid) {
                        return [
                            'status' => 'resolved',
                            'candidate' => $candidate,
                            'evidence' => ['origin_map'],
                            'candidates' => 1,
                        ];
                    }
                }
                return [
                    'status' => 'origin_invalid',
                    'candidate' => null,
                    'evidence' => ['origin_map_out_of_scope'],
                    'candidates' => 0,
                ];
            }
        }
        return logical_placement::choose_unique_candidate($signature, $candidates);
    }

    private static function section_candidates(int $courseid, bool $normalonly): array {
        global $DB;
        $records = array_values($DB->get_records_select('course_sections', 'course = :courseid AND section > 0',
            ['courseid' => $courseid], 'section ASC'));
        $result = [];
        foreach ($records as $record) {
            $type = empty($record->component) ? 'normal' : ((string)$record->component === 'mod_subsection' ? 'delegated_subsection' : 'delegated_other');
            if ($normalonly && $type !== 'normal') {
                continue;
            }
            $result[] = self::section_signature((int)$record->id, $courseid, false);
        }
        return $result;
    }

    private static function activity_candidates_for_section(int $courseid, int $sectionid): array {
        global $DB;
        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid], '*', MUST_EXIST);
        $result = [];
        foreach (self::sequence_ids((string)$section->sequence) as $cmid) {
            $result[] = self::activity_signature((int)$cmid, $courseid, false);
        }
        return $result;
    }

    private static function section_signature(int $sectionid, int $courseid, bool $withneighbors): array {
        global $DB;
        $section = $DB->get_record('course_sections', ['id' => $sectionid, 'course' => $courseid], '*', MUST_EXIST);
        $type = empty($section->component) ? 'normal' : ((string)$section->component === 'mod_subsection' ? 'delegated_subsection' : 'delegated_other');
        $name = trim((string)$section->name);
        $parentpath = [];
        if ($type === 'delegated_subsection' && (int)$section->itemid > 0) {
            $sub = $DB->get_record('subsection', ['id' => (int)$section->itemid], 'id,name', IGNORE_MISSING);
            if ($sub && trim((string)$sub->name) !== '') {
                $name = (string)$sub->name;
            }
            $delegatecm = get_coursemodule_from_instance('subsection', (int)$section->itemid, $courseid, false, IGNORE_MISSING);
            if ($delegatecm && !empty($delegatecm->section)) {
                $parent = $DB->get_record('course_sections', ['id' => (int)$delegatecm->section, 'course' => $courseid], 'id,name,section,component,itemid', IGNORE_MISSING);
                if ($parent) {
                    $parentpath[] = ['name' => logical_placement::normalise_text((string)$parent->name), 'sectiontype' => 'normal'];
                }
            }
        }
        $signature = [
            'id' => (int)$section->id,
            'kind' => 'section',
            'sectiontype' => $type,
            'name' => logical_placement::normalise_text($name),
            'ordinal' => (int)$section->section,
            'parentpath' => $parentpath,
        ];
        if ($withneighbors && $type === 'normal') {
            $normal = array_values($DB->get_records_select('course_sections',
                'course = :courseid AND section > 0 AND (component IS NULL OR component = :empty)',
                ['courseid' => $courseid, 'empty' => ''], 'section ASC'));
            foreach ($normal as $i => $candidate) {
                if ((int)$candidate->id !== $sectionid) {
                    continue;
                }
                if ($i > 0) {
                    $signature['previous'] = self::section_signature((int)$normal[$i - 1]->id, $courseid, false);
                }
                if ($i < count($normal) - 1) {
                    $signature['next'] = self::section_signature((int)$normal[$i + 1]->id, $courseid, false);
                }
                break;
            }
        }
        return $signature;
    }

    private static function activity_signature(int $cmid, int $courseid, bool $withneighbors): array {
        global $DB;
        $cm = $DB->get_record('course_modules', ['id' => $cmid, 'course' => $courseid], '*', MUST_EXIST);
        $module = $DB->get_record('modules', ['id' => $cm->module], 'id,name', MUST_EXIST);
        $name = '';
        try {
            $modinfo = get_fast_modinfo($courseid);
            $info = $modinfo->get_cm($cmid);
            $name = (string)$info->name;
        } catch (\Throwable $ignored) {
            try {
                $name = (string)$DB->get_field((string)$module->name, 'name', ['id' => (int)$cm->instance]);
            } catch (\Throwable $ignoredagain) {
                $name = '';
            }
        }
        $section = $DB->get_record('course_sections', ['id' => $cm->section, 'course' => $courseid], '*', MUST_EXIST);
        $ids = self::sequence_ids((string)$section->sequence);
        $index = array_search($cmid, $ids, true);
        $signature = [
            'id' => $cmid,
            'kind' => 'activity',
            'modname' => (string)$module->name,
            'name' => logical_placement::normalise_text($name),
            'ordinal' => $index === false ? -1 : (int)$index,
            'section' => self::section_signature((int)$section->id, $courseid, false),
        ];
        if ($withneighbors && $index !== false) {
            if ($index > 0) {
                $signature['previous'] = self::activity_signature((int)$ids[$index - 1], $courseid, false);
            }
            if ($index < count($ids) - 1) {
                $signature['next'] = self::activity_signature((int)$ids[$index + 1], $courseid, false);
            }
        }
        return $signature;
    }

    private static function sequence_ids(string $sequence): array {
        if (trim($sequence) === '') {
            return [];
        }
        return array_values(array_filter(array_map('intval', explode(',', $sequence)), static fn(int $id): bool => $id > 0));
    }

    private static function resolved(array $physical, array $evidence): array {
        return [
            'resolverversion' => self::VERSION,
            'status' => 'resolved',
            'placement' => $physical,
            'evidence' => array_values(array_unique($evidence)),
        ];
    }

    private static function blocked(string $code, string $reason, array $details = []): array {
        return [
            'resolverversion' => self::VERSION,
            'status' => 'blocked',
            'code' => $code,
            'reason' => $reason,
            'details' => $details,
        ];
    }
}
