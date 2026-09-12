<?php

namespace tiny_digieramedia;

use context;
use editor_tiny\plugin;
use editor_tiny\plugin_with_buttons;
use editor_tiny\plugin_with_menuitems;
use editor_tiny\plugin_with_configuration;

final class plugininfo extends plugin implements plugin_with_buttons, plugin_with_menuitems, plugin_with_configuration {
    public static function get_available_buttons(): array {
        return ['tiny_digieramedia/digieramedia'];
    }

    public static function get_available_menuitems(): array {
        return ['tiny_digieramedia/digieramedia'];
    }

    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): array {
        $enabled = has_capability('local/digieramedia:view', $context)
            && has_capability('local/digieramedia:insert', $context);
        $courseid = 0;
        try {
            $coursecontext = $context->get_course_context(false);
            if ($coursecontext) {
                $courseid = (int)$coursecontext->instanceid;
            }
        } catch (\Throwable $e) {
            $courseid = 0;
        }

        return [
            'enabled' => $enabled,
            'contextid' => (int)$context->id,
            'courseid' => $courseid,
            'canupload' => has_capability('local/digieramedia:upload', $context),
            'canmanagevisibility' => has_capability('local/digieramedia:managevisibility', $context),
            'canmanageversions' => has_capability('local/digieramedia:manageversions', $context),
            'canreplace' => has_capability('local/digieramedia:replace', $context),
            'canviewusage' => has_capability('local/digieramedia:viewusage', $context),
            'cantrash' => has_capability('local/digieramedia:trashown', $context)
                || has_capability('local/digieramedia:trash', $context),
            'canrestore' => has_capability('local/digieramedia:restore', $context),
            'canpurge' => has_capability('local/digieramedia:purge', $context),
            'canedit' => has_capability('local/digieramedia:editown', $context)
                || has_capability('local/digieramedia:editall', $context)
                || has_capability('local/digieramedia:manage', $context),
            'canoverridepath' => has_capability('local/digieramedia:overridepath', $context)
                || has_capability('local/digieramedia:manage', $context),
        ];
    }
}
