<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Restores DIGIERA Media references attached to Moodle activities.
 *
 * Uses the portable manifest written by the backup plugin and the
 * manifest-driven remap_manifest_content() to create new references
 * without requiring source reference rows in the database.
 */
class restore_local_digieramedia_plugin extends restore_local_plugin {
    /** @var array<string, array<string, mixed>> Portable manifests keyed by source Reference UUID. */
    private array $manifests = [];

    /**
     * Restore the manifest rows saved under the module node.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure(): array {
        return [
            new restore_path_element(
                'local_digieramedia_reference_manifest',
                $this->get_pathfor('/references/reference')
            ),
        ];
    }

    /**
     * Capture one portable manifest row while module.xml is parsed.
     *
     * @param array $data
     */
    public function process_local_digieramedia_reference_manifest($data): void {
        $data = (array)$data;
        $uuid = strtolower(trim((string)($data['source_reference_uuid'] ?? '')));
        if ($uuid === '') {
            return;
        }
        $this->manifests[$uuid] = $data;
    }

    /**
     * Rewrite restored activity content after Moodle has created the new
     * module id, instance id and module context.
     *
     * Supports all module types registered in content_adapter_registry:
     * page, label, book, and any module with a standard intro field.
     */
    protected function after_restore_module(): void {
        global $DB;

        if ($this->manifests === []) {
            return;
        }

        $modname = (string)$this->task->get_modulename();
        $registry = new \local_digieramedia\backup\content_adapter_registry();
        $adapter = $registry->for_module($modname);
        if (!$adapter) {
            return;
        }

        $instanceid = (int)$this->task->get_activityid();
        $cmid = (int)$this->task->get_moduleid();
        if ($instanceid <= 0 || $cmid <= 0) {
            throw new restore_step_exception(
                'DIGIERA Media restore received incomplete module mapping for ' . $modname
            );
        }

        $context = context_module::instance($cmid);
        $userid = (int)$this->task->get_userid();

        // Determine DIGIERA clone mode from the policy scope.
        $mode = \local_digieramedia\restore\clone_policy_scope::current_mode();
        $operationid = \local_digieramedia\restore\clone_policy_scope::current_operation_id();

        $references = new \local_digieramedia\repository\reference_repository();
        $remapper = new \local_digieramedia\restore\reference_remapper(
            $references,
            new \local_digieramedia\repository\media_repository(),
            new \local_digieramedia\repository\version_repository()
        );

        // Collect all content records from the adapter for the restored instance.
        $records = $adapter->source_records($instanceid);
        $manifestarray = array_values($this->manifests);

        foreach ($records as $record) {
            $result = $remapper->remap_manifest_content(
                $record->content,
                $manifestarray,
                $context,
                $mode,
                $operationid,
                $userid
            );

            if ($result['unresolved'] !== []) {
                $reasons = array_values(array_unique(array_map(
                    static fn(array $item): string => (string)($item['reason'] ?? 'UNKNOWN'),
                    $result['unresolved']
                )));
                throw new restore_step_exception(
                    "DIGIERA Media could not resolve references in {$modname}: " . implode(', ', $reasons)
                );
            }

            // Update the content field if markers were rewritten.
            if ($result['content'] !== $record->content) {
                $this->update_content($modname, $instanceid, $record, $result['content'], $DB);
            }

            // Finalize reference metadata for each mapped reference.
            foreach ($result['mappings'] as $mapping) {
                $target = $references->get((int)$mapping['target_reference_id']);
                $target->contextid = $context->id;
                $target->courseid = $this->task->get_courseid();
                $target->cmid = $cmid;
                $target->component = 'mod_' . $modname;
                $target->entitytype = $record->adapter;
                $target->entityid = $instanceid;
                $target->fieldname = $record->fieldname;
                $target->status = \local_digieramedia\state\reference_status::ACTIVE;
                $target->timemodified = time();
                $references->update($target);
            }
        }
    }

    /**
     * Update the persisted content field for the restored module.
     *
     * @param string $modname Module name (page, label, book, etc.)
     * @param int $instanceid Activity instance ID
     * @param \local_digieramedia\backup\content_record $record Source content record
     * @param string $newcontent Rewritten content with new marker UUIDs
     * @param \moodle_database $DB Database
     */
    private function update_content(
        string $modname,
        int $instanceid,
        \local_digieramedia\backup\content_record $record,
        string $newcontent,
        \moodle_database $DB,
    ): void {
        $adapter = $record->adapter;
        $fieldname = $record->fieldname;

        switch ($adapter) {
            case 'page':
                $DB->set_field('page', $fieldname, $newcontent, ['id' => $instanceid]);
                break;
            case 'label':
                $DB->set_field('label', $fieldname, $newcontent, ['id' => $instanceid]);
                break;
            case 'book':
                // content_record->sourceentityid is the chapter id for book adapters.
                $DB->set_field('book_chapters', $fieldname, $newcontent, ['id' => $record->sourceentityid]);
                break;
            default:
                // Generic intro adapter: update intro field on the module table.
                if ($fieldname === 'intro') {
                    $DB->set_field($modname, 'intro', $newcontent, ['id' => $instanceid]);
                }
                break;
        }
    }
}
