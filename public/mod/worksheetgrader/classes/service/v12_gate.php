<?php
namespace mod_worksheetgrader\service;
final class v12_gate {
    public static function enabled_for_course(int $courseid): bool {
        $enabled = (bool)get_config('mod_worksheetgrader', 'v12enabled');
        if (!$enabled) { return false; }
        $raw = trim((string)get_config('mod_worksheetgrader', 'v12pilotcourseids'));
        if ($raw === '') { return true; }
        $ids = array_values(array_unique(array_filter(array_map('intval', preg_split('/[\s,;]+/', $raw)))));
        return in_array($courseid, $ids, true);
    }
}
