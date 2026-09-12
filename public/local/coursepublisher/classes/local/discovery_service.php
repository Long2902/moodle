<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only topology scanner and reconciliation service.
 */
final class discovery_service {
    public const STATUS_READY_NEW = 'READY_NEW';
    public const STATUS_EXISTING = 'EXISTING';
    public const STATUS_UNMATCHED = 'UNMATCHED';
    public const STATUS_MISSING = 'MISSING';
    public const STATUS_CONFLICT_GROUP = 'CONFLICT_GROUP';
    public const STATUS_CONFLICT_DUPLICATE_COURSE = 'CONFLICT_DUPLICATE_COURSE';
    public const STATUS_CONFLICT_EXISTING_BINDING = 'CONFLICT_EXISTING_BINDING';
    public const STATUS_CONFLICT_COURSE_REUSED = 'CONFLICT_COURSE_REUSED';
    public const STATUS_INVALID_TOPOLOGY = 'INVALID_TOPOLOGY';
    public const STATUS_EXCLUDED_MASTER = 'EXCLUDED_MASTER';

    private static function validate_selected_ids(array $requested, array $records): void {
        $requested = array_values(array_unique(array_filter(array_map('intval', $requested))));
        sort($requested, SORT_NUMERIC);
        $resolved = array_map('intval', array_keys($records));
        sort($resolved, SORT_NUMERIC);
        if ($requested !== $resolved) {
            throw new \moodle_exception('invalidparameter');
        }
    }

