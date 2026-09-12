<?php
// Standalone RED/GREEN contract for Native draft persistence.
declare(strict_types=1);

namespace {
    const MUST_EXIST = 2;
    class invalid_parameter_exception extends \RuntimeException {}
    class moodle_exception extends \RuntimeException {}
}

namespace core\lock {
    final class test_lock {
        public bool $released = false;
        public function release(): bool {
            $this->released = true;
            return true;
        }
    }
    final class test_lock_factory {
        public ?test_lock $lastlock = null;
        public function get_lock($resource, $timeout, $maxlifetime = 86400) {
            $this->lastlock = new test_lock();
            return $this->lastlock;
        }
    }
    final class lock_config {
        public static ?test_lock_factory $factory = null;
        public static function get_lock_factory(string $type): test_lock_factory {
            self::$factory ??= new test_lock_factory();
            return self::$factory;
        }
    }
}

// The production service is Moodle-autoloaded and reads real File API assets.
// This standalone harness has no Moodle autoloader/file storage, so provide the
// narrow dependency needed by native_version_service. The fixture below contains
// no image nodes, therefore an empty asset URL map is the correct test input.
namespace local_worksheetlibrary\service {
    final class native_asset_service {
        public static int $asseturlcalls = 0;

        public static function asset_urls(int $versionid): array {
            self::$asseturlcalls++;
            if ($versionid !== 11) {
                throw new \RuntimeException('Unexpected standalone Native asset version id');
            }
            return [];
        }
    }
}

namespace {
    final class fake_transaction {
        public bool $committed = false;
        public function allow_commit(): void { $this->committed = true; }
        public function rollback($e): void { throw $e; }
    }

    final class fake_db {
        /** @var array<string,array<int,stdClass>> */
        public array $tables = [];
        public ?fake_transaction $transaction = null;
        public int $updates = 0;

        public function __construct() {
            $this->tables['wslib_item'][7] = (object)[
                'id' => 7,
                'kind' => 'native',
                'archived' => 0,
            ];
            $this->tables['wslib_version'][11] = (object)[
                'id' => 11,
                'itemid' => 7,
                'state' => 'draft',
                'nativejson' => null,
                'schemaversion' => null,
                'revision' => 0,
                'renderedhtml' => null,
                'contenthash' => '',
            ];
        }

        public function start_delegated_transaction(): fake_transaction {
            return $this->transaction = new fake_transaction();
        }

        public function get_record(string $table, array $conditions, string $fields = '*', int $strictness = 0) {
            foreach ($this->tables[$table] ?? [] as $record) {
                $ok = true;
                foreach ($conditions as $key => $value) {
                    if (!property_exists($record, $key) || $record->{$key} != $value) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) {
                    return clone $record;
                }
            }
            if ($strictness === MUST_EXIST) {
                throw new \RuntimeException("Missing record: $table");
            }
            return false;
        }

        public function update_record(string $table, object $record): bool {
            if (!isset($record->id, $this->tables[$table][$record->id])) {
                throw new \RuntimeException("Missing update target: $table");
            }
            $current = $this->tables[$table][$record->id];
            foreach (get_object_vars($record) as $key => $value) {
                $current->{$key} = $value;
            }
            $this->updates++;
            return true;
        }
    }

    function assert_true(bool $condition, string $message): void {
        if (!$condition) {
            throw new \RuntimeException('ASSERT FAIL: ' . $message);
        }
    }

    require_once __DIR__ . '/../../digieranative/classes/document/schema.php';
    require_once __DIR__ . '/../../digieranative/classes/document/validator.php';
    require_once __DIR__ . '/../../digieranative/classes/document/renderer.php';

    $exceptionfile = __DIR__ . '/../classes/exception/native_revision_conflict_exception.php';
    $servicefile = __DIR__ . '/../classes/service/native_version_service.php';
    assert_true(is_file($exceptionfile), 'typed Native revision conflict exception must exist');
    assert_true(is_file($servicefile), 'native_version_service must exist');
    require_once $exceptionfile;
    require_once $servicefile;

    $valid = json_encode([
        'type' => 'worksheet',
        'version' => 1,
        'content' => [[
            'type' => 'paragraph',
            'content' => [[
                'type' => 'text',
                'text' => 'Xin chào Native',
            ]],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Successful save increments N -> N+1 and persists canonical source/rendered HTML.
    $GLOBALS['DB'] = new fake_db();
    \local_worksheetlibrary\service\native_asset_service::$asseturlcalls = 0;
    $saved = \local_worksheetlibrary\service\native_version_service::save_draft(11, 0, $valid, 99);
    assert_true((int)$saved->revision === 1, 'first save revision must become 1');
    assert_true($saved->nativejson === $valid, 'Native JSON must persist exactly');
    assert_true((int)$saved->schemaversion === 1, 'schema version must persist');
    assert_true(str_contains((string)$saved->renderedhtml, 'Xin chào Native'), 'server-rendered HTML must persist');
    assert_true(hash('sha256', $valid) === $saved->contenthash, 'content hash must cover Native JSON');
    assert_true($GLOBALS['DB']->transaction?->committed === true, 'save transaction must commit');
    assert_true(
        \local_worksheetlibrary\service\native_asset_service::$asseturlcalls === 1,
        'Native save must resolve managed asset URLs before rendering'
    );

    // Stale expected revision raises typed conflict and does not mutate.
    $before = clone $GLOBALS['DB']->tables['wslib_version'][11];
    $updatesbefore = $GLOBALS['DB']->updates;
    $conflicted = false;
    try {
        \local_worksheetlibrary\service\native_version_service::save_draft(11, 0, $valid, 99);
    } catch (\local_worksheetlibrary\exception\native_revision_conflict_exception $e) {
        $conflicted = true;
        assert_true($e->current_revision() === 1, 'conflict exposes current revision');
    }
    assert_true($conflicted, 'stale expected revision must conflict');
    assert_true($GLOBALS['DB']->updates === $updatesbefore, 'conflict must not update draft');
    assert_true($GLOBALS['DB']->tables['wslib_version'][11]->nativejson === $before->nativejson, 'conflict must preserve Native JSON');

    // Invalid Native JSON is rejected without mutation.
    $GLOBALS['DB'] = new fake_db();
    $updatesbefore = $GLOBALS['DB']->updates;
    $invalidrejected = false;
    try {
        \local_worksheetlibrary\service\native_version_service::save_draft(11, 0, '{"type":"wrong"}', 99);
    } catch (\invalid_parameter_exception $e) {
        $invalidrejected = true;
    }
    assert_true($invalidrejected, 'validator failure must propagate');
    assert_true($GLOBALS['DB']->updates === $updatesbefore, 'invalid JSON must not update draft');

    // Non-Native item is rejected.
    $GLOBALS['DB'] = new fake_db();
    $GLOBALS['DB']->tables['wslib_item'][7]->kind = 'html';
    $wrongkindrejected = false;
    try {
        \local_worksheetlibrary\service\native_version_service::save_draft(11, 0, $valid, 99);
    } catch (\invalid_parameter_exception $e) {
        $wrongkindrejected = true;
    }
    assert_true($wrongkindrejected, 'Native save must reject non-Native item');

    echo "NATIVE_DRAFT_FIRST_SAVE=PASS\n";
    echo "NATIVE_DRAFT_CONFLICT=PASS\n";
    echo "NATIVE_DRAFT_VALIDATION=PASS\n";
    echo "NATIVE_DRAFT_RENDER=PASS\n";
    echo "NATIVE_DRAFT_ASSET_URL_RESOLUTION=PASS\n";
}
