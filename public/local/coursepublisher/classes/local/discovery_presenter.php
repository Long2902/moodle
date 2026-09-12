<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Presentation-only helpers for the discovery preview.
 */
final class discovery_presenter {
    /**
     * Hide unmatched course rows by default while preserving operational rows.
     */
    public static function visible_rows(array $rows, bool $showunmatched = false): array {
        if ($showunmatched) {
            return array_values($rows);
        }

        return array_values(array_filter($rows, static function(array $row): bool {
            return (string)($row['status'] ?? '') !== discovery_service::STATUS_UNMATCHED;
        }));
    }

    /**
     * Count unique scanned courses that matched at least one enabled recognition rule.
     */
    public static function matched_course_count(array $rows): int {
        $courseids = [];
        foreach ($rows as $row) {
            $courseid = (int)($row['courseid'] ?? 0);
            $status = (string)($row['status'] ?? '');
            if (!$courseid) {
                continue;
            }
            if ($status === discovery_service::STATUS_UNMATCHED || $status === discovery_service::STATUS_EXCLUDED_MASTER) {
                continue;
            }
            $courseids[$courseid] = true;
        }
        return count($courseids);
    }
}
