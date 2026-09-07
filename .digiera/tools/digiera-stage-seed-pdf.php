<?php

declare(strict_types=1);

define('CLI_SCRIPT', true);

$options = getopt('', ['config:', 'url:', 'name::']);
$config = $options['config'] ?? '/var/www/moodle/public/config.php';
$url = $options['url'] ?? '';
$name = trim((string)($options['name'] ?? 'DIGIERA Stage PDF'));

if (!is_string($config) || !is_file($config)) {
    fwrite(STDERR, "Invalid --config path\n");
    exit(2);
}
if (!is_string($url) || $url === '') {
    fwrite(STDERR, "Usage: php digiera-stage-seed-pdf.php --url=https://cdn.digiera.vn/path/file.pdf [--name='Test PDF'] [--config=/path/config.php]\n");
    exit(2);
}

$parts = parse_url($url);
if (!is_array($parts)
        || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
        || strtolower((string)($parts['host'] ?? '')) !== 'cdn.digiera.vn'
        || empty($parts['path'])) {
    fwrite(STDERR, "Stage seed only accepts HTTPS URLs on cdn.digiera.vn\n");
    exit(2);
}

$objectkey = rawurldecode(ltrim((string)$parts['path'], '/'));
if (!preg_match('/\.pdf$/i', $objectkey)) {
    fwrite(STDERR, "Stage seed URL must point to a PDF\n");
    exit(2);
}

require $config;

if (!isset($DB)) {
    fwrite(STDERR, "Moodle DB is unavailable\n");
    exit(2);
}

function digiera_stage_uuid_v4(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-'
        . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-'
        . substr($hex, 20, 12);
}

$filename = basename($objectkey);
$now = time();
$systemcontext = context_system::instance();
$transaction = $DB->start_delegated_transaction();

$version = $DB->get_record('local_digieramedia_version', [
    'objectkey' => $objectkey,
    'status' => 'READY',
]);
$media = null;

if ($version) {
    $media = $DB->get_record('local_digieramedia_media', [
        'id' => (int)$version->mediaid,
        'status' => 'ACTIVE',
    ]);
}

if (!$media) {
    $mediaid = $DB->insert_record('local_digieramedia_media', (object)[
        'uuid' => digiera_stage_uuid_v4(),
        'name' => $name !== '' ? $name : $filename,
        'description' => 'Temporary staging seed for DIGIERA Media vertical-slice verification.',
        'mediatype' => 'pdf',
        'mimetype' => 'application/pdf',
        'owneruserid' => 0,
        'origincontextid' => (int)$systemcontext->id,
        'origincourseid' => 0,
        'originsectionid' => 0,
        'visibility' => 'SHARED',
        'status' => 'ACTIVE',
        'currentversionid' => 0,
        'timecreated' => $now,
        'timemodified' => $now,
        'createdby' => 0,
        'modifiedby' => 0,
    ], true);

    $versionid = $DB->insert_record('local_digieramedia_version', (object)[
        'mediaid' => (int)$mediaid,
        'versionno' => 1,
        'bucket' => 'digiera-cdn-stage',
        'objectkey' => $objectkey,
        'originalfilename' => $filename,
        'displayfilename' => $filename,
        'filesize' => 0,
        'mimetype' => 'application/pdf',
        'etag' => 'stage-seed',
        'status' => 'READY',
        'timecreated' => $now,
        'createdby' => 0,
        'restoredfromversionid' => 0,
        'timepurged' => 0,
    ], true);

    $DB->set_field('local_digieramedia_media', 'currentversionid', (int)$versionid, ['id' => (int)$mediaid]);
    $media = $DB->get_record('local_digieramedia_media', ['id' => (int)$mediaid], '*', MUST_EXIST);
    $version = $DB->get_record('local_digieramedia_version', ['id' => (int)$versionid], '*', MUST_EXIST);
}

$reference = $DB->get_record('local_digieramedia_reference', [
    'mediaid' => (int)$media->id,
    'component' => 'local_digieramedia',
    'entitytype' => 'stage_seed',
    'fieldname' => 'content',
    'status' => 'ACTIVE',
]);

if (!$reference) {
    $referenceid = $DB->insert_record('local_digieramedia_reference', (object)[
        'uuid' => digiera_stage_uuid_v4(),
        'mediaid' => (int)$media->id,
        'contextid' => (int)$systemcontext->id,
        'courseid' => 0,
        'cmid' => 0,
        'component' => 'local_digieramedia',
        'entitytype' => 'stage_seed',
        'entityid' => 0,
        'fieldname' => 'content',
        'displayprofile' => 'embedded',
        'versionmode' => 'FOLLOW_CURRENT',
        'pinnedversionid' => 0,
        'status' => 'ACTIVE',
        'createdby' => 0,
        'alttext' => null,
        'caption' => null,
        'optionsjson' => '{}',
        'timecreated' => $now,
        'timemodified' => $now,
    ], true);
    $reference = $DB->get_record('local_digieramedia_reference', ['id' => (int)$referenceid], '*', MUST_EXIST);
}

$transaction->allow_commit();

$marker = '[[digiera-ref:' . $reference->uuid . ']]';
echo "STAGE_SEED=PASS\n";
echo "MEDIA_UUID={$media->uuid}\n";
echo "VERSION_ID={$version->id}\n";
echo "REFERENCE_UUID={$reference->uuid}\n";
echo "OBJECT_KEY={$objectkey}\n";
echo "MARKER={$marker}\n";
echo "NEXT=Paste MARKER as normal text into a Moodle Page and save it.\n";
