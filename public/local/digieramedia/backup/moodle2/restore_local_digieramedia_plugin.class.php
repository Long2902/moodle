<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Restores DIGIERA Media references attached to Moodle activities.
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
     * Rewrite restored activity content only after Moodle has created its new
     * module id, instance id and module context.
     *
     * Page is the first production adapter. Other editor-backed activity types
     * will be added through the same post-restore adapter pattern.
     */
    protected function after_restore_module(): void {
        global $DB;

        if ($this->manifests === []) {
            return;
        }
        if ($this->task->get_modulename() !== 'page') {
            return;
        }

        $pageid = (int)$this->task->get_activityid();
        $cmid = (int)$this->task->get_moduleid();
        if ($pageid <= 0 || $cmid <= 0) {
            throw new restore_step_exception('DIGIERA Media restore received incomplete Page mapping');
        }

        $page = $DB->get_record('page', ['id' => $pageid], '*', MUST_EXIST);
        $context = context_module::instance($cmid);

        $references = new \local_digieramedia\repository\reference_repository();
        $remapper = new \local_digieramedia\restore\reference_remapper(
            $references,
            new \local_digieramedia\repository\media_repository(),
            new \local_digieramedia\repository\version_repository()
        );

        $result = $remapper->remap_content(
            (string)$page->content,
            $context,
            \local_digieramedia\restore\clone_mode::SHARED_FOLLOW,
            (int)$this->task->get_userid()
        );

        if ($result['unresolved'] !== []) {
            $reasons = array_values(array_unique(array_map(
                static fn(array $item): string => (string)($item['reason'] ?? 'UNKNOWN'),
                $result['unresolved']
            )));
            throw new restore_step_exception(
                'DIGIERA Media could not resolve restored Page references: ' . implode(', ', $reasons)
            );
        }

        if ($result['content'] !== $page->content) {
            $page->content = $result['content'];
            $page->timemodified = time();
            $DB->update_record('page', $page);
        }

        foreach ($result['mappings'] as $mapping) {
            $sourceuuid = strtolower((string)$mapping['source_reference_uuid']);
            if (!isset($this->manifests[$sourceuuid])) {
                throw new restore_step_exception(
                    'DIGIERA Media restored a Reference that was not present in the backup manifest'
                );
            }

            $target = $references->get((int)$mapping['target_reference_id']);
            $target->contextid = $context->id;
            $target->courseid = $this->task->get_courseid();
            $target->cmid = $cmid;
            $target->component = 'mod_page';
            $target->entitytype = 'page';
            $target->entityid = $pageid;
            $target->fieldname = 'content';
            $target->status = \local_digieramedia\state\reference_status::ACTIVE;
            $target->timemodified = time();
            $references->update($target);
        }
    }
}
