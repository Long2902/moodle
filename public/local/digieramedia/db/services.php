<?php

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_digieramedia_search_media' => [
        'classname' => 'local_digieramedia\\external\\search_media',
        'methodname' => 'execute',
        'description' => 'Search DIGIERA Media visible in an editor context.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_create_reference' => [
        'classname' => 'local_digieramedia\\external\\create_reference',
        'methodname' => 'execute',
        'description' => 'Create one draft DIGIERA reference for TinyMCE insertion.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_update_reference_version' => [
        'classname' => 'local_digieramedia\\external\\update_reference_version',
        'methodname' => 'execute',
        'description' => 'Update the saved version mode of one existing DIGIERA reference.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_resolve_references' => [
        'classname' => 'local_digieramedia\\external\\resolve_references',
        'methodname' => 'execute',
        'description' => 'Resolve DIGIERA reference UUIDs to media metadata for TinyMCE rehydration.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_get_media_versions' => [
        'classname' => 'local_digieramedia\\external\\get_media_versions',
        'methodname' => 'execute',
        'description' => 'Return immutable version history for one DIGIERA Media item.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_create_upload_session' => [
        'classname' => 'local_digieramedia\\external\\create_upload_session',
        'methodname' => 'execute',
        'description' => 'Authorize a server-owned direct Cloudflare R2 single-PUT upload.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'local_digieramedia_finalize_upload' => [
        'classname' => 'local_digieramedia\\external\\finalize_upload',
        'methodname' => 'execute',
        'description' => 'Verify an R2 object and idempotently commit DIGIERA Media metadata.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ],
];
