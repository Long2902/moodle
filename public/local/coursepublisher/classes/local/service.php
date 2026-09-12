<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Core Case 1 topology and dry-run services.
 */
final class service {
    public const LEGACY_GRADES = ['10', '11', '12'];

    public static function normalise_code(string $code): string {
        $code = trim(\core_text::strtoupper($code));
        $code = preg_replace('/[^A-Z0-9_-]+/u', '_', $code);
        return trim($code, '_');
    }

    /**
     * Backward-compatible syntax guard for the historical gradekey route parameter.
     * The value is now a Target Group key and is no longer limited to 10/11/12.
     */
    public static function assert_grade(string $gradekey): void {
        $gradekey = trim($gradekey);
        if ($gradekey === '' || strlen($gradekey) > 64 || preg_match('/^[A-Za-z0-9_-]+$/', $gradekey) !== 1) {
            throw new \moodle_exception('invalidparameter');
        }
    }

    public static function resolve_target_group(int $programid, string $gradekey, bool $enabledonly = false): \stdClass {
        global $DB;

        self::assert_grade($gradekey);
        $params = ['programid' => $programid, 'groupkey' => $gradekey];
        if ($enabledonly) {
            $params['enabled'] = 1;
        }
        $group = $DB->get_record('local_cp_target_group', $params);
        if (!$group) {
            throw new \moodle_exception('targetgroupnotfound', 'local_coursepublisher', '', $gradekey);
        }
        return $group;
    }

    public static function target_group_options(int $programid = 0, bool $enabledonly = true): array {
        global $DB;

        $params = [];
        if ($programid) {
            $params['programid'] = $programid;
        }
        if ($enabledonly) {
            $params['enabled'] = 1;
        }
        $groups = $DB->get_records('local_cp_target_group', $params, 'programid ASC, sortorder ASC, name ASC');
        $out = [];
        foreach ($groups as $group) {
            $key = (string)$group->groupkey;
            $label = format_string($group->name) . ' [' . s($key) . ']';
            if (!isset($out[$key])) {
                $out[$key] = $label;
            }
        }
        return $out;
    }

