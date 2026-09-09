<?php

namespace local_digieramedia\service;

use context;
use local_digieramedia\repository\reference_repository;

/**
 * Read-only view of where one logical DIGIERA Media item is referenced.
 */
final class usage_service {
    private reference_repository $references;

    public function __construct(?reference_repository $references = null) {
        $this->references = $references ?? new reference_repository();
    }

    public function get(int $userid, context $context, string $mediauuid): array {
        global $DB;

        require_capability('local/digieramedia:viewusage', $context, $userid);
        $media = $DB->get_record('local_digieramedia_media', ['uuid' => $mediauuid], '*', MUST_EXIST);
        $broad = has_capability('local/digieramedia:viewall', $context, $userid)
            || has_capability('local/digieramedia:manage', $context, $userid);
        $records = $DB->get_records(
            'local_digieramedia_reference',
            ['mediaid' => (int)$media->id],
            'timemodified DESC, id DESC'
        );

        $usages = [];
        foreach ($records as $reference) {
            if (!$this->can_view_reference($userid, $reference, $broad)) {
                continue;
            }
            $usages[] = $this->usage_row($userid, $reference, $broad);
        }

        $trash = $DB->get_record('local_digieramedia_trash', ['mediaid' => (int)$media->id]);
        $deletedbyname = '';
        if ($trash && (int)$trash->deletedby > 0) {
            $user = $DB->get_record('user', ['id' => (int)$trash->deletedby], 'id,firstname,lastname');
            if ($user) {
                $deletedbyname = fullname($user);
            }
        }

        $cantrash = (string)$media->status === 'ACTIVE'
            && (((int)$media->owneruserid === $userid
                    && has_capability('local/digieramedia:trashown', $context, $userid))
                || has_capability('local/digieramedia:trash', $context, $userid));
        $canrestore = (string)$media->status === 'TRASHED'
            && has_capability('local/digieramedia:restore', $context, $userid);
        $canpurge = in_array((string)$media->status, ['TRASHED', 'PURGING'], true)
            && has_capability('local/digieramedia:purge', $context, $userid);

        return [
            'mediauuid' => (string)$media->uuid,
            'mediastatus' => (string)$media->status,
            'livecount' => $this->references->count_live((int)$media->id),
            'visiblecount' => count($usages),
            'canviewusage' => true,
            'cantrash' => $cantrash,
            'canrestore' => $canrestore,
            'canpurge' => $canpurge,
            'canforcepurge' => $canpurge,
            'deletedat' => $trash ? (int)$trash->deletedat : 0,
            'deletedby' => $trash ? (int)$trash->deletedby : 0,
            'deletedbyname' => $deletedbyname,
            'reason' => $trash ? (string)($trash->reason ?? '') : '',
            'usages' => $usages,
        ];
    }

    private function can_view_reference(int $userid, \stdClass $reference, bool $broad): bool {
        if ($broad) {
            return true;
        }
        if ((int)$reference->courseid > 0) {
            $coursecontext = \context_course::instance((int)$reference->courseid, IGNORE_MISSING);
            if (!$coursecontext) {
                return false;
            }
            return has_capability('local/digieramedia:view', $coursecontext, $userid);
        }
        $referencecontext = context::instance_by_id((int)$reference->contextid, IGNORE_MISSING);
        return $referencecontext
            ? has_capability('local/digieramedia:view', $referencecontext, $userid)
            : false;
    }

    private function usage_row(int $userid, \stdClass $reference, bool $broad): array {
        global $DB;

        $courseid = (int)$reference->courseid;
        $cmid = (int)$reference->cmid;
        $coursename = '';
        $activityname = '';
        $url = '';

        if ($courseid > 0) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id,fullname');
            if ($course) {
                $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
                $coursename = $coursecontext
                    ? format_string($course->fullname, true, ['context' => $coursecontext])
                    : format_string($course->fullname);
                $url = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            }
        }

        if ($courseid > 0 && $cmid > 0) {
            try {
                $modinfo = get_fast_modinfo($courseid, $userid);
                $cm = $modinfo->get_cm($cmid);
                if ($broad || $cm->uservisible) {
                    $activityname = $cm->get_formatted_name();
                    if ($cm->url) {
                        $url = $cm->url->out(false);
                    }
                }
            } catch (\Throwable $e) {
                $activityname = '';
            }
        }

        $pinnedversionno = 0;
        if ((string)$reference->versionmode === 'PINNED_VERSION' && (int)$reference->pinnedversionid > 0) {
            $pinnedversionno = (int)$DB->get_field(
                'local_digieramedia_version',
                'versionno',
                ['id' => (int)$reference->pinnedversionid]
            );
        }
        $fallback = trim((string)$reference->component) . ' / '
            . trim((string)$reference->entitytype) . ':' . (int)$reference->entityid
            . ' / ' . trim((string)$reference->fieldname);

        return [
            'referenceuuid' => (string)$reference->uuid,
            'status' => (string)$reference->status,
            'courseid' => $courseid,
            'coursename' => $coursename,
            'cmid' => $cmid,
            'activityname' => $activityname,
            'component' => (string)$reference->component,
            'entitytype' => (string)$reference->entitytype,
            'entityid' => (int)$reference->entityid,
            'fieldname' => (string)$reference->fieldname,
            'versionmode' => (string)$reference->versionmode,
            'pinnedversionno' => $pinnedversionno,
            'url' => $url,
            'fallback' => $fallback,
            'timecreated' => (int)$reference->timecreated,
            'timemodified' => (int)$reference->timemodified,
        ];
    }
}
