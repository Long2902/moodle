<?php
// This file is part of Moodle - http://moodle.org/.

namespace mod_worksheetgrader\service;

defined('MOODLE_INTERNAL') || die();

use mod_worksheetgrader\docspace\client;

/**
 * Coordinates ONLYOFFICE DocSpace workspaces.
 *
 * Supports two DocSpace layouts:
 * - Shared Public Room folder: reuse one configured Folder ID + room requestToken
 *   and open each file directly with initEditor(). This avoids consuming many rooms.
 * - Isolated rooms: keep the V11.5.3 room-per-workspace strategy for stricter isolation.
 */
final class docspace_manager {
    public const SCOPE_ACTIVITY = 'activity'; // Legacy V11.5 shared-room mapping.
    public const SCOPE_TEMPLATE = 'template';
    public const SCOPE_ATTEMPT = 'attempt';
    public const ACCESS_READ = 'read';
    public const ACCESS_EDIT = 'edit';
    public const MODE_SHARED = 'sharedfolder';
    public const MODE_ISOLATED = 'isolatedrooms';

    public static function workspace_mode(): string {
        $mode = trim((string)get_config('mod_worksheetgrader', 'docspaceworkspacemode'));
        return $mode === self::MODE_ISOLATED ? self::MODE_ISOLATED : self::MODE_SHARED;
    }

    public static function shared_mode(): bool {
        return self::workspace_mode() === self::MODE_SHARED &&
            trim((string)get_config('mod_worksheetgrader', 'docspacefolderid')) !== '' &&
            trim((string)get_config('mod_worksheetgrader', 'docspacerequesttoken')) !== '';
    }

    private static function shared_folder_id(): string {
        return trim((string)get_config('mod_worksheetgrader', 'docspacefolderid'));
    }

    private static function shared_request_token(): string {
        return trim((string)get_config('mod_worksheetgrader', 'docspacerequesttoken'));
    }

    public static function configured(): bool {
        return !empty(get_config('mod_worksheetgrader', 'docspaceenabled')) &&
            trim((string)get_config('mod_worksheetgrader', 'docspaceurl')) !== '' &&
            trim((string)get_config('mod_worksheetgrader', 'docspaceapikey')) !== '';
    }

    public static function get_activity_room(int $activityid): ?\stdClass {
        global $DB;
        return $DB->get_record('wsg_docspace', [
            'worksheetgraderid' => $activityid,
            'sessionid' => 0,
            'teamid' => 0,
            'scope' => self::SCOPE_ACTIVITY,
        ]) ?: null;
    }

    public static function get_template(int $sessionid): ?\stdClass {
        global $DB;
        return $DB->get_record('wsg_docspace', [
            'sessionid' => $sessionid,
            'teamid' => 0,
            'scope' => self::SCOPE_TEMPLATE,
        ]) ?: null;
    }

    public static function get_attempt_workspace(int $attemptid): ?\stdClass {
        global $DB;
        return $DB->get_record('wsg_docspace', [
            'attemptid' => $attemptid,
            'scope' => self::SCOPE_ATTEMPT,
        ]) ?: null;
    }

