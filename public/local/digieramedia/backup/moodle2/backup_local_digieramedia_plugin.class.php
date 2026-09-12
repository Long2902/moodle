<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Adds portable DIGIERA Media reference manifests to activity backups.
 */
class backup_local_digieramedia_plugin extends backup_local_plugin {
    /**
     * Attach marker-derived DIGIERA Media manifests to the module node.
     *
     * Persisted marker content is authoritative. Database-local numeric ids,
     * R2 bucket/object keys and credentials never enter the Moodle backup.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element(null);
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $references = new backup_nested_element('references');
        $reference = new backup_nested_element('reference', null, [
            'source_reference_uuid',
            'media_uuid',
            'displayprofile',
            'source_versionmode',
            'effective_version_no',
            'alttext',
            'caption',
            'optionsjson',
            'adapter',
            'source_entity_id',
            'fieldname',
            'occurrence',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($references);
        $references->add_child($reference);

        $registry = new \local_digieramedia\backup\content_adapter_registry();
        $adapter = $registry->for_module((string)$this->task->get_modulename());
        $rows = [];
        if ($adapter) {
            $collector = new \local_digieramedia\backup\reference_collector(
                new \local_digieramedia\repository\reference_repository(),
                new \local_digieramedia\repository\media_repository(),
                new \local_digieramedia\repository\version_repository()
            );
            foreach ($adapter->source_records((int)$this->task->get_activityid()) as $record) {
                foreach ($collector->collect_content($record) as $manifest) {
                    $rows[] = $manifest;
                }
            }
        }
        $reference->set_source_array($rows);

        return $plugin;
    }
}
