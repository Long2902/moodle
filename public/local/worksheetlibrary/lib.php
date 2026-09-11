<?php
defined('MOODLE_INTERNAL') || die();

function local_worksheetlibrary_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_SYSTEM || !in_array($filearea, ['content', 'nativeasset'], true)) {
        return false;
    }
    require_login();
    \local_worksheetlibrary\service\access_service::require_view();

    $versionid = (int)array_shift($args);
    $filename = array_pop($args);
    if ($versionid <= 0 || !$filename) {
        return false;
    }
    $filepath = '/' . (count($args) ? implode('/', $args) . '/' : '');
    $file = get_file_storage()->get_file(
        $context->id,
        'local_worksheetlibrary',
        $filearea,
        $versionid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }
    $options['cacheability'] = 'private';
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function local_worksheetlibrary_extend_navigation_course($navigation, $course, $context) {
    if (\local_worksheetlibrary\service\access_service::can_author()) {
        $navigation->add(
            get_string('library', 'local_worksheetlibrary'),
            new moodle_url('/local/worksheetlibrary/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'worksheetlibrary'
        );
    }
}
