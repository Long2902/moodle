<?php

namespace local_coursepublisher\local;

defined('MOODLE_INTERNAL') || die();

/** Durable source-to-target mapping service. */
final class origin_map_service {
    public static function find_current(int $sourcecourseid, string $sourcetype, int $sourceid,
            int $targetcourseid): ?\stdClass {
        global $DB;
        $map = $DB->get_record('local_cp_origin_map', [
            'sourcecourseid' => $sourcecourseid,
            'sourcetype' => $sourcetype,
            'sourceid' => $sourceid,
            'targetcourseid' => $targetcourseid,
        ]);
        if (!$map) {
            return null;
        }
        if (self::target_exists($map)) {
            return $map;
        }
        return null;
    }

    public static function assert_not_already_published(int $sourcecourseid, string $sourcetype, int $sourceid,
            int $targetcourseid): void {
        if (self::find_current($sourcecourseid, $sourcetype, $sourceid, $targetcourseid)) {
            throw new \moodle_exception('batchalreadypublished', 'local_coursepublisher');
        }
    }

    /** Persist mappings derivable from durable successful job state. */
    public static function record_job_success_from_db(int $jobid): void {
        global $DB;
        $job = $DB->get_record('local_cp_job', ['id' => $jobid], '*', MUST_EXIST);
        if ((string)$job->mode === job_service::MODE_FOUNDATION) {
            return;
        }
        $now = time();
        $mappings = [];
        if ((string)$job->sourcetype === 'activity') {
            $items = array_values($DB->get_records('local_cp_job_item', ['jobid' => $jobid], 'sortorder ASC'));
            if (count($items) === 1 && (int)$items[0]->targetcmid > 0) {
                $mappings[] = ['sourcetype' => 'activity', 'sourceid' => (int)$job->sourceid,
                    'targettype' => 'activity', 'targetid' => (int)$items[0]->targetcmid];
            }
        } else if ((string)$job->sourcetype === 'section') {
            if ((int)$job->targetsectionid > 0) {
                $mappings[] = ['sourcetype' => 'section', 'sourceid' => (int)$job->sourceid,
                    'targettype' => 'section', 'targetid' => (int)$job->targetsectionid];
            }

            // Preserved delegated subsections have one structural identity in
            // course_sections and one visible mod_subsection anchor CM. Both
            // identities are durable and must be mapped. This lets future
            // update/sync logic resolve either the delegated Section or its
            // parent-section anchor without relying on transient restore data.
            if ((string)$job->mode === job_service::MODE_SUBSECTION_TREE_PLACEMENT &&
                    (int)$job->targetsectionid > 0) {
                $sourcesection = $DB->get_record('course_sections', [
                    'id' => (int)$job->sourceid,
                    'course' => (int)$job->sourcecourseid,
                ], 'id,component,itemid', MUST_EXIST);
                $targetsection = $DB->get_record('course_sections', [
                    'id' => (int)$job->targetsectionid,
                    'course' => (int)$job->targetcourseid,
                ], 'id,component,itemid', MUST_EXIST);

                if ((string)$sourcesection->component !== 'mod_subsection' ||
                        (string)$targetsection->component !== 'mod_subsection' ||
                        (int)$sourcesection->itemid <= 0 || (int)$targetsection->itemid <= 0) {
                    throw new \moodle_exception('originmappingmissing', 'local_coursepublisher');
                }

                $sourceanchor = get_coursemodule_from_instance(
                    'subsection',
                    (int)$sourcesection->itemid,
                    (int)$job->sourcecourseid,
                    false,
                    MUST_EXIST
                );
                $targetanchor = get_coursemodule_from_instance(
                    'subsection',
                    (int)$targetsection->itemid,
                    (int)$job->targetcourseid,
                    false,
                    MUST_EXIST
                );
                if (!empty($sourceanchor->deletioninprogress) || !empty($targetanchor->deletioninprogress)) {
                    throw new \moodle_exception('originmappingmissing', 'local_coursepublisher');
                }

                $mappings[] = [
                    'sourcetype' => 'activity',
                    'sourceid' => (int)$sourceanchor->id,
                    'targettype' => 'activity',
                    'targetid' => (int)$targetanchor->id,
                ];
            }

            foreach ($DB->get_records('local_cp_job_item', ['jobid' => $jobid], 'sortorder ASC') as $item) {
                if ((int)$item->sourcecmid > 0 && (int)$item->targetcmid > 0 && (string)$item->status === job_status::ITEM_SUCCEEDED) {
                    $mappings[] = ['sourcetype' => 'activity', 'sourceid' => (int)$item->sourcecmid,
                        'targettype' => 'activity', 'targetid' => (int)$item->targetcmid];
                }
            }
        }
        if (!$mappings) {
            throw new \moodle_exception('originmappingmissing', 'local_coursepublisher');
        }
        // Mapping rows form one success postcondition. Persist all mappings
        // atomically so a late DB error cannot leave a partially-current origin
        // graph attached to a job that is sent to manual review.
        $transaction = $DB->start_delegated_transaction();
        foreach ($mappings as $mapping) {
            $conditions = [
                'sourcecourseid' => (int)$job->sourcecourseid,
                'sourcetype' => $mapping['sourcetype'],
                'sourceid' => $mapping['sourceid'],
                'targetcourseid' => (int)$job->targetcourseid,
            ];
            $record = $DB->get_record('local_cp_origin_map', $conditions) ?: (object)$conditions;
            $record->targettype = $mapping['targettype'];
            $record->targetid = $mapping['targetid'];
            $record->batchid = (int)($job->batchid ?? 0);
            $record->jobid = (int)$job->id;
            $record->sourcefingerprint = (string)$job->sourcefingerprint;
            $record->timemodified = $now;
            if (!empty($record->id)) {
                $DB->update_record('local_cp_origin_map', $record);
            } else {
                $record->timecreated = $now;
                $DB->insert_record('local_cp_origin_map', $record);
            }
        }
        $transaction->allow_commit();
    }

    private static function target_exists(\stdClass $map): bool {
        global $DB;
        if ((string)$map->targettype === 'activity') {
            $cm = $DB->get_record('course_modules', ['id' => (int)$map->targetid, 'course' => (int)$map->targetcourseid], 'id,deletioninprogress');
            return $cm && empty($cm->deletioninprogress);
        }
        if ((string)$map->targettype === 'section') {
            return (bool)$DB->record_exists('course_sections', ['id' => (int)$map->targetid, 'course' => (int)$map->targetcourseid]);
        }
        return false;
    }
}