    /**
     * Retain the V11.5 activity-room method for compatibility with old records.
     * New template and attempt workspaces do not use this room.
     */
    public static function ensure_activity_room(\stdClass $activity, int $actorid = 0): \stdClass {
        global $DB;
        if (!self::configured()) {
            throw new \moodle_exception('docspacenotconfigured', 'mod_worksheetgrader');
        }
        $existing = self::get_activity_room((int)$activity->id);
        if ($existing) {
            return $existing;
        }
        $api = new client();
        $title = self::activity_room_title($activity) . ' — legacy container';
        $room = $api->create_public_room($title);
        $now = time();
        $record = (object)[
            'worksheetgraderid' => (int)$activity->id,
            'sessionid' => 0,
            'teamid' => 0,
            'attemptid' => 0,
            'scope' => self::SCOPE_ACTIVITY,
            'roomid' => (string)$room['id'],
            'folderid' => '',
            'fileid' => '',
            'roomtitle' => $title,
            'filename' => '',
            'publiclink' => null,
            'requesttoken' => null,
            'access' => self::ACCESS_EDIT,
            'status' => 'legacy-container',
            'lastsync' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        try {
            $record->id = $DB->insert_record('wsg_docspace', $record);
        } catch (\dml_write_exception $exception) {
            $existing = self::get_activity_room((int)$activity->id);
            if ($existing) {
                return $existing;
            }
            throw $exception;
        }
        audit_logger::log((int)$activity->id, 'docspace_activity_room_created', [
            'roomid' => $record->roomid,
            'roomtitle' => $record->roomtitle,
        ], 0, 0, 0, $actorid);
        return $record;
    }

    /** Create or replace the teacher's template file from a Moodle stored file. */
    public static function create_template_from_stored_file(
        \stdClass $activity,
        \stdClass $session,
        \stored_file $file,
        int $actorid
    ): \stdClass {
        $tmp = $file->copy_content_to_temp();
        try {
            return self::create_template(
                $activity,
                $session,
                $file->get_filename(),
                $actorid,
                $tmp,
                $file->get_mimetype()
            );
        } finally {
            @unlink($tmp);
        }
    }

    /** Create or replace the teacher's template with a blank office file. */
    public static function create_blank_template(
        \stdClass $activity,
        \stdClass $session,
        string $filename,
        int $actorid
    ): \stdClass {
        return self::create_template($activity, $session, $filename, $actorid);
    }

    private static function create_template(
        \stdClass $activity,
        \stdClass $session,
        string $filename,
        int $actorid,
        ?string $localpath = null,
        string $mimetype = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ): \stdClass {
        global $DB;
        if (!self::configured()) {
            throw new \moodle_exception('docspacenotconfigured', 'mod_worksheetgrader');
        }
        if ($DB->record_exists('wsg_attempt', ['sessionid' => $session->id])) {
            throw new \moodle_exception('docspacetemplatehasattempts', 'mod_worksheetgrader');
        }

        $api = new client();
        $roomtitle = self::template_room_title($activity, $session);
        $shared = self::shared_mode();
        $workspace = $shared ? [
            'roomid' => '',
            'folderid' => self::shared_folder_id(),
            'link' => ['url' => '', 'requesttoken' => self::shared_request_token()],
        ] : self::create_editable_room($api, $roomtitle);
        $destination = $shared ? $workspace['folderid'] : $workspace['roomid'];
        $safe = self::template_filename($session, $filename ?: 'Phieu-hoc-tap.docx');
        try {
            $createdfile = $localpath
                ? $api->upload_file($destination, $localpath, $safe, $mimetype)
                : $api->create_file($destination, $safe);
        } catch (\Throwable $exception) {
            if (!$shared) {
                self::delete_room_quietly($api, $workspace['roomid']);
            }
            throw $exception;
        }

        $oldrecord = self::get_template((int)$session->id);
        $record = $oldrecord ?: (object)[
            'worksheetgraderid' => (int)$activity->id,
            'sessionid' => (int)$session->id,
            'teamid' => 0,
            'attemptid' => 0,
            'scope' => self::SCOPE_TEMPLATE,
            'timecreated' => time(),
        ];
        $oldroomid = $oldrecord && self::is_dedicated_room_mapping($oldrecord) ? (string)$oldrecord->roomid : '';
        $record->roomid = $shared ? '' : $workspace['roomid'];
        $record->folderid = $shared ? $workspace['folderid'] : '';
        $record->fileid = (string)$createdfile['id'];
        $record->roomtitle = $shared ? 'Configured shared Public Room' : $roomtitle;
        $record->filename = $createdfile['title'] ?: $safe;
        $record->publiclink = $shared ? '' : $workspace['link']['url'];
        $record->requesttoken = $workspace['link']['requesttoken'];
        $record->access = self::ACCESS_EDIT;
        $record->status = $shared ? 'shared-ready' : 'room-ready';
        $record->lastsync = 0;
        $record->timemodified = time();
        if (!empty($record->id)) {
            $DB->update_record('wsg_docspace', $record);
        } else {
            $record->id = $DB->insert_record('wsg_docspace', $record);
        }
        if ($oldroomid !== '' && $oldroomid !== $record->roomid) {
            self::delete_room_quietly($api, $oldroomid);
        }

        $session->contentmode = 'docspace';
        $session->sourcefilename = $record->filename;
        $session->metadatajson = json_encode([
            'docspace' => [
                'roomid' => $record->roomid,
                'folderid' => $record->folderid,
                'fileid' => $record->fileid,
                'tokenscope' => $shared ? 'shared-room' : 'room',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $session->timemodified = time();
        $DB->update_record('wsg_session', $session);
        audit_logger::log((int)$activity->id, 'docspace_template_created', [
            'roomid' => $record->roomid,
            'folderid' => $record->folderid,
            'fileid' => $record->fileid,
            'filename' => $record->filename,
            'tokenscope' => $shared ? 'shared-room' : 'room',
        ], (int)$session->id, 0, 0, $actorid);
        return $record;
    }

    /** Lazily create a room-isolated workspace for a team/individual attempt. */
    public static function ensure_attempt_workspace(
        \stdClass $activity,
        \stdClass $session,
        \stdClass $team,
        \stdClass $attempt,
        int $actorid
    ): \stdClass {
        global $DB;
        $existing = self::get_attempt_workspace((int)$attempt->id);
        if ($existing) {
            return self::ensure_link($existing);
        }
        $template = self::get_template((int)$session->id);
        if (!$template || !$template->fileid) {
            throw new \moodle_exception('docspacetemplatemissing', 'mod_worksheetgrader');
        }
        $template = self::ensure_link($template);

        $api = new client();
        $roomtitle = self::attempt_room_title($activity, $session, $team, (int)$attempt->id);
        $shared = self::shared_mode();
        $workspace = $shared ? [
            'roomid' => '',
            'folderid' => self::shared_folder_id(),
            'link' => ['url' => '', 'requesttoken' => self::shared_request_token()],
        ] : self::create_editable_room($api, $roomtitle);
        $destination = $shared ? $workspace['folderid'] : $workspace['roomid'];
        $filename = self::attempt_filename($template->filename, $session->name, $team->name);
        try {
            $file = $api->copy_file((string)$template->fileid, $destination, $filename);
        } catch (\Throwable $exception) {
            if (!$shared) {
                self::delete_room_quietly($api, $workspace['roomid']);
            }
            throw $exception;
        }
        $now = time();
        $record = (object)[
            'worksheetgraderid' => (int)$activity->id,
            'sessionid' => (int)$session->id,
            'teamid' => (int)$team->id,
            'attemptid' => (int)$attempt->id,
            'scope' => self::SCOPE_ATTEMPT,
            'roomid' => $shared ? '' : $workspace['roomid'],
            'folderid' => $shared ? $workspace['folderid'] : '',
            'fileid' => (string)$file['id'],
            'roomtitle' => $shared ? 'Configured shared Public Room' : $roomtitle,
            'filename' => (string)$file['title'],
            'publiclink' => $shared ? '' : (string)$workspace['link']['url'],
            'requesttoken' => (string)$workspace['link']['requesttoken'],
            'access' => self::ACCESS_EDIT,
            'status' => $shared ? 'shared-ready' : 'room-ready',
            'lastsync' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('wsg_docspace', $record);
        audit_logger::log((int)$activity->id, 'docspace_attempt_created', [
            'roomid' => $record->roomid,
            'folderid' => $record->folderid,
            'fileid' => $record->fileid,
            'tokenscope' => $shared ? 'shared-room' : 'room',
        ], (int)$session->id, (int)$team->id, (int)$attempt->id, $actorid);
        return $record;
    }

    /**
     * Upgrade a file/folder-token mapping to a dedicated Public Room token.
     *
     * DocSpace's no-login authentication is room-scoped. A file or folder
     * external link may display a document but can be downgraded to read-only.
     */
    public static function ensure_link(\stdClass $record): \stdClass {
        global $DB;
        if ($record->scope === self::SCOPE_ACTIVITY) {
            return $record;
        }
        if (self::shared_mode()) {
            if (empty($record->fileid)) {
                throw new \moodle_exception('docspaceinvalidresponse', 'mod_worksheetgrader', '', 'shared file id');
            }
            $folderid = self::shared_folder_id();
            $token = self::shared_request_token();
            if (str_starts_with((string)($record->status ?? ''), 'shared-')) {
                if ((string)$record->folderid !== $folderid || (string)$record->requesttoken !== $token) {
                    $record->folderid = $folderid;
                    $record->requesttoken = $token;
                    $record->roomid = '';
                    $record->publiclink = '';
                    $record->timemodified = time();
                    $DB->update_record('wsg_docspace', $record);
                }
                return $record;
            }
            $api = new client();
            $oldroomid = self::is_dedicated_room_mapping($record) ? (string)$record->roomid : '';
            $file = $api->copy_file(
                (string)$record->fileid,
                $folderid,
                self::normalise_filename((string)$record->filename)
            );
            $record->roomid = '';
            $record->folderid = $folderid;
            $record->fileid = (string)$file['id'];
            $record->roomtitle = 'Configured shared Public Room';
            $record->filename = (string)$file['title'];
            $record->publiclink = '';
            $record->requesttoken = $token;
            $record->status = $record->access === self::ACCESS_READ ? 'shared-submitted' : 'shared-migrated';
            $record->timemodified = time();
            $DB->update_record('wsg_docspace', $record);
            if ($oldroomid !== '') {
                self::delete_room_quietly($api, $oldroomid);
            }
            return $record;
        }
        if (self::is_dedicated_room_mapping($record) && !empty($record->requesttoken)) {
            return $record;
        }
        if (empty($record->fileid)) {
            throw new \moodle_exception('docspaceinvalidresponse', 'mod_worksheetgrader', '', 'legacy file id');
        }

        $api = new client();
        $access = $record->access === self::ACCESS_READ ? 1 : 2;
        $roomtitle = self::legacy_room_title($record);
        $room = $api->create_public_room($roomtitle);
        try {
            $file = $api->copy_file(
                (string)$record->fileid,
                (string)$room['id'],
                self::normalise_filename((string)$record->filename)
            );
            $link = $api->set_room_link((string)$room['id'], $access);
        } catch (\Throwable $exception) {
            self::delete_room_quietly($api, (string)$room['id']);
            throw $exception;
        }

        $record->roomid = (string)$room['id'];
        $record->folderid = '';
        $record->fileid = (string)$file['id'];
        $record->roomtitle = $roomtitle;
        $record->filename = (string)$file['title'];
        $record->publiclink = $link['url'];
        $record->requesttoken = $link['requesttoken'];
        $record->status = $access === 1 ? 'room-submitted' : 'room-migrated';
        $record->timemodified = time();
        $DB->update_record('wsg_docspace', $record);
        return $record;
    }

    /** Take a Moodle-owned snapshot before submission. */
    public static function finalise_attempt(
        \context_module $context,
        \stdClass $activity,
        \stdClass $session,
        \stdClass $team,
        \stdClass $attempt,
        int $actorid
    ): \stdClass {
        global $DB;
        $record = self::ensure_attempt_workspace($activity, $session, $team, $attempt, $actorid);
        $api = new client();
        $tmp = make_request_directory() . '/' . self::normalise_filename($record->filename ?: 'bai-lam.docx');
        $uri = $api->get_presigned_uri((string)$record->fileid);
        $api->download_to_path($uri, $tmp);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_worksheetgrader', 'docspacesnapshot', (int)$attempt->id);
        $stored = $fs->create_file_from_pathname([
            'contextid' => $context->id,
            'component' => 'mod_worksheetgrader',
            'filearea' => 'docspacesnapshot',
            'itemid' => (int)$attempt->id,
            'filepath' => '/',
            'filename' => self::normalise_filename($record->filename ?: 'bai-lam.docx'),
            'userid' => $actorid,
        ], $tmp);
        @unlink($tmp);
        $record->status = self::shared_mode() ? 'shared-snapshot-ready' : 'room-snapshot-ready';
        $record->lastsync = time();
        $record->timemodified = time();
        $DB->update_record('wsg_docspace', $record);
        audit_logger::log((int)$activity->id, 'docspace_snapshot_saved', [
            'filename' => $stored->get_filename(),
            'contenthash' => $stored->get_contenthash(),
        ], (int)$session->id, (int)$team->id, (int)$attempt->id, $actorid);
        return $record;
    }

    /** Switch a submitted room link to read-only without rolling back Moodle submit. */
    public static function make_attempt_readonly(
        \stdClass $record,
        \stdClass $activity,
        \stdClass $session,
        \stdClass $team,
        \stdClass $attempt,
        int $actorid
    ): \stdClass {
        global $DB;
        try {
            $record = self::ensure_link($record);
            if (str_starts_with((string)$record->status, 'shared-') || self::shared_mode()) {
                // The configured requestToken is room-scoped and may be shared by many files.
                // Never downgrade the whole room when one learner submits. Moodle's snapshot is authoritative.
                $record->access = self::ACCESS_READ;
                $record->status = 'shared-submitted';
            } else {
                $link = (new client())->set_room_link((string)$record->roomid, 1);
                $record->publiclink = $link['url'];
                $record->requesttoken = $link['requesttoken'];
                $record->access = self::ACCESS_READ;
                $record->status = 'room-submitted';
            }
        } catch (\Throwable $exception) {
            $record->status = 'room-snapshot-warning';
            audit_logger::log((int)$activity->id, 'docspace_readonly_failed', [
                'message' => clean_param($exception->getMessage(), PARAM_TEXT),
            ], (int)$session->id, (int)$team->id, (int)$attempt->id, $actorid);
        }
        $record->timemodified = time();
        $DB->update_record('wsg_docspace', $record);
        return $record;
    }

    /** Promote the original Moodle source file to DocSpace, or create a blank DOCX. */
    public static function create_template_from_session_source_or_blank(
        \stdClass $activity,
        \stdClass $session,
        \context_module $context,
        int $actorid
    ): \stdClass {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_worksheetgrader',
            'source',
            (int)$session->id,
            'timemodified DESC, id DESC',
            false
        );
        foreach ($files as $file) {
            if (preg_match('/\.(docx|xlsx|pptx|pdf)$/i', $file->get_filename())) {
                return self::create_template_from_stored_file($activity, $session, $file, $actorid);
            }
        }
        $filename = self::normalise_filename(strip_tags(format_string($session->name)) . '.docx');
        return self::create_blank_template($activity, $session, $filename, $actorid);
    }

    /** Build browser-side SDK configuration without exposing the administrator API key. */
    public static function frame_config(\stdClass $record, string $frameid, bool $readonly = false): array {
        global $USER;
        $record = self::ensure_link($record);
        $sdkversion = trim((string)get_config('mod_worksheetgrader', 'docspacesdkversion')) ?: 'auto';
        return [
            'frameId' => $frameid,
            'src' => rtrim((string)get_config('mod_worksheetgrader', 'docspaceurl'), '/'),
            'id' => (string)$record->fileid,
            'roomId' => (string)$record->roomid,
            'folderId' => (string)$record->folderid,
            'requestToken' => (string)$record->requesttoken,
            'tokenScope' => str_starts_with((string)$record->status, 'shared-') ? 'shared-room' : 'room',
            'authMode' => $readonly ? 'public-viewer' : (str_starts_with((string)$record->status, 'shared-') ? 'shared-editor' : 'public-room'),
            'mode' => $readonly ? 'viewer' : (str_starts_with((string)$record->status, 'shared-') ? 'editor' : 'public-room'),
            'editorType' => 'desktop',
            'width' => '100%',
            'height' => '920px',
            'showMenu' => true,
            'showHeader' => true,
            'showTitle' => true,
            'theme' => 'System',
            'locale' => current_language() === 'vi' ? 'vi-VN' : 'en-US',
            'sdkVersion' => $sdkversion === 'auto' ? 'auto' : (preg_replace('/[^0-9.]/', '', $sdkversion) ?: 'auto'),
            'fallbackUrl' => (string)$record->publiclink,
            'readonly' => $readonly,
            'userLabel' => fullname($USER),
        ];
    }

    /** Best-effort cleanup of dedicated rooms when an activity is deleted. */
    public static function delete_remote_rooms_for_activity(int $activityid): void {
        global $DB;
        self::delete_remote_rooms($DB->get_records('wsg_docspace', ['worksheetgraderid' => $activityid]));
    }

    /** Best-effort cleanup of dedicated rooms when an unused session is deleted. */
    public static function delete_remote_rooms_for_session(int $sessionid): void {
        global $DB;
        self::delete_remote_rooms($DB->get_records('wsg_docspace', ['sessionid' => $sessionid]));
    }

    /** Delete only rooms known to be dedicated V11.5.2 workspaces. */
    private static function delete_remote_rooms(array $records): void {
        if (!self::configured()) {
            return;
        }
        try {
            $api = new client();
            $seen = [];
            foreach ($records as $record) {
                if (!self::is_dedicated_room_mapping($record)) {
                    continue;
                }
                $roomid = (string)$record->roomid;
                if ($roomid === '' || isset($seen[$roomid])) {
                    continue;
                }
                $seen[$roomid] = true;
                self::delete_room_quietly($api, $roomid);
            }
        } catch (\Throwable $ignored) {
            // Remote cleanup must never prevent Moodle data deletion.
        }
    }

    public static function get_snapshot(\context_module $context, int $attemptid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            $context->id,
            'mod_worksheetgrader',
            'docspacesnapshot',
            $attemptid,
            'timemodified DESC, id DESC',
            false
        );
        return $files ? reset($files) : null;
    }

    /** Create a Public Room and an anonymous room-scoped edit token. */
    private static function create_editable_room(client $api, string $title): array {
        $room = $api->create_public_room($title);
        try {
            $link = $api->set_room_link((string)$room['id'], 2);
        } catch (\Throwable $exception) {
            self::delete_room_quietly($api, (string)$room['id']);
            throw $exception;
        }
        return ['roomid' => (string)$room['id'], 'link' => $link];
    }

    private static function delete_room_quietly(client $api, string $roomid): void {
        if ($roomid === '') {
            return;
        }
        try {
            $api->delete_room($roomid);
        } catch (\Throwable $ignored) {
            // Cleanup failure must not hide the original operation error.
        }
    }

    private static function is_dedicated_room_mapping(\stdClass $record): bool {
        return str_starts_with((string)($record->status ?? ''), 'room-') &&
            !empty($record->roomid) && !empty($record->fileid);
    }

    private static function activity_room_title(\stdClass $activity): string {
        return self::safe_room_title('Moodle — ' . format_string($activity->name));
    }

    private static function template_room_title(\stdClass $activity, \stdClass $session): string {
        return self::safe_room_title(
            'Mẫu — ' . strip_tags(format_string($activity->name)) . ' — ' .
            strip_tags(format_string($session->name)) . ' — ' . time()
        );
    }

    private static function attempt_room_title(
        \stdClass $activity,
        \stdClass $session,
        \stdClass $team,
        int $attemptid
    ): string {
        return self::safe_room_title(
            'Bài làm — ' . strip_tags(format_string($activity->name)) . ' — ' .
            strip_tags(format_string($session->name)) . ' — ' .
            strip_tags(format_string($team->name)) . ' — ' . $attemptid
        );
    }

    private static function legacy_room_title(\stdClass $record): string {
        $prefix = $record->scope === self::SCOPE_TEMPLATE ? 'Mẫu đã chuyển' : 'Bài làm đã chuyển';
        return self::safe_room_title($prefix . ' — ' . ($record->filename ?: $record->fileid) . ' — ' . $record->id);
    }

    private static function safe_room_title(string $title): string {
        $title = trim(preg_replace('/[\\\/<>":|?*]+/u', '-', $title));
        return \core_text::substr($title ?: 'Worksheet', 0, 220);
    }

    private static function template_filename(\stdClass $session, string $filename): string {
        $path = pathinfo(self::normalise_filename($filename));
        $extension = isset($path['extension']) ? '.' . strtolower($path['extension']) : '.docx';
        $basename = $path['filename'] ?? 'Phieu-hoc-tap';
        return self::normalise_filename(
            strip_tags(format_string($session->name)) . ' - Mau - ' . $basename . $extension
        );
    }

    private static function attempt_filename(string $templatefilename, string $sessionname, string $teamname): string {
        $path = pathinfo($templatefilename ?: 'Phieu-hoc-tap.docx');
        $extension = isset($path['extension']) ? '.' . strtolower($path['extension']) : '.docx';
        $basename = $path['filename'] ?? 'Phieu-hoc-tap';
        return self::normalise_filename(
            $basename . ' - ' . strip_tags(format_string($sessionname)) . ' - ' .
            strip_tags(format_string($teamname)) . $extension
        );
    }

    private static function normalise_filename(string $filename): string {
        $filename = clean_param($filename, PARAM_FILE);
        if ($filename === '' || $filename === '.') {
            return 'Phieu-hoc-tap.docx';
        }
        if (!preg_match('/\.(docx|xlsx|pptx|pdf)$/i', $filename)) {
            $filename .= '.docx';
        }
        return \core_text::substr($filename, 0, 240);
    }
}
