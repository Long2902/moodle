<?php
namespace mod_worksheetgrader\service;
final class worksheet_snapshot_service {
    public static function attach_published_version(int $sessionid, int $versionid, int $actorid): \stdClass {
        global $DB;
        if (!class_exists('\\local_worksheetlibrary\\api')) { throw new \moodle_exception('Worksheet Library is not installed'); }
        $session = $DB->get_record('wsg_session', ['id'=>$sessionid], '*', MUST_EXIST);
        if ($session->status !== 'draft' || $DB->record_exists('wsg_attempt', ['sessionid'=>$sessionid])) {
            throw new \moodle_exception('Cannot replace worksheet after attempts exist');
        }
        $version = \local_worksheetlibrary\api::published_version($versionid);
        $activity = $DB->get_record('worksheetgrader', ['id'=>$session->worksheetgraderid], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('worksheetgrader', $activity->id, $activity->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_worksheetgrader', 'officetemplate', $sessionid);
        $kind = (string)$version->item->kind;
        $contenthtml = '';
        $filename = '';
        if ($kind === 'html') {
            $contenthtml = (string)$version->contenthtml;
        } else if ($kind === 'native') {
            $nativejson = (string)($version->nativejson ?? '');
            if ($nativejson === '') {
                throw new \moodle_exception('Published Native worksheet is missing');
            }
            $document = \local_digieranative\document\validator::validate_json($nativejson);
            $contenthtml = \local_digieranative\document\renderer::render_json($nativejson, 'preview');
            $metadata = json_decode((string)($session->metadatajson ?? '{}'), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }
            $metadata['nativejson'] = $nativejson;
            $metadata['schemaversion'] = (int)$document['version'];
            $metadata['renderedhtml'] = $contenthtml;
            $session->metadatajson = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            $source = \local_worksheetlibrary\api::primary_file($versionid);
            if (!$source) { throw new \moodle_exception('Published worksheet file is missing'); }
            $filename = $source->get_filename();
            $fs->create_file_from_storedfile([
                'contextid'=>$context->id,'component'=>'mod_worksheetgrader','filearea'=>'officetemplate',
                'itemid'=>$sessionid,'filepath'=>'/','filename'=>$filename,
            ], $source);
        }
        $session->libraryitemid = (int)$version->itemid;
        $session->libraryversionid = (int)$versionid;
        $session->worksheetkind = $kind;
        $session->worksheethash = $kind === 'native' ? hash('sha256', (string)$version->nativejson) : (string)$version->contenthash;
        $session->contentmode = in_array($kind, ['html', 'native'], true) ? 'html' : 'office';
        $session->contenthtml = $contenthtml;
        $session->sourcefilename = $filename;
        $session->migrationstatus = 'not_required';
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        audit_logger::log((int)$activity->id, 'v12_worksheet_attached', ['versionid'=>$versionid,'kind'=>$kind], $sessionid,0,0,$actorid);
        return $session;
    }
}
