<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure, deterministic logical-placement normalization and candidate matching.
 *
 * No Moodle IDs are assumed to be portable between courses. This helper is
 * deliberately exact/fail-closed: it never uses fuzzy matching.
 */
final class logical_placement {
    public const RESOLVER_VERSION = 1;

    public static function normalise_text(string $value): string {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if (class_exists('core_text')) {
            return \core_text::strtolower($value);
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }
        // CLI/unit fallback for environments without mbstring. Moodle itself
        // provides core_text; this map keeps Vietnamese test fixtures stable.
        $value = strtr($value, [
            'À'=>'à','Á'=>'á','Ạ'=>'ạ','Ả'=>'ả','Ã'=>'ã','Â'=>'â','Ầ'=>'ầ','Ấ'=>'ấ','Ậ'=>'ậ','Ẩ'=>'ẩ','Ẫ'=>'ẫ','Ă'=>'ă','Ằ'=>'ằ','Ắ'=>'ắ','Ặ'=>'ặ','Ẳ'=>'ẳ','Ẵ'=>'ẵ',
            'È'=>'è','É'=>'é','Ẹ'=>'ẹ','Ẻ'=>'ẻ','Ẽ'=>'ẽ','Ê'=>'ê','Ề'=>'ề','Ế'=>'ế','Ệ'=>'ệ','Ể'=>'ể','Ễ'=>'ễ',
            'Ì'=>'ì','Í'=>'í','Ị'=>'ị','Ỉ'=>'ỉ','Ĩ'=>'ĩ','Ò'=>'ò','Ó'=>'ó','Ọ'=>'ọ','Ỏ'=>'ỏ','Õ'=>'õ','Ô'=>'ô','Ồ'=>'ồ','Ố'=>'ố','Ộ'=>'ộ','Ổ'=>'ổ','Ỗ'=>'ỗ','Ơ'=>'ơ','Ờ'=>'ờ','Ớ'=>'ớ','Ợ'=>'ợ','Ở'=>'ở','Ỡ'=>'ỡ',
            'Ù'=>'ù','Ú'=>'ú','Ụ'=>'ụ','Ủ'=>'ủ','Ũ'=>'ũ','Ư'=>'ư','Ừ'=>'ừ','Ứ'=>'ứ','Ự'=>'ự','Ử'=>'ử','Ữ'=>'ữ','Ỳ'=>'ỳ','Ý'=>'ý','Ỵ'=>'ỵ','Ỷ'=>'ỷ','Ỹ'=>'ỹ','Đ'=>'đ',
        ]);
        return strtolower($value);
    }

    /**
     * Pick exactly one deterministic candidate or fail closed.
     *
     * @return array{status:string,candidate:?array,evidence:array,candidates:int}
     */
    public static function choose_unique_candidate(array $signature, array $candidates): array {
        $scored = [];
        foreach ($candidates as $candidate) {
            $evaluation = self::evaluate_candidate($signature, $candidate);
            if (!$evaluation['eligible']) {
                continue;
            }
            $scored[] = [
                'candidate' => $candidate,
                'score' => $evaluation['score'],
                'evidence' => $evaluation['evidence'],
            ];
        }

        if (!$scored) {
            return ['status' => 'not_found', 'candidate' => null, 'evidence' => [], 'candidates' => 0];
        }

        usort($scored, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        $bestscore = $scored[0]['score'];
        $best = array_values(array_filter($scored, static fn(array $row): bool => $row['score'] === $bestscore));
        if (count($best) !== 1) {
            return [
                'status' => 'ambiguous',
                'candidate' => null,
                'evidence' => $best[0]['evidence'] ?? [],
                'candidates' => count($best),
            ];
        }

        return [
            'status' => 'resolved',
            'candidate' => $best[0]['candidate'],
            'evidence' => $best[0]['evidence'],
            'candidates' => count($scored),
        ];
    }

    /** @return array{eligible:bool,score:int,evidence:array} */
    private static function evaluate_candidate(array $signature, array $candidate): array {
        $score = 0;
        $evidence = [];

        foreach (['kind', 'sectiontype', 'modname'] as $key) {
            if (!array_key_exists($key, $signature) || $signature[$key] === '' || $signature[$key] === null) {
                continue;
            }
            if (!array_key_exists($key, $candidate) || (string)$candidate[$key] !== (string)$signature[$key]) {
                return ['eligible' => false, 'score' => 0, 'evidence' => []];
            }
            $score += $key === 'kind' ? 5 : 20;
            $evidence[] = $key;
        }

        if (!empty($signature['name'])) {
            if (empty($candidate['name']) || self::normalise_text((string)$candidate['name']) !== self::normalise_text((string)$signature['name'])) {
                return ['eligible' => false, 'score' => 0, 'evidence' => []];
            }
            $score += 50;
            $evidence[] = 'name';
        }

        if (!empty($signature['parentpath'])) {
            if (empty($candidate['parentpath']) || !self::path_equal((array)$signature['parentpath'], (array)$candidate['parentpath'])) {
                return ['eligible' => false, 'score' => 0, 'evidence' => []];
            }
            $score += 30;
            $evidence[] = 'parentpath';
        }

        if (!empty($signature['section']) && is_array($signature['section'])) {
            if (empty($candidate['section']) || !is_array($candidate['section']) || !self::scope_signature_equal($signature['section'], $candidate['section'])) {
                return ['eligible' => false, 'score' => 0, 'evidence' => []];
            }
            $score += 30;
            $evidence[] = 'section';
        }

        if (isset($signature['ordinal']) && isset($candidate['ordinal']) && (int)$signature['ordinal'] === (int)$candidate['ordinal']) {
            $score += 15;
            $evidence[] = 'ordinal';
        }

        foreach (['previous', 'next'] as $key) {
            if (empty($signature[$key]) || !is_array($signature[$key]) || empty($candidate[$key]) || !is_array($candidate[$key])) {
                continue;
            }
            if (self::scope_signature_equal($signature[$key], $candidate[$key])) {
                $score += 20;
                $evidence[] = $key;
            }
        }

        return ['eligible' => true, 'score' => $score, 'evidence' => $evidence];
    }

    private static function scope_signature_equal(array $expected, array $actual): bool {
        foreach (['kind', 'sectiontype', 'modname'] as $key) {
            if (isset($expected[$key]) && $expected[$key] !== '' && (!isset($actual[$key]) || (string)$expected[$key] !== (string)$actual[$key])) {
                return false;
            }
        }
        if (!empty($expected['name'])) {
            if (empty($actual['name']) || self::normalise_text((string)$expected['name']) !== self::normalise_text((string)$actual['name'])) {
                return false;
            }
        }
        return true;
    }

    private static function path_equal(array $expected, array $actual): bool {
        if (count($expected) !== count($actual)) {
            return false;
        }
        foreach (array_values($expected) as $index => $part) {
            $actualpart = array_values($actual)[$index] ?? null;
            $partname = is_array($part) ? (string)($part['name'] ?? '') : (string)$part;
            $actualname = is_array($actualpart) ? (string)($actualpart['name'] ?? '') : (string)$actualpart;
            if (self::normalise_text($partname) !== self::normalise_text($actualname)) {
                return false;
            }
        }
        return true;
    }
}
