<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Adds portable DIGIERA Media reference manifests to activity backups.
 */
class backup_local_digieramedia_plugin extends backup_local_plugin {
    /**
     * Attach DIGIERA Media references to the module node in module.xml.
     *
     * Only portable logical identities are exported. R2 bucket/object keys and
     * database-local numeric ids intentionally never enter the Moodle backup.
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
            'versionmode',
            'pinned_version_no',
        ]);

        $plugin->add_child($wrapper);
        $wrapper->add_child($references);
        $references->add_child($reference);

        $sql = "SELECT r.uuid AS source_reference_uuid,
                       m.uuid AS media_uuid,
                       r.displayprofile,
                       r.versionmode,
                       CASE WHEN r.versionmode = 'PINNED_VERSION' THEN pv.versionno ELSE NULL END AS pinned_version_no
                  FROM {local_digieramedia_reference} r
                  JOIN {local_digieramedia_media} m ON m.id = r.mediaid
             LEFT JOIN {local_digieramedia_version} pv ON pv.id = r.pinnedversionid
                 WHERE r.contextid = :contextid
                   AND r.status = 'ACTIVE'
              ORDER BY r.id";
        $reference->set_source_sql($sql, ['contextid' => backup::VAR_CONTEXTID]);

        return $plugin;
    }
}
