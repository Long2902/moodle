<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure matching primitives used by topology auto-discovery.
 */
final class discovery_matcher {
    public const MATCH_TYPES = ['contains', 'starts_with', 'regex'];
    public const COURSE_FIELDS = ['fullname', 'shortname'];

    public static function normalise_text(string $value): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if (class_exists('\\core_text')) {
            return \core_text::strtolower($value);
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        // Standalone-test fallback covering Vietnamese deployment names when mbstring is unavailable.
        $value = strtr($value, [
            'À'=>'à','Á'=>'á','Ạ'=>'ạ','Ả'=>'ả','Ã'=>'ã','Â'=>'â','Ầ'=>'ầ','Ấ'=>'ấ','Ậ'=>'ậ','Ẩ'=>'ẩ','Ẫ'=>'ẫ','Ă'=>'ă','Ằ'=>'ằ','Ắ'=>'ắ','Ặ'=>'ặ','Ẳ'=>'ẳ','Ẵ'=>'ẵ',
            'È'=>'è','É'=>'é','Ẹ'=>'ẹ','Ẻ'=>'ẻ','Ẽ'=>'ẽ','Ê'=>'ê','Ề'=>'ề','Ế'=>'ế','Ệ'=>'ệ','Ể'=>'ể','Ễ'=>'ễ',
            'Ì'=>'ì','Í'=>'í','Ị'=>'ị','Ỉ'=>'ỉ','Ĩ'=>'ĩ',
            'Ò'=>'ò','Ó'=>'ó','Ọ'=>'ọ','Ỏ'=>'ỏ','Õ'=>'õ','Ô'=>'ô','Ồ'=>'ồ','Ố'=>'ố','Ộ'=>'ộ','Ổ'=>'ổ','Ỗ'=>'ỗ','Ơ'=>'ơ','Ờ'=>'ờ','Ớ'=>'ớ','Ợ'=>'ợ','Ở'=>'ở','Ỡ'=>'ỡ',
            'Ù'=>'ù','Ú'=>'ú','Ụ'=>'ụ','Ủ'=>'ủ','Ũ'=>'ũ','Ư'=>'ư','Ừ'=>'ừ','Ứ'=>'ứ','Ự'=>'ự','Ử'=>'ử','Ữ'=>'ữ',
            'Ỳ'=>'ỳ','Ý'=>'ý','Ỵ'=>'ỵ','Ỷ'=>'ỷ','Ỹ'=>'ỹ','Đ'=>'đ',
        ]);
        return strtolower($value);
    }

    public static function validate_regex(string $pattern): bool {
        if ($pattern === '') {
            return false;
        }
        set_error_handler(static function(): bool { return true; });
        try {
            $result = preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }
        return $result !== false;
    }

    public static function match_value(string $value, string $matchtype, string $pattern): bool {
        if (!in_array($matchtype, self::MATCH_TYPES, true) || $pattern === '') {
            return false;
        }
        if ($matchtype === 'regex') {
            if (!self::validate_regex($pattern)) {
                return false;
            }
            return preg_match($pattern, $value) === 1;
        }
        $value = self::normalise_text($value);
        $pattern = self::normalise_text($pattern);
        if ($matchtype === 'starts_with') {
            return strncmp($value, $pattern, strlen($pattern)) === 0;
        }
        return strpos($value, $pattern) !== false;
    }

    public static function rule_matches(\stdClass $course, \stdClass $rule): bool {
        $fieldname = (string)($rule->fieldname ?? '');
        if (!in_array($fieldname, self::COURSE_FIELDS, true)) {
            return false;
        }
        $value = (string)($course->{$fieldname} ?? '');
        return self::match_value($value, (string)$rule->matchtype, (string)$rule->pattern);
    }

    /**
     * Classify one Moodle course against enabled rules.
     *
     * @return array{status:string,targetgroupid?:int,groupkey?:string,ruleids:array,targetgroupids?:array}
     */
    public static function classify_course(\stdClass $course, array $rules): array {
        $groups = [];
        foreach ($rules as $rule) {
            if (isset($rule->enabled) && empty($rule->enabled)) {
                continue;
            }
            if (!self::rule_matches($course, $rule)) {
                continue;
            }
            $groupid = (int)$rule->targetgroupid;
            if (!isset($groups[$groupid])) {
                $groups[$groupid] = [
                    'groupkey' => (string)($rule->groupkey ?? ''),
                    'ruleids' => [],
                ];
            }
            $groups[$groupid]['ruleids'][] = (int)($rule->id ?? 0);
        }
        if (!$groups) {
            return ['status' => 'unmatched', 'ruleids' => []];
        }
        if (count($groups) > 1) {
            return [
                'status' => 'conflict',
                'ruleids' => array_values(array_merge(...array_column($groups, 'ruleids'))),
                'targetgroupids' => array_map('intval', array_keys($groups)),
            ];
        }
        $groupid = (int)array_key_first($groups);
        return [
            'status' => 'matched',
            'targetgroupid' => $groupid,
            'groupkey' => $groups[$groupid]['groupkey'],
            'ruleids' => $groups[$groupid]['ruleids'],
        ];
    }
}