    private static function group_records(int $programid, array $targetgroupids): array {
        global $DB;
        if (!$targetgroupids) {
            return $DB->get_records('local_cp_target_group', ['programid' => $programid, 'enabled' => 1], 'sortorder,name');
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $targetgroupids), SQL_PARAMS_NAMED, 'tg');
        $params['programid'] = $programid;
        $sql = "SELECT * FROM {local_cp_target_group}
                 WHERE programid = :programid AND enabled = 1 AND id {$insql}
              ORDER BY sortorder, name";
        $records = $DB->get_records_sql($sql, $params);
        self::validate_selected_ids($targetgroupids, $records);
        return $records;
    }

    private static function container_records(array $containerids): array {
        global $DB;
        if (!$containerids) {
            return $DB->get_records('local_cp_discovery_container', ['enabled' => 1], 'sortorder,name');
        }
        [$insql, $params] = $DB->get_in_or_equal(array_map('intval', $containerids), SQL_PARAMS_NAMED, 'dc');
        $sql = "SELECT * FROM {local_cp_discovery_container} WHERE enabled = 1 AND id {$insql} ORDER BY sortorder,name";
        $records = $DB->get_records_sql($sql, $params);
        self::validate_selected_ids($containerids, $records);
        return $records;
    }

    private static function matches_container(string $name, array $containerrules): bool {
        foreach ($containerrules as $rule) {
            if (discovery_matcher::match_value($name, (string)$rule->matchtype, (string)$rule->pattern)) {
                return true;
            }
        }
        return false;
    }

    private static function index_many_by(array $records, string $field): array {
        $out = [];
        foreach ($records as $record) {
            $key = (int)$record->{$field};
            $out[$key][] = $record;
        }
        return $out;
    }

    /**
     * Build discovery counters from the exact rows rendered in the preview table.
     *
     * This keeps summary semantics aligned with active Target Group/container filters.
     */
    private static function summarize_rows(array $rows): array {
        $regionids = [];
        $containerids = [];
        $unitids = [];
        $courseids = [];
        $statuses = [];

        foreach ($rows as $row) {
            $regionid = (int)($row['regioncategoryid'] ?? 0);
            $containerid = (int)($row['containercategoryid'] ?? 0);
            $unitid = (int)($row['unitcategoryid'] ?? 0);
            $courseid = (int)($row['courseid'] ?? 0);
            $status = (string)($row['status'] ?? '');

            if ($regionid) {
                $regionids[$regionid] = true;
            }
            if ($containerid) {
                $containerids[$containerid] = true;
            }
            if ($unitid) {
                $unitids[$unitid] = true;
            }
            if ($courseid) {
                $courseids[$courseid] = true;
            }
            if ($status !== '') {
                $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            }
        }

        return [
            'regions' => count($regionids),
            'containers' => count($containerids),
            'units' => count($unitids),
            'courses' => count($courseids),
            'statuses' => $statuses,
        ];
    }

    private static function row_base(\stdClass $regioncat, \stdClass $containercat, \stdClass $unitcat): array {
        return [
            'regioncategoryid' => (int)$regioncat->id,
            'regionname' => (string)$regioncat->name,
            'containercategoryid' => (int)$containercat->id,
            'containername' => (string)$containercat->name,
            'unitcategoryid' => (int)$unitcat->id,
            'unitname' => (string)$unitcat->name,
        ];
    }

    private static function row(array $base, string $status, string $reason, ?\stdClass $course = null,
            ?\stdClass $group = null, array $ruleids = [], int $regionid = 0, int $schoolid = 0, int $targetbindid = 0): array {
        return $base + [
            'courseid' => $course ? (int)$course->id : 0,
            'coursename' => $course ? (string)$course->fullname : '',
            'shortname' => $course ? (string)$course->shortname : '',
            'targetgroupid' => $group ? (int)$group->id : 0,
            'groupkey' => $group ? (string)$group->groupkey : '',
            'groupname' => $group ? (string)$group->name : '',
            'matchedruleids' => array_values(array_filter(array_map('intval', $ruleids))),
            'regionid' => $regionid,
            'schoolid' => $schoolid,
            'targetbindid' => $targetbindid,
            'status' => $status,
            'reason' => $reason,
            'selectable' => $status === self::STATUS_READY_NEW,
        ];
    }

    /**
     * Scan one Program's configured deployment root. This method performs no writes.
     */
    public static function scan(int $programid, array $targetgroupids = [], array $containerids = []): array {
        global $DB;

        $program = $DB->get_record('local_cp_program', ['id' => $programid, 'enabled' => 1], '*', MUST_EXIST);
        $rootid = (int)($program->discoveryrootcategoryid ?? 0);
        if (!$rootid) {
            throw new \moodle_exception('discoveryrootrequired', 'local_coursepublisher');
        }
        $root = $DB->get_record('course_categories', ['id' => $rootid], 'id,name,parent,path', MUST_EXIST);

        $groups = self::group_records($programid, $targetgroupids);
        if (!$groups) {
            throw new \moodle_exception('discoverygroupsrequired', 'local_coursepublisher');
        }
        $containerrules = self::container_records($containerids);
        if (!$containerrules) {
            throw new \moodle_exception('discoverycontainersrequired', 'local_coursepublisher');
        }

        $groupids = array_map('intval', array_keys($groups));
        [$groupinsql, $gparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'rg');
        $rulesql = "SELECT r.*, g.groupkey
                      FROM {local_cp_discovery_rule} r
                      JOIN {local_cp_target_group} g ON g.id = r.targetgroupid
                     WHERE r.enabled = 1 AND g.enabled = 1 AND r.targetgroupid {$groupinsql}
                  ORDER BY r.priority DESC, r.sortorder, r.id";
        $rules = $DB->get_records_sql($rulesql, $gparams);

        // All scanned topology must be inside this explicit root path.
        $allcategories = $DB->get_records_select(
            'course_categories',
            'path = :rootpath OR path LIKE :rootprefix',
            ['rootpath' => $root->path, 'rootprefix' => $root->path . '/%'],
            'depth ASC, sortorder ASC, id ASC',
            'id,name,parent,path,depth,sortorder'
        );
        $regions = [];
        foreach ($allcategories as $category) {
            if ((int)$category->parent === $rootid) {
                $regions[(int)$category->id] = $category;
            }
        }

        $containers = [];
        foreach ($allcategories as $category) {
            $parent = (int)$category->parent;
            if (isset($regions[$parent]) && self::matches_container((string)$category->name, $containerrules)) {
                $containers[(int)$category->id] = $category;
            }
        }

        $units = [];
        foreach ($allcategories as $category) {
            $parent = (int)$category->parent;
            if (isset($containers[$parent])) {
                $units[(int)$category->id] = $category;
            }
        }

        // Map every category below a Unit to that Unit. A direct Unit category maps to itself.
        $unitbycategory = [];
        foreach ($units as $unitid => $unit) {
            $unitbycategory[$unitid] = $unitid;
        }
        if ($units) {
            foreach ($allcategories as $category) {
                if (isset($unitbycategory[(int)$category->id])) {
                    continue;
                }
                foreach ($units as $unitid => $unit) {
                    $needle = '/' . $unitid . '/';
                    if (strpos((string)$category->path . '/', $needle) !== false) {
                        $unitbycategory[(int)$category->id] = (int)$unitid;
                        break;
                    }
                }
            }
        }

        $coursesbyunit = [];
        if ($unitbycategory) {
            [$catsql, $catparams] = $DB->get_in_or_equal(array_keys($unitbycategory), SQL_PARAMS_NAMED, 'cc');
            $courses = $DB->get_records_select('course', "category {$catsql}", $catparams, 'fullname,id', 'id,fullname,shortname,category,visible');
            foreach ($courses as $course) {
                $unitid = $unitbycategory[(int)$course->category] ?? 0;
                if ($unitid) {
                    $coursesbyunit[$unitid][] = $course;
                }
            }
        }

        // Existing Course Publisher topology is reconciled by stable Moodle IDs, never display names.
        $regionsbycategory = self::index_many_by($DB->get_records('local_cp_region'), 'categoryid');
        $schoolsbycategory = self::index_many_by($DB->get_records('local_cp_school'), 'categoryid');
        $targets = $DB->get_records('local_cp_target', ['programid' => $programid]);
        $targetsbyroute = [];
        foreach ($targets as $target) {
            $targetsbyroute[(int)$target->schoolid . ':' . (string)$target->gradekey] = $target;
        }
        $activetargetsbycourse = self::index_many_by($DB->get_records('local_cp_target', ['enabled' => 1]), 'courseid');
        $mastercourseids = [];
        foreach ($DB->get_records('local_cp_master', ['enabled' => 1], '', 'id,courseid') as $master) {
            $mastercourseids[(int)$master->courseid] = true;
        }

        $rows = [];
        foreach ($units as $unitid => $unitcat) {
            $containercat = $containers[(int)$unitcat->parent];
            $regioncat = $regions[(int)$containercat->parent];
            $base = self::row_base($regioncat, $containercat, $unitcat);

            $regionrecords = $regionsbycategory[(int)$regioncat->id] ?? [];
            $schoolrecords = $schoolsbycategory[(int)$unitcat->id] ?? [];
            $region = count($regionrecords) === 1 ? reset($regionrecords) : null;
            $school = count($schoolrecords) === 1 ? reset($schoolrecords) : null;
            $topologyreason = '';
            if (count($regionrecords) > 1) {
                $topologyreason = 'Multiple Course Publisher Regions use this Moodle category.';
            } else if ($region && empty($region->enabled)) {
                $topologyreason = 'Existing Course Publisher Region is disabled.';
            } else if (count($schoolrecords) > 1) {
                $topologyreason = 'Multiple Course Publisher Units use this Moodle category.';
            } else if ($school && empty($school->enabled)) {
                $topologyreason = 'Existing Course Publisher Unit is disabled.';
            } else if ($school && (!$region || (int)$school->regionid !== (int)$region->id)) {
                $topologyreason = 'Existing Course Publisher Unit belongs to a different Region binding.';
            }
            $regionid = $region ? (int)$region->id : 0;
            $schoolid = $school ? (int)$school->id : 0;

            $matched = [];
            foreach ($coursesbyunit[$unitid] ?? [] as $course) {
                if (isset($mastercourseids[(int)$course->id])) {
                    $rows[] = self::row($base, self::STATUS_EXCLUDED_MASTER, 'Course is an active master and cannot be a target.', $course, null, [], $regionid, $schoolid);
                    continue;
                }
                $classification = discovery_matcher::classify_course($course, $rules);
                if ($classification['status'] === 'unmatched') {
                    $rows[] = self::row($base, self::STATUS_UNMATCHED, 'No enabled recognition rule matched this course.', $course, null, [], $regionid, $schoolid);
                    continue;
                }
                if ($classification['status'] === 'conflict') {
                    $rows[] = self::row($base, self::STATUS_CONFLICT_GROUP, 'Course matched rules from more than one Target Group.', $course, null, $classification['ruleids'], $regionid, $schoolid);
                    continue;
                }
                $groupid = (int)$classification['targetgroupid'];
                if (!isset($groups[$groupid])) {
                    continue;
                }
                $matched[$groupid][] = [$course, $classification];
            }

            foreach ($groups as $groupid => $group) {
                $candidates = $matched[(int)$groupid] ?? [];
                if (!$candidates) {
                    $rows[] = self::row($base, self::STATUS_MISSING, 'No course matched this Target Group in the Unit.', null, $group, [], $regionid, $schoolid);
                    continue;
                }
                if (count($candidates) > 1) {
                    foreach ($candidates as [$course, $classification]) {
                        $rows[] = self::row($base, self::STATUS_CONFLICT_DUPLICATE_COURSE, 'More than one course in this Unit matched the same Target Group.', $course, $group, $classification['ruleids'], $regionid, $schoolid);
                    }
                    continue;
                }
                [$course, $classification] = $candidates[0];
                if ($topologyreason !== '') {
                    $rows[] = self::row($base, self::STATUS_INVALID_TOPOLOGY, $topologyreason, $course, $group, $classification['ruleids'], $regionid, $schoolid);
                    continue;
                }

                $route = $schoolid ? ($targetsbyroute[$schoolid . ':' . (string)$group->groupkey] ?? null) : null;
                if ($route) {
                    if ((int)$route->courseid === (int)$course->id && !empty($route->enabled)) {
                        $rows[] = self::row($base, self::STATUS_EXISTING, 'Target binding already exists.', $course, $group, $classification['ruleids'], $regionid, $schoolid, (int)$route->id);
                    } else {
                        $rows[] = self::row($base, self::STATUS_CONFLICT_EXISTING_BINDING, 'An existing binding for this Unit and Target Group points elsewhere or is disabled.', $course, $group, $classification['ruleids'], $regionid, $schoolid, (int)$route->id);
                    }
                    continue;
                }

                $reuse = $activetargetsbycourse[(int)$course->id] ?? [];
                if ($reuse) {
                    $rows[] = self::row($base, self::STATUS_CONFLICT_COURSE_REUSED, 'Course is already used by another active target binding.', $course, $group, $classification['ruleids'], $regionid, $schoolid);
                    continue;
                }
                $rows[] = self::row($base, self::STATUS_READY_NEW, 'Safe new target binding candidate.', $course, $group, $classification['ruleids'], $regionid, $schoolid);
            }
        }

        $summary = self::summarize_rows($rows);

        service::audit('discovery_scan', 'program', $programid, [
            'rootcategoryid' => $rootid,
            'targetgroupids' => $groupids,
            'containerids' => array_map('intval', array_keys($containerrules)),
            'summary' => $summary,
        ]);

        return [
            'program' => $program,
            'root' => $root,
            'groups' => $groups,
            'containers' => $containerrules,
            'rules' => $rules,
            'rows' => $rows,
            'summary' => $summary,
        ];
    }

    public static function candidate_key(array $row): string {
        return implode(':', [
            (int)($row['regioncategoryid'] ?? 0),
            (int)($row['containercategoryid'] ?? 0),
            (int)($row['unitcategoryid'] ?? 0),
            (int)($row['courseid'] ?? 0),
            (int)($row['targetgroupid'] ?? 0),
        ]);
    }

    private static function parse_candidate_key(string $key): ?array {
        if (preg_match('/^(\d+):(\d+):(\d+):(\d+):(\d+)$/', $key, $matches) !== 1) {
            return null;
        }
        return [
            'regioncategoryid' => (int)$matches[1],
            'containercategoryid' => (int)$matches[2],
            'unitcategoryid' => (int)$matches[3],
            'courseid' => (int)$matches[4],
            'targetgroupid' => (int)$matches[5],
        ];
    }

    /**
     * Revalidate and register selected READY candidates. No content is published.
     */
    public static function register_selected(int $programid, array $selection, array $targetgroupids = [],
            array $containerids = []): array {
        global $DB;

        $selection = array_values(array_unique(array_filter(array_map('strval', $selection))));
        if (!$selection) {
            return ['created' => 0, 'existing' => 0, 'failed' => 0, 'results' => []];
        }

        // Re-scan immediately before any configuration write. Preview state is never trusted.
        $fresh = self::scan($programid, $targetgroupids, $containerids);
        $freshbykey = [];
        foreach ($fresh['rows'] as $row) {
            if (!empty($row['courseid']) && !empty($row['targetgroupid'])) {
                $freshbykey[self::candidate_key($row)] = $row;
            }
        }

        $results = [];
        $createdcount = 0;
        $existingcount = 0;
        $failedcount = 0;
        foreach ($selection as $key) {
            $parsed = self::parse_candidate_key($key);
            if (!$parsed || !isset($freshbykey[$key])) {
                $results[] = ['key' => $key, 'status' => 'FAILED_REVALIDATION', 'reason' => 'Candidate no longer exists in the current discovery scan.'];
                $failedcount++;
                continue;
            }
            $row = $freshbykey[$key];
            if ($row['status'] === self::STATUS_EXISTING) {
                $results[] = ['key' => $key, 'status' => 'BECAME_EXISTING', 'reason' => $row['reason'], 'targetbindid' => (int)$row['targetbindid']];
                $existingcount++;
                continue;
            }
            if ($row['status'] !== self::STATUS_READY_NEW) {
                $results[] = ['key' => $key, 'status' => 'FAILED_REVALIDATION', 'reason' => $row['status'] . ': ' . $row['reason']];
                $failedcount++;
                continue;
            }

            try {
                $transaction = $DB->start_delegated_transaction();
                try {
                    $regionmatches = $DB->get_records('local_cp_region', ['categoryid' => (int)$row['regioncategoryid']]);
                    if (count($regionmatches) > 1) {
                        throw new \moodle_exception('discoveryduplicateregion', 'local_coursepublisher');
                    }
                    $region = $regionmatches ? reset($regionmatches) : null;
                    $regioncreated = false;
                    if ($region) {
                        if (empty($region->enabled)) {
                            throw new \moodle_exception('discoverydisabledtopology', 'local_coursepublisher');
                        }
                        $regionid = (int)$region->id;
                    } else {
                        $regionid = service::save_region((object)[
                            'code' => 'AUTO_R_' . (int)$row['regioncategoryid'],
                            'name' => (string)$row['regionname'],
                            'categoryid' => (int)$row['regioncategoryid'],
                            'enabled' => 1,
                        ]);
                        $regioncreated = true;
                        service::audit('discovery_register_region', 'region', $regionid, ['categoryid' => (int)$row['regioncategoryid'], 'programid' => $programid]);
                    }

                    $schoolmatches = $DB->get_records('local_cp_school', ['categoryid' => (int)$row['unitcategoryid']]);
                    if (count($schoolmatches) > 1) {
                        throw new \moodle_exception('discoveryduplicateunit', 'local_coursepublisher');
                    }
                    $school = $schoolmatches ? reset($schoolmatches) : null;
                    $schoolcreated = false;
                    if ($school) {
                        if (empty($school->enabled) || (int)$school->regionid !== $regionid) {
                            throw new \moodle_exception('discoverydisabledtopology', 'local_coursepublisher');
                        }
                        $schoolid = (int)$school->id;
                    } else {
                        $schoolid = service::save_school((object)[
                            'regionid' => $regionid,
                            'code' => 'AUTO_U_' . (int)$row['unitcategoryid'],
                            'name' => (string)$row['unitname'],
                            'categoryid' => (int)$row['unitcategoryid'],
                            'enabled' => 1,
                        ]);
                        $schoolcreated = true;
                        service::audit('discovery_register_unit', 'school', $schoolid, ['categoryid' => (int)$row['unitcategoryid'], 'programid' => $programid]);
                    }

                    // A concurrent registration may have created this route after the re-scan.
                    $route = $DB->get_record('local_cp_target', [
                        'programid' => $programid,
                        'schoolid' => $schoolid,
                        'gradekey' => (string)$row['groupkey'],
                    ]);
                    if ($route) {
                        if ((int)$route->courseid === (int)$row['courseid'] && !empty($route->enabled)) {
                            $transaction->allow_commit();
                            $results[] = ['key' => $key, 'status' => 'BECAME_EXISTING', 'reason' => 'Binding was created concurrently.', 'targetbindid' => (int)$route->id];
                            $existingcount++;
                            continue;
                        }
                        throw new \moodle_exception('discoveryexistingbindingconflict', 'local_coursepublisher');
                    }

                    $targetid = service::save_target((object)[
                        'programid' => $programid,
                        'schoolid' => $schoolid,
                        'gradekey' => (string)$row['groupkey'],
                        'courseid' => (int)$row['courseid'],
                        'enabled' => 1,
                    ]);
                    service::audit('discovery_register_target', 'target', $targetid, [
                        'programid' => $programid,
                        'targetgroupid' => (int)$row['targetgroupid'],
                        'courseid' => (int)$row['courseid'],
                        'regioncreated' => $regioncreated,
                        'unitcreated' => $schoolcreated,
                    ]);
                    $transaction->allow_commit();
                    $results[] = ['key' => $key, 'status' => 'CREATED', 'reason' => 'Target binding created.', 'targetbindid' => $targetid];
                    $createdcount++;
                } catch (\Throwable $e) {
                    $transaction->rollback($e);
                }
            } catch (\Throwable $e) {
                $results[] = ['key' => $key, 'status' => 'FAILED_WRITE', 'reason' => $e->getMessage()];
                $failedcount++;
            }
        }

        return [
            'created' => $createdcount,
            'existing' => $existingcount,
            'failed' => $failedcount,
            'results' => $results,
        ];
    }
}