    public static function target_group_id_options(int $programid = 0, bool $enabledonly = true): array {
        global $DB;

        $params = [];
        if ($programid) {
            $params['programid'] = $programid;
        }
        if ($enabledonly) {
            $params['enabled'] = 1;
        }
        $sql = "SELECT g.*, p.name AS programname, p.code AS programcode
                  FROM {local_cp_target_group} g
                  JOIN {local_cp_program} p ON p.id = g.programid";
        $where = [];
        $sqlparams = [];
        if ($programid) {
            $where[] = 'g.programid = :programid';
            $sqlparams['programid'] = $programid;
        }
        if ($enabledonly) {
            $where[] = 'g.enabled = 1';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.name, g.sortorder, g.name';
        $groups = $DB->get_records_sql($sql, $sqlparams);
        $out = [];
        foreach ($groups as $group) {
            $out[(int)$group->id] = format_string($group->programname) . ' / ' . format_string($group->name) . ' [' . s($group->groupkey) . ']';
        }
        return $out;
    }

    public static function target_group_label(int $programid, string $gradekey): string {
        global $DB;

        $group = $DB->get_record('local_cp_target_group', ['programid' => $programid, 'groupkey' => $gradekey], 'id,name,groupkey');
        if ($group) {
            return format_string($group->name) . ' [' . s($group->groupkey) . ']';
        }
        return s($gradekey);
    }

    public static function save_target_group(\stdClass $data): int {
        global $DB;

        $programid = (int)$data->programid;
        $DB->get_record('local_cp_program', ['id' => $programid], '*', MUST_EXIST);
        $groupkey = self::normalise_code((string)$data->groupkey);
        self::assert_grade($groupkey);
        $name = trim((string)$data->name);
        if ($name === '') {
            throw new \moodle_exception('invalidparameter');
        }
        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_target_group', ['id' => (int)$data->id], '*', MUST_EXIST);
            if ((string)$record->groupkey !== $groupkey) {
                throw new \moodle_exception('targetgroupkeyimmutable', 'local_coursepublisher');
            }
            if ((int)$record->programid !== $programid) {
                throw new \moodle_exception('targetgroupprogramimmutable', 'local_coursepublisher');
            }
            if ($DB->record_exists_select('local_cp_target_group', 'programid = ? AND groupkey = ? AND id <> ?', [$programid, $groupkey, $record->id])) {
                throw new \moodle_exception('duplicatetargetgroup', 'local_coursepublisher');
            }
            $record->programid = $programid;
            $record->name = $name;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->sortorder = (int)($data->sortorder ?? 0);
            $record->timemodified = $now;
            $DB->update_record('local_cp_target_group', $record);
            self::audit('target_group_update', 'target_group', (int)$record->id, ['programid' => $programid, 'groupkey' => $groupkey]);
            return (int)$record->id;
        }
        if ($DB->record_exists('local_cp_target_group', ['programid' => $programid, 'groupkey' => $groupkey])) {
            throw new \moodle_exception('duplicatetargetgroup', 'local_coursepublisher');
        }
        $record = (object)[
            'programid' => $programid,
            'groupkey' => $groupkey,
            'name' => $name,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'sortorder' => (int)($data->sortorder ?? 0),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = (int)$DB->insert_record('local_cp_target_group', $record);
        self::audit('target_group_create', 'target_group', $id, ['programid' => $programid, 'groupkey' => $groupkey]);
        return $id;
    }

    private static function default_discovery_rule_exists(int $targetgroupid, string $fieldname,
            string $matchtype, string $pattern): bool {
        global $DB;

        $records = $DB->get_records('local_cp_discovery_rule', [
            'targetgroupid' => $targetgroupid,
            'fieldname' => $fieldname,
            'matchtype' => $matchtype,
        ], '', 'id,pattern');
        foreach ($records as $record) {
            if ((string)$record->pattern === $pattern) {
                return true;
            }
        }
        return false;
    }

    /**
     * Seed the conservative built-in Target Groups for a newly created Program.
     * Existing programs are migrated in db/upgrade.php; special groups remain administrator-defined.
     */
    private static function ensure_default_target_groups(int $programid): void {
        global $DB;

        $now = time();
        $defaults = [
            '10' => 'Lớp 10',
            '11' => 'Lớp 11',
            '12' => 'Lớp 12',
        ];
        $sortorder = 10;
        foreach ($defaults as $groupkey => $name) {
            $group = $DB->get_record('local_cp_target_group', [
                'programid' => $programid,
                'groupkey' => $groupkey,
            ]);
            if (!$group) {
                $group = (object)[
                    'programid' => $programid,
                    'groupkey' => $groupkey,
                    'name' => $name,
                    'enabled' => 1,
                    'sortorder' => $sortorder,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $group->id = (int)$DB->insert_record('local_cp_target_group', $group);
                self::audit('target_group_create', 'target_group', (int)$group->id, [
                    'programid' => $programid,
                    'groupkey' => $groupkey,
                    'source' => 'program_default',
                ]);
            }

            foreach ([
                ['fullname', 'contains', 'LỚP ' . $groupkey, 'Tên khóa chứa LỚP ' . $groupkey],
                ['shortname', 'contains', '_L' . $groupkey . '_', 'Shortname chứa _L' . $groupkey . '_'],
            ] as [$fieldname, $matchtype, $pattern, $rulename]) {
                if (self::default_discovery_rule_exists(
                        (int)$group->id, $fieldname, $matchtype, $pattern)) {
                    continue;
                }
                $ruleid = (int)$DB->insert_record('local_cp_discovery_rule', (object)[
                    'targetgroupid' => (int)$group->id,
                    'name' => $rulename,
                    'fieldname' => $fieldname,
                    'matchtype' => $matchtype,
                    'pattern' => $pattern,
                    'priority' => 100,
                    'enabled' => 1,
                    'sortorder' => 0,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
                self::audit('discovery_rule_create', 'discovery_rule', $ruleid, [
                    'targetgroupid' => (int)$group->id,
                    'source' => 'program_default',
                ]);
            }
            $sortorder += 10;
        }
    }

    /**
     * Return course options with category context so large sites are safer to search.
     */
    public static function course_options(): array {
        global $DB, $SITE;

        $categories = \core_course_category::make_categories_list();
        $records = $DB->get_records_select(
            'course',
            'id <> ?',
            [$SITE->id],
            'fullname ASC',
            'id, fullname, shortname, category'
        );
        $out = [0 => get_string('selectcourse', 'local_coursepublisher')];
        foreach ($records as $course) {
            $category = $categories[$course->category] ?? ('#' . $course->category);
            $out[$course->id] = format_string($course->fullname) . ' [' . $course->shortname . '] (#' . $course->id . ')' .
                ' — ' . $category;
        }
        return $out;
    }

    public static function category_options(): array {
        $out = [0 => get_string('selectcategory', 'local_coursepublisher')];
        foreach (\core_course_category::make_categories_list() as $id => $name) {
            $out[$id] = $name . ' (#' . $id . ')';
        }
        return $out;
    }

    public static function category_label(int $categoryid): string {
        if (!$categoryid) {
            return get_string('notconfigured', 'local_coursepublisher');
        }
        $categories = \core_course_category::make_categories_list();
        return ($categories[$categoryid] ?? ('#' . $categoryid)) . ' (#' . $categoryid . ')';
    }

    public static function program_options(bool $enabledonly = true): array {
        global $DB;
        $params = $enabledonly ? ['enabled' => 1] : [];
        $programs = $DB->get_records('local_cp_program', $params, 'name ASC');
        $out = [0 => get_string('selectprogram', 'local_coursepublisher')];
        foreach ($programs as $program) {
            $out[$program->id] = $program->name . ' [' . $program->code . ']';
        }
        return $out;
    }

    public static function discovery_container_options(bool $enabledonly = true): array {
        global $DB;
        $params = $enabledonly ? ['enabled' => 1] : [];
        $records = $DB->get_records('local_cp_discovery_container', $params, 'sortorder,name');
        $out = [];
        foreach ($records as $record) {
            $out[(int)$record->id] = format_string($record->name) . ' — ' . s($record->pattern);
        }
        return $out;
    }

    public static function region_options(bool $enabledonly = true): array {
        global $DB;
        $params = $enabledonly ? ['enabled' => 1] : [];
        $regions = $DB->get_records('local_cp_region', $params, 'name ASC');
        $out = [0 => get_string('selectregion', 'local_coursepublisher')];
        foreach ($regions as $region) {
            $out[$region->id] = $region->name . ' [' . $region->code . ']';
        }
        return $out;
    }

    public static function school_options(bool $enabledonly = true): array {
        global $DB;

        $where = $enabledonly ? 'WHERE s.enabled = 1 AND r.enabled = 1' : '';
        $sql = "SELECT s.id, s.name, s.code, r.name AS regionname
                  FROM {local_cp_school} s
                  JOIN {local_cp_region} r ON r.id = s.regionid
                {$where}
              ORDER BY r.name, s.name";
        $schools = $DB->get_records_sql($sql);
        $out = [0 => get_string('selectschool', 'local_coursepublisher')];
        foreach ($schools as $school) {
            $out[$school->id] = $school->regionname . ' / ' . $school->name . ' [' . $school->code . ']';
        }
        return $out;
    }

    public static function audit(string $action, string $entitytype, int $entityid, array $details = []): void {
        global $DB, $USER;
        $record = (object)[
            'userid' => (int)$USER->id,
            'action' => $action,
            'entitytype' => $entitytype,
            'entityid' => $entityid,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timecreated' => time(),
        ];
        $DB->insert_record('local_cp_audit', $record);
    }

    public static function save_program(\stdClass $data): int {
        global $DB;
        $code = self::normalise_code($data->code);
        if ($code === '') {
            throw new \moodle_exception('invalidparameter');
        }
        $rootcategoryid = (int)($data->discoveryrootcategoryid ?? 0);
        if ($rootcategoryid && !$DB->record_exists('course_categories', ['id' => $rootcategoryid])) {
            throw new \moodle_exception('categorymissing', 'local_coursepublisher');
        }
        $now = time();
        if (!empty($data->id)) {
            if ($DB->record_exists_select('local_cp_program', 'code = ? AND id <> ?', [$code, $data->id])) {
                throw new \moodle_exception('duplicateprogram', 'local_coursepublisher');
            }
            $record = $DB->get_record('local_cp_program', ['id' => $data->id], '*', MUST_EXIST);
            $record->code = $code;
            $record->name = trim($data->name);
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->discoveryrootcategoryid = $rootcategoryid;
            $record->timemodified = $now;
            $DB->update_record('local_cp_program', $record);
            self::audit('update', 'program', $record->id, ['code' => $code]);
            return (int)$record->id;
        }
        if ($DB->record_exists('local_cp_program', ['code' => $code])) {
            throw new \moodle_exception('duplicateprogram', 'local_coursepublisher');
        }
        $record = (object)[
            'code' => $code,
            'name' => trim($data->name),
            'enabled' => empty($data->enabled) ? 0 : 1,
            'discoveryrootcategoryid' => $rootcategoryid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        $id = (int)$DB->insert_record('local_cp_program', $record);
        self::ensure_default_target_groups($id);
        self::audit('create', 'program', $id, ['code' => $code, 'discoveryrootcategoryid' => $rootcategoryid]);
        $transaction->allow_commit();
        return $id;
    }

    private static function validate_master_candidate(int $programid, string $gradekey, int $courseid, int $currentid = 0): void {
        global $DB;

        $group = self::resolve_target_group($programid, $gradekey);
        $DB->get_record('local_cp_program', ['id' => $programid], '*', MUST_EXIST);
        $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $params = [$programid, $courseid, $gradekey, $currentid];
        if ($DB->record_exists_select(
            'local_cp_master',
            'programid = ? AND courseid = ? AND gradekey <> ? AND enabled = 1 AND id <> ?',
            $params
        )) {
            throw new \moodle_exception('masterreusedgrade', 'local_coursepublisher');
        }
        if ($DB->record_exists('local_cp_target', ['courseid' => $courseid, 'enabled' => 1])) {
            throw new \moodle_exception('masteristarget', 'local_coursepublisher');
        }
    }

    public static function save_master(\stdClass $data): int {
        global $DB;

        $group = self::resolve_target_group((int)$data->programid, (string)$data->gradekey);
        $existing = $DB->get_record('local_cp_master', [
            'programid' => $data->programid,
            'gradekey' => $data->gradekey,
            'targetgroupid' => (int)$group->id,
        ]);
        if ($existing && empty($data->id)) {
            $data->id = $existing->id;
        }
        self::validate_master_candidate(
            (int)$data->programid,
            (string)$data->gradekey,
            (int)$data->courseid,
            (int)($data->id ?? 0)
        );

        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_master', ['id' => $data->id], '*', MUST_EXIST);
            $record->programid = $data->programid;
            $record->gradekey = $data->gradekey;
            $record->targetgroupid = (int)$group->id;
            $record->courseid = $data->courseid;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->timemodified = $now;
            $DB->update_record('local_cp_master', $record);
            self::audit('update', 'master', $record->id, ['courseid' => $record->courseid, 'grade' => $record->gradekey]);
            return (int)$record->id;
        }
        $record = (object)[
            'programid' => $data->programid,
            'gradekey' => $data->gradekey,
            'targetgroupid' => (int)$group->id,
            'courseid' => $data->courseid,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('local_cp_master', $record);
        self::audit('create', 'master', $id, ['courseid' => $record->courseid, 'grade' => $record->gradekey]);
        return (int)$id;
    }

    public static function save_region(\stdClass $data): int {
        global $DB;

        $code = self::normalise_code($data->code);
        if (empty($data->categoryid)) {
            throw new \moodle_exception('regioncategoryrequired', 'local_coursepublisher');
        }
        $DB->get_record('course_categories', ['id' => $data->categoryid], '*', MUST_EXIST);
        if (!empty($data->id) && $DB->record_exists_select('local_cp_region', 'code = ? AND id <> ?', [$code, $data->id])) {
            throw new \moodle_exception('duplicateregion', 'local_coursepublisher');
        }
        if (empty($data->id) && $DB->record_exists('local_cp_region', ['code' => $code])) {
            throw new \moodle_exception('duplicateregion', 'local_coursepublisher');
        }
        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_region', ['id' => $data->id], '*', MUST_EXIST);
            $record->code = $code;
            $record->name = trim($data->name);
            $record->categoryid = (int)$data->categoryid;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->timemodified = $now;
            $DB->update_record('local_cp_region', $record);
            self::audit('update', 'region', $record->id, ['categoryid' => $record->categoryid]);
            return (int)$record->id;
        }
        $record = (object)[
            'code' => $code,
            'name' => trim($data->name),
            'categoryid' => (int)$data->categoryid,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('local_cp_region', $record);
        self::audit('create', 'region', $id, ['categoryid' => $record->categoryid]);
        return (int)$id;
    }

    public static function category_is_descendant(int $childcategoryid, int $ancestorcategoryid): bool {
        global $DB;

        if (!$childcategoryid || !$ancestorcategoryid || $childcategoryid === $ancestorcategoryid) {
            return false;
        }
        $child = $DB->get_record('course_categories', ['id' => $childcategoryid], 'id,path');
        if (!$child) {
            return false;
        }
        $needle = '/' . $ancestorcategoryid . '/';
        return strpos($child->path . '/', $needle) !== false;
    }

    private static function validate_school_category(\stdClass $school, \stdClass $region): void {
        global $DB;

        if (empty($region->categoryid)) {
            throw new \moodle_exception('regioncategorymissing', 'local_coursepublisher');
        }
        if (empty($school->categoryid)) {
            throw new \moodle_exception('schoolcategoryrequired', 'local_coursepublisher');
        }
        if (!$DB->record_exists('course_categories', ['id' => $region->categoryid]) ||
                !$DB->record_exists('course_categories', ['id' => $school->categoryid])) {
            throw new \moodle_exception('categorymissing', 'local_coursepublisher');
        }
        if (!self::category_is_descendant((int)$school->categoryid, (int)$region->categoryid)) {
            throw new \moodle_exception('schoolwrongregion', 'local_coursepublisher');
        }
    }

    public static function save_school(\stdClass $data): int {
        global $DB;

        $code = self::normalise_code($data->code);
        $region = $DB->get_record('local_cp_region', ['id' => $data->regionid], '*', MUST_EXIST);
        $candidate = (object)['categoryid' => (int)$data->categoryid];
        self::validate_school_category($candidate, $region);

        if (!empty($data->id) && $DB->record_exists_select('local_cp_school', 'code = ? AND id <> ?', [$code, $data->id])) {
            throw new \moodle_exception('duplicateschool', 'local_coursepublisher');
        }
        if (empty($data->id) && $DB->record_exists('local_cp_school', ['code' => $code])) {
            throw new \moodle_exception('duplicateschool', 'local_coursepublisher');
        }
        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_school', ['id' => $data->id], '*', MUST_EXIST);
            $record->regionid = $data->regionid;
            $record->code = $code;
            $record->name = trim($data->name);
            $record->categoryid = (int)$data->categoryid;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->timemodified = $now;
            $DB->update_record('local_cp_school', $record);
            self::audit('update', 'school', $record->id, ['categoryid' => $record->categoryid]);
            return (int)$record->id;
        }
        $record = (object)[
            'regionid' => $data->regionid,
            'code' => $code,
            'name' => trim($data->name),
            'categoryid' => (int)$data->categoryid,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('local_cp_school', $record);
        self::audit('create', 'school', $id, ['categoryid' => $record->categoryid]);
        return (int)$id;
    }

    public static function course_is_inside_school(int $courseid, \stdClass $school): bool {
        global $DB;

        if (empty($school->categoryid)) {
            return false;
        }
        $course = $DB->get_record('course', ['id' => $courseid], 'id,category');
        if (!$course) {
            return false;
        }
        $category = $DB->get_record('course_categories', ['id' => $course->category], 'id,path');
        if (!$category) {
            return false;
        }
        $needle = '/' . (int)$school->categoryid;
        return (int)$category->id === (int)$school->categoryid ||
            preg_match('~' . preg_quote($needle, '~') . '(?:/|$)~', $category->path) === 1;
    }

    private static function validate_target_candidate(\stdClass $data, int $currentid = 0): void {
        global $DB;

        $group = self::resolve_target_group((int)$data->programid, (string)$data->gradekey);
        $DB->get_record('local_cp_program', ['id' => $data->programid], '*', MUST_EXIST);
        $school = $DB->get_record('local_cp_school', ['id' => $data->schoolid], '*', MUST_EXIST);
        $region = $DB->get_record('local_cp_region', ['id' => $school->regionid], '*', MUST_EXIST);
        self::validate_school_category($school, $region);
        $DB->get_record('course', ['id' => $data->courseid], '*', MUST_EXIST);

        if ($DB->record_exists('local_cp_master', ['courseid' => $data->courseid, 'enabled' => 1])) {
            throw new \moodle_exception('targetismaster', 'local_coursepublisher');
        }
        if (!self::course_is_inside_school((int)$data->courseid, $school)) {
            throw new \moodle_exception('targetwrongcategory', 'local_coursepublisher');
        }
        if ($DB->record_exists_select(
            'local_cp_target',
            'courseid = ? AND enabled = 1 AND id <> ?',
            [(int)$data->courseid, $currentid]
        )) {
            throw new \moodle_exception('targetreused', 'local_coursepublisher');
        }
    }

    public static function save_target(\stdClass $data): int {
        global $DB;

        $group = self::resolve_target_group((int)$data->programid, (string)$data->gradekey);
        $existing = $DB->get_record('local_cp_target', [
            'programid' => $data->programid,
            'schoolid' => $data->schoolid,
            'gradekey' => $data->gradekey,
            'targetgroupid' => (int)$group->id,
        ]);
        if ($existing && empty($data->id)) {
            $data->id = $existing->id;
        }
        self::validate_target_candidate($data, (int)($data->id ?? 0));

        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_target', ['id' => $data->id], '*', MUST_EXIST);
            $record->programid = $data->programid;
            $record->schoolid = $data->schoolid;
            $record->gradekey = $data->gradekey;
            $record->targetgroupid = (int)$group->id;
            $record->courseid = $data->courseid;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->timemodified = $now;
            $DB->update_record('local_cp_target', $record);
            self::audit('update', 'target', $record->id, ['courseid' => $record->courseid, 'grade' => $record->gradekey]);
            return (int)$record->id;
        }
        $record = (object)[
            'programid' => $data->programid,
            'schoolid' => $data->schoolid,
            'gradekey' => $data->gradekey,
            'targetgroupid' => (int)$group->id,
            'courseid' => $data->courseid,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = $DB->insert_record('local_cp_target', $record);
        self::audit('create', 'target', $id, ['courseid' => $record->courseid, 'grade' => $record->gradekey]);
        return (int)$id;
    }

    private static function validate_match_definition(string $matchtype, string $pattern): void {
        if (!in_array($matchtype, discovery_matcher::MATCH_TYPES, true) || trim($pattern) === '') {
            throw new \moodle_exception('invaliddiscoveryrule', 'local_coursepublisher');
        }
        if ($matchtype === 'regex' && !discovery_matcher::validate_regex($pattern)) {
            throw new \moodle_exception('invalidregex', 'local_coursepublisher');
        }
    }

    public static function save_discovery_rule(\stdClass $data): int {
        global $DB;

        $group = $DB->get_record('local_cp_target_group', ['id' => (int)$data->targetgroupid], '*', MUST_EXIST);
        $fieldname = (string)$data->fieldname;
        $matchtype = (string)$data->matchtype;
        $pattern = trim((string)$data->pattern);
        if (!in_array($fieldname, discovery_matcher::COURSE_FIELDS, true)) {
            throw new \moodle_exception('invaliddiscoveryrule', 'local_coursepublisher');
        }
        self::validate_match_definition($matchtype, $pattern);
        $name = trim((string)$data->name);
        if ($name === '') {
            throw new \moodle_exception('invalidparameter');
        }
        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_discovery_rule', ['id' => (int)$data->id], '*', MUST_EXIST);
            $record->targetgroupid = (int)$group->id;
            $record->name = $name;
            $record->fieldname = $fieldname;
            $record->matchtype = $matchtype;
            $record->pattern = $pattern;
            $record->priority = (int)($data->priority ?? 100);
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->sortorder = (int)($data->sortorder ?? 0);
            $record->timemodified = $now;
            $DB->update_record('local_cp_discovery_rule', $record);
            self::audit('discovery_rule_update', 'discovery_rule', (int)$record->id, ['targetgroupid' => (int)$group->id]);
            return (int)$record->id;
        }
        $record = (object)[
            'targetgroupid' => (int)$group->id,
            'name' => $name,
            'fieldname' => $fieldname,
            'matchtype' => $matchtype,
            'pattern' => $pattern,
            'priority' => (int)($data->priority ?? 100),
            'enabled' => empty($data->enabled) ? 0 : 1,
            'sortorder' => (int)($data->sortorder ?? 0),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = (int)$DB->insert_record('local_cp_discovery_rule', $record);
        self::audit('discovery_rule_create', 'discovery_rule', $id, ['targetgroupid' => (int)$group->id]);
        return $id;
    }

    public static function save_discovery_container(\stdClass $data): int {
        global $DB;

        $name = trim((string)$data->name);
        $matchtype = (string)$data->matchtype;
        $pattern = trim((string)$data->pattern);
        self::validate_match_definition($matchtype, $pattern);
        if ($name === '') {
            throw new \moodle_exception('invalidparameter');
        }
        $now = time();
        if (!empty($data->id)) {
            $record = $DB->get_record('local_cp_discovery_container', ['id' => (int)$data->id], '*', MUST_EXIST);
            $record->name = $name;
            $record->matchtype = $matchtype;
            $record->pattern = $pattern;
            $record->enabled = empty($data->enabled) ? 0 : 1;
            $record->sortorder = (int)($data->sortorder ?? 0);
            $record->timemodified = $now;
            $DB->update_record('local_cp_discovery_container', $record);
            self::audit('container_rule_update', 'discovery_container', (int)$record->id);
            return (int)$record->id;
        }
        $record = (object)[
            'name' => $name,
            'matchtype' => $matchtype,
            'pattern' => $pattern,
            'enabled' => empty($data->enabled) ? 0 : 1,
            'sortorder' => (int)($data->sortorder ?? 0),
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = (int)$DB->insert_record('local_cp_discovery_container', $record);
        self::audit('container_rule_create', 'discovery_container', $id);
        return $id;
    }

    public static function toggle(string $table, int $id, string $entitytype): void {
        global $DB;

        $allowed = ['local_cp_program', 'local_cp_master', 'local_cp_region', 'local_cp_school', 'local_cp_target', 'local_cp_target_group', 'local_cp_discovery_rule', 'local_cp_discovery_container'];
        if (!in_array($table, $allowed, true)) {
            throw new \moodle_exception('invalidparameter');
        }
        $record = $DB->get_record($table, ['id' => $id], '*', MUST_EXIST);
        $newenabled = empty($record->enabled) ? 1 : 0;

        if ($newenabled) {
            if ($table === 'local_cp_discovery_rule') {
                self::validate_match_definition((string)$record->matchtype, (string)$record->pattern);
            } else if ($table === 'local_cp_discovery_container') {
                self::validate_match_definition((string)$record->matchtype, (string)$record->pattern);
            } else if ($table === 'local_cp_master') {
                self::validate_master_candidate((int)$record->programid, (string)$record->gradekey, (int)$record->courseid, (int)$record->id);
            } else if ($table === 'local_cp_school') {
                $region = $DB->get_record('local_cp_region', ['id' => $record->regionid], '*', MUST_EXIST);
                self::validate_school_category($record, $region);
            } else if ($table === 'local_cp_target') {
                self::validate_target_candidate($record, (int)$record->id);
            } else if ($table === 'local_cp_region') {
                if (empty($record->categoryid) || !$DB->record_exists('course_categories', ['id' => $record->categoryid])) {
                    throw new \moodle_exception('regioncategoryrequired', 'local_coursepublisher');
                }
            }
        }

        $record->enabled = $newenabled;
        if (property_exists($record, 'timemodified')) {
            $record->timemodified = time();
        }
        $DB->update_record($table, $record);
        self::audit('toggle', $entitytype, $id, ['enabled' => $record->enabled]);
    }

    /**
     * Evaluate one Program + School + Grade route for the health matrix.
     */
    public static function evaluate_route(\stdClass $program, \stdClass $school, string $gradekey): \stdClass {
        global $DB;

        try {
            self::resolve_target_group((int)$program->id, $gradekey, true);
        } catch (\Throwable $e) {
            return (object)['status' => 'error', 'reason' => $e->getMessage(), 'master' => null, 'target' => null, 'mastercourse' => null, 'targetcourse' => null];
        }
        $result = (object)[
            'status' => 'warning',
            'reason' => '',
            'master' => null,
            'target' => null,
            'mastercourse' => null,
            'targetcourse' => null,
        ];

        $region = $DB->get_record('local_cp_region', ['id' => $school->regionid, 'enabled' => 1]);
        if (!$region) {
            $result->status = 'error';
            $result->reason = get_string('healthregionmissing', 'local_coursepublisher');
            return $result;
        }
        try {
            self::validate_school_category($school, $region);
        } catch (\Throwable $e) {
            $result->status = 'error';
            $result->reason = $e->getMessage();
            return $result;
        }

        $master = $DB->get_record('local_cp_master', [
            'programid' => $program->id,
            'gradekey' => $gradekey,
            'enabled' => 1,
        ]);
        if (!$master) {
            $result->reason = get_string('healthmastermissing', 'local_coursepublisher');
            return $result;
        }
        $result->master = $master;
        $mastercourse = $DB->get_record('course', ['id' => $master->courseid], 'id,fullname,shortname,category');
        if (!$mastercourse) {
            $result->status = 'error';
            $result->reason = get_string('healthmasterbroken', 'local_coursepublisher');
            return $result;
        }
        $result->mastercourse = $mastercourse;

        $target = $DB->get_record('local_cp_target', [
            'programid' => $program->id,
            'schoolid' => $school->id,
            'gradekey' => $gradekey,
            'enabled' => 1,
        ]);
        if (!$target) {
            $result->reason = get_string('healthtargetmissing', 'local_coursepublisher');
            return $result;
        }
        $result->target = $target;
        $targetcourse = $DB->get_record('course', ['id' => $target->courseid], 'id,fullname,shortname,category');
        if (!$targetcourse) {
            $result->status = 'error';
            $result->reason = get_string('healthtargetbroken', 'local_coursepublisher');
            return $result;
        }
        $result->targetcourse = $targetcourse;

        if (!self::course_is_inside_school((int)$target->courseid, $school)) {
            $result->status = 'error';
            $result->reason = get_string('targetwrongcategory', 'local_coursepublisher');
            return $result;
        }
        if ($DB->record_exists('local_cp_master', ['courseid' => $target->courseid, 'enabled' => 1])) {
            $result->status = 'error';
            $result->reason = get_string('targetismaster', 'local_coursepublisher');
            return $result;
        }
        if ($DB->record_exists_select(
            'local_cp_target',
            'courseid = ? AND enabled = 1 AND id <> ?',
            [(int)$target->courseid, (int)$target->id]
        )) {
            $result->status = 'error';
            $result->reason = get_string('targetreused', 'local_coursepublisher');
            return $result;
        }

        $result->status = 'ok';
        $result->reason = get_string('healthrouteok', 'local_coursepublisher');
        return $result;
    }

    /**
     * Build the full enabled health matrix with batched lookups.
     *
     * This avoids per-route N+1 queries when the site grows to many schools.
     */
    public static function health_matrix(): array {
        global $DB;

        $programs = $DB->get_records('local_cp_program', ['enabled' => 1], 'name ASC');
        $regions = $DB->get_records('local_cp_region', ['enabled' => 1], 'name ASC');
        $schools = $DB->get_records('local_cp_school', ['enabled' => 1], 'name ASC');
        $masters = $DB->get_records('local_cp_master', ['enabled' => 1]);
        $targets = $DB->get_records('local_cp_target', ['enabled' => 1]);
        $targetgroups = $DB->get_records('local_cp_target_group', ['enabled' => 1], 'programid ASC, sortorder ASC, name ASC');
        $groupsbyprogram = [];
        foreach ($targetgroups as $targetgroup) {
            $groupsbyprogram[(int)$targetgroup->programid][(string)$targetgroup->groupkey] = $targetgroup;
        }

        $regionmap = [];
        foreach ($regions as $region) {
            $regionmap[(int)$region->id] = $region;
        }

        $courseids = [];
        foreach ($masters as $master) {
            $courseids[(int)$master->courseid] = (int)$master->courseid;
        }
        foreach ($targets as $target) {
            $courseids[(int)$target->courseid] = (int)$target->courseid;
        }
        $courses = $courseids ? $DB->get_records_list('course', 'id', array_values($courseids), '', 'id,fullname,shortname,category') : [];

        $categoryids = [];
        foreach ($regions as $region) {
            if ($region->categoryid) {
                $categoryids[(int)$region->categoryid] = (int)$region->categoryid;
            }
        }
        foreach ($schools as $school) {
            if ($school->categoryid) {
                $categoryids[(int)$school->categoryid] = (int)$school->categoryid;
            }
        }
        foreach ($courses as $course) {
            if ($course->category) {
                $categoryids[(int)$course->category] = (int)$course->category;
            }
        }
        $categories = $categoryids ? $DB->get_records_list('course_categories', 'id', array_values($categoryids), '', 'id,path') : [];

        $masterbyroute = [];
        $mastercourseids = [];
        $masterprogramcoursecount = [];
        foreach ($masters as $master) {
            $masterbyroute[(int)$master->programid . ':' . $master->gradekey] = $master;
            $mastercourseids[(int)$master->courseid] = true;
            $mk = (int)$master->programid . ':' . (int)$master->courseid;
            $masterprogramcoursecount[$mk] = ($masterprogramcoursecount[$mk] ?? 0) + 1;
        }

        $targetbyroute = [];
        $targetcoursecount = [];
        foreach ($targets as $target) {
            $targetbyroute[(int)$target->programid . ':' . (int)$target->schoolid . ':' . $target->gradekey] = $target;
            $targetcoursecount[(int)$target->courseid] = ($targetcoursecount[(int)$target->courseid] ?? 0) + 1;
        }

        $categoryinside = static function(int $childcategoryid, int $ancestorcategoryid, bool $allowself = true) use ($categories): bool {
            if (!$childcategoryid || !$ancestorcategoryid) {
                return false;
            }
            if ($childcategoryid === $ancestorcategoryid) {
                return $allowself;
            }
            if (!isset($categories[$childcategoryid])) {
                return false;
            }
            $needle = '/' . $ancestorcategoryid . '/';
            return strpos($categories[$childcategoryid]->path . '/', $needle) !== false;
        };

        $matrix = [];
        foreach ($programs as $program) {
            $programgroups = $groupsbyprogram[(int)$program->id] ?? [];
            $group = (object)['program' => $program, 'targetgroups' => $programgroups, 'rows' => []];
            foreach ($schools as $school) {
                if (!isset($regionmap[(int)$school->regionid])) {
                    continue;
                }
                $region = $regionmap[(int)$school->regionid];
                $school->regionname = $region->name;
                $school->regioncode = $region->code;
                $row = (object)['school' => $school, 'grades' => []];

                foreach ($programgroups as $grade => $targetgroup) {
                    $health = (object)[
                        'status' => 'warning',
                        'reason' => '',
                        'master' => null,
                        'target' => null,
                        'mastercourse' => null,
                        'targetcourse' => null,
                    ];

                    if (!$region->categoryid || !isset($categories[(int)$region->categoryid])) {
                        $health->status = 'error';
                        $health->reason = get_string('regioncategorymissing', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    if (!$school->categoryid || !isset($categories[(int)$school->categoryid])) {
                        $health->status = 'error';
                        $health->reason = get_string('schoolcategoryrequired', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    if (!$categoryinside((int)$school->categoryid, (int)$region->categoryid, false)) {
                        $health->status = 'error';
                        $health->reason = get_string('schoolwrongregion', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }

                    $master = $masterbyroute[(int)$program->id . ':' . $grade] ?? null;
                    if (!$master) {
                        $health->reason = get_string('healthmastermissing', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    $health->master = $master;
                    $mastercourse = $courses[(int)$master->courseid] ?? null;
                    if (!$mastercourse) {
                        $health->status = 'error';
                        $health->reason = get_string('healthmasterbroken', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    $health->mastercourse = $mastercourse;
                    if (($masterprogramcoursecount[(int)$program->id . ':' . (int)$master->courseid] ?? 0) > 1) {
                        $health->status = 'error';
                        $health->reason = get_string('masterreusedgrade', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }

                    $target = $targetbyroute[(int)$program->id . ':' . (int)$school->id . ':' . $grade] ?? null;
                    if (!$target) {
                        $health->reason = get_string('healthtargetmissing', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    $health->target = $target;
                    $targetcourse = $courses[(int)$target->courseid] ?? null;
                    if (!$targetcourse) {
                        $health->status = 'error';
                        $health->reason = get_string('healthtargetbroken', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    $health->targetcourse = $targetcourse;
                    if (!$categoryinside((int)$targetcourse->category, (int)$school->categoryid, true)) {
                        $health->status = 'error';
                        $health->reason = get_string('targetwrongcategory', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    if (isset($mastercourseids[(int)$target->courseid])) {
                        $health->status = 'error';
                        $health->reason = get_string('targetismaster', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }
                    if (($targetcoursecount[(int)$target->courseid] ?? 0) > 1) {
                        $health->status = 'error';
                        $health->reason = get_string('targetreused', 'local_coursepublisher');
                        $row->grades[$grade] = $health;
                        continue;
                    }

                    $health->status = 'ok';
                    $health->reason = get_string('healthrouteok', 'local_coursepublisher');
                    $row->grades[$grade] = $health;
                }
                $group->rows[] = $row;
            }
            $matrix[] = $group;
        }
        return $matrix;
    }

    public static function dashboard_stats(?array $matrix = null): \stdClass {
        global $DB;

        $stats = (object)[
            'programs' => $DB->count_records('local_cp_program', ['enabled' => 1]),
            'masters' => $DB->count_records('local_cp_master', ['enabled' => 1]),
            'regions' => $DB->count_records('local_cp_region', ['enabled' => 1]),
            'schools' => $DB->count_records('local_cp_school', ['enabled' => 1]),
            'targets' => $DB->count_records('local_cp_target', ['enabled' => 1]),
            'ready' => 0,
            'warnings' => 0,
            'errors' => 0,
        ];
        $matrix = $matrix ?? self::health_matrix();
        foreach ($matrix as $group) {
            foreach ($group->rows as $row) {
                foreach ($row->grades as $health) {
                    if ($health->status === 'ok') {
                        $stats->ready++;
                    } else if ($health->status === 'error') {
                        $stats->errors++;
                    } else {
                        $stats->warnings++;
                    }
                }
            }
        }
        return $stats;
    }

    public static function recent_audits(int $limit = 10): array {
        global $DB;

        $sql = "SELECT a.*, u.firstname, u.lastname
                  FROM {local_cp_audit} a
             LEFT JOIN {user} u ON u.id = a.userid
              ORDER BY a.id DESC";
        return array_values($DB->get_records_sql($sql, [], 0, $limit));
    }

    private static function source_mismatch_exception(string $sourcetype, int $sourceid, int $actualcourseid,
            \stdClass $expectedcourse): \moodle_exception {
        global $DB;

        $actualcourse = $DB->get_record('course', ['id' => $actualcourseid], 'id,fullname,shortname');
        $a = (object)[
            'type' => get_string($sourcetype === 'section' ? 'sourcesection' : 'sourceactivity', 'local_coursepublisher'),
            'sourceid' => $sourceid,
            'actual' => $actualcourse ? format_string($actualcourse->fullname) . ' (#' . $actualcourse->id . ')' : ('#' . $actualcourseid),
            'expected' => format_string($expectedcourse->fullname) . ' (#' . $expectedcourse->id . ')',
        ];
        return new \moodle_exception('sourcewrongmaster', 'local_coursepublisher', '', $a);
    }

    /**
     * Resolve and validate a Case 1 preview. No course content is mutated here.
     */
    public static function resolve_preview(int $programid, string $gradekey, string $sourcetype, int $sourceid,
            int $regionid = 0, int $schoolid = 0): array {
        global $DB;

        $program = $DB->get_record('local_cp_program', ['id' => $programid, 'enabled' => 1], '*', MUST_EXIST);
        self::resolve_target_group($programid, $gradekey, true);
        $master = $DB->get_record('local_cp_master', [
            'programid' => $programid,
            'gradekey' => $gradekey,
            'enabled' => 1,
        ]);
        if (!$master) {
            throw new \moodle_exception('masternotfound', 'local_coursepublisher');
        }
        $mastercourse = $DB->get_record('course', ['id' => $master->courseid], 'id,fullname,shortname', MUST_EXIST);

        if ($sourcetype === 'section') {
            $source = $DB->get_record('course_sections', ['id' => $sourceid], 'id,course,section,name,visible');
            if (!$source) {
                throw new \moodle_exception('sourcenotfound', 'local_coursepublisher');
            }
            if ((int)$source->course !== (int)$master->courseid) {
                throw self::source_mismatch_exception($sourcetype, $sourceid, (int)$source->course, $mastercourse);
            }
            $source->displayname = trim((string)$source->name) !== '' ? format_string($source->name) :
                get_string('sectionnumber', 'local_coursepublisher', $source->section);
            $source->typedescription = get_string('sourcesection', 'local_coursepublisher');
        } else if ($sourcetype === 'activity') {
            $source = $DB->get_record(
                'course_modules',
                ['id' => $sourceid],
                'id,course,module,instance,visible,deletioninprogress'
            );
            if (!$source) {
                throw new \moodle_exception('sourcenotfound', 'local_coursepublisher');
            }
            if ((int)$source->course !== (int)$master->courseid) {
                throw self::source_mismatch_exception($sourcetype, $sourceid, (int)$source->course, $mastercourse);
            }
            if (!empty($source->deletioninprogress)) {
                throw new \moodle_exception('sourcependingdelete', 'local_coursepublisher');
            }
            $source->typedescription = get_string('sourceactivity', 'local_coursepublisher');
            $source->displayname = get_string('sourceactivityid', 'local_coursepublisher', $sourceid);
            try {
                $modinfo = get_fast_modinfo((int)$master->courseid);
                $cm = $modinfo->get_cm($sourceid);
                $source->displayname = format_string($cm->name) . ' (#' . $sourceid . ')';
            } catch (\Throwable $ignored) {
                // The server-side course/module validation above remains authoritative.
            }
        } else {
            throw new \moodle_exception('invalidparameter');
        }

        $params = [];
        $where = 's.enabled = 1 AND r.enabled = 1';
        if ($regionid) {
            $where .= ' AND r.id = :regionid';
            $params['regionid'] = $regionid;
        }
        if ($schoolid) {
            $where .= ' AND s.id = :schoolid';
            $params['schoolid'] = $schoolid;
        }
        $sql = "SELECT s.*, r.name AS regionname, r.code AS regioncode, r.categoryid AS regioncategoryid
                  FROM {local_cp_school} s
                  JOIN {local_cp_region} r ON r.id = s.regionid
                 WHERE {$where}
              ORDER BY r.name, s.name";
        $schools = $DB->get_records_sql($sql, $params);

        $rows = [];
        foreach ($schools as $school) {
            $health = self::evaluate_route($program, $school, $gradekey);
            $target = $health->target;
            $course = $health->targetcourse;
            $rows[] = (object)[
                'targetbindid' => $target ? (int)$target->id : 0,
                'regionname' => $school->regionname,
                'regioncode' => $school->regioncode,
                'schoolname' => $school->name,
                'schoolcode' => $school->code,
                'gradekey' => $gradekey,
                'courseid' => $target ? (int)$target->courseid : 0,
                'coursename' => $course ? format_string($course->fullname) : get_string('notconfigured', 'local_coursepublisher'),
                'shortname' => $course ? $course->shortname : '',
                'status' => $health->status === 'ok' ? 'ready' : 'blocked',
                'reason' => $health->reason,
            ];
        }

        return [
            'program' => $program,
            'master' => $master,
            'mastercourse' => $mastercourse,
            'source' => $source,
            'sourcetype' => $sourcetype,
            'targets' => $rows,
        ];
    }
}
