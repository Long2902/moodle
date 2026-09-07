<?php
// Standalone RED/GREEN service slice for Preview v0.1 Native workflow.
declare(strict_types=1);

namespace {
    define('MOODLE_INTERNAL', true);
    const MUST_EXIST = 2;
    const FORMAT_PLAIN = 0;
    class moodle_exception extends \RuntimeException { public function __construct($errorcode='', $module='', $link='', $a=null, $debuginfo=null) { parent::__construct((string)$errorcode); } }
    class required_capability_exception extends \RuntimeException { public function __construct(...$args) { parent::__construct('required capability'); } }
    class context_module {
        public int $id;
        public static function instance(int $id): self { $x = new self(); $x->id = $id; return $x; }
    }
    function get_coursemodule_from_instance($mod, $instance, $course, $section = false, $strictness = 0) {
        return (object)['id' => 99, 'instance' => $instance, 'course' => $course];
    }
    final class fake_file_storage {
        public function delete_area_files(...$args): void {}
        public function create_file_from_storedfile(...$args) { throw new \RuntimeException('Native snapshot must not clone a file'); }
    }
    function get_file_storage(): fake_file_storage { static $fs; return $fs ??= new fake_file_storage(); }
    function worksheetgrader_update_completion(...$args): void {}
    function worksheetgrader_update_grades(...$args): void {}
    function worksheetgrader_build_submission_html(...$args): string { throw new \RuntimeException('Native submit must use Native renderer'); }
}

namespace core\lock {
    final class test_lock { public function release(): bool { return true; } }
    final class test_lock_factory { public function get_lock($resource, $timeout, $maxlifetime = 86400) { return new test_lock(); } }
    final class lock_config { public static function get_lock_factory(string $type): test_lock_factory { return new test_lock_factory(); } }
}

namespace local_worksheetlibrary {
    final class api {
        public static ?\stdClass $published = null;
        public static function published_version(int $versionid): \stdClass {
            if (!self::$published || (int)self::$published->id !== $versionid) {
                throw new \RuntimeException('Library unavailable after snapshot');
            }
            return unserialize(serialize(self::$published));
        }
        public static function primary_file(int $versionid) { return null; }
    }
}

namespace mod_worksheetgrader\service {
    final class audit_logger { public static function log(...$args): void {} }
    final class team_manager {
        public static array $members = [100];
        public static function get_member_ids(int $teamid): array { return self::$members; }
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
        private array $next = ['wsg_attempt' => 500, 'wsg_grade' => 700];
        public function start_delegated_transaction(): fake_transaction { return new fake_transaction(); }
        public function get_record(string $table, array $conditions, string $fields = '*', int $strictness = 0) {
            foreach ($this->tables[$table] ?? [] as $record) {
                $ok = true;
                foreach ($conditions as $key => $value) {
                    if (!property_exists($record, $key) || $record->{$key} != $value) { $ok = false; break; }
                }
                if ($ok) { return clone $record; }
            }
            if ($strictness === MUST_EXIST) { throw new \RuntimeException("Missing $table"); }
            return false;
        }
        public function record_exists(string $table, array $conditions): bool { return (bool)$this->get_record($table, $conditions); }
        public function insert_record(string $table, object $record) {
            $id = $record->id ?? ($this->next[$table] = ($this->next[$table] ?? 1) + 1);
            $copy = clone $record; $copy->id = $id; $this->tables[$table][$id] = $copy; return $id;
        }
        public function update_record(string $table, object $record): bool {
            if (!isset($record->id, $this->tables[$table][$record->id])) { throw new \RuntimeException("Missing update $table"); }
            $current = $this->tables[$table][$record->id];
            foreach (get_object_vars($record) as $k => $v) { $current->{$k} = $v; }
            return true;
        }
    }

    function assert_true(bool $condition, string $message): void {
        if (!$condition) { throw new \RuntimeException('ASSERT FAIL: ' . $message); }
    }

    $repo = dirname(__DIR__, 3);
    require_once $repo . '/local/digieranative/classes/document/schema.php';
    require_once $repo . '/local/digieranative/classes/document/validator.php';
    require_once $repo . '/local/digieranative/classes/document/renderer.php';
    require_once __DIR__ . '/../classes/service/worksheet_snapshot_service.php';
    require_once __DIR__ . '/../classes/service/attempt_manager.php';
    require_once __DIR__ . '/../classes/service/grading_manager.php';

    $teacherjson = json_encode([
        'type' => 'worksheet', 'version' => 1, 'content' => [[
            'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Câu hỏi Native']],
        ], [
            'type' => 'longAnswer', 'attrs' => ['questionId' => 'q1', 'placeholder' => 'Trả lời'],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $teacherhtml = \local_digieranative\document\renderer::render_json($teacherjson, 'preview');
    \local_worksheetlibrary\api::$published = (object)[
        'id' => 91,
        'itemid' => 51,
        'state' => 'published',
        'nativejson' => $teacherjson,
        'schemaversion' => 1,
        'renderedhtml' => $teacherhtml,
        'contenthash' => hash('sha256', $teacherjson),
        'item' => (object)['id' => 51, 'kind' => 'native'],
    ];

    $GLOBALS['DB'] = new fake_db();
    $GLOBALS['DB']->tables['worksheetgrader'][10] = (object)[
        'id' => 10, 'course' => 5, 'contenthtml' => '', 'representative' => 'any',
        'completiononsubmit' => 0, 'completionongrade' => 0, 'allowadjust' => 0,
        'grade' => 10.0, 'aggregation' => 'average',
    ];
    $GLOBALS['DB']->tables['wsg_session'][20] = (object)[
        'id' => 20, 'worksheetgraderid' => 10, 'status' => 'draft', 'teammode' => 'temporary',
        'contentmode' => 'html', 'contenthtml' => '', 'metadatajson' => '{}', 'membershiplocked' => 1,
        'worksheetkind' => 'html', 'worksheethash' => '', 'libraryitemid' => 0, 'libraryversionid' => 0,
        'sourcefilename' => '', 'migrationstatus' => 'not_required', 'timemodified' => 0, 'maxpoints' => 10.0,
    ];
    $GLOBALS['DB']->tables['wsg_team'][30] = (object)['id' => 30, 'sessionid' => 20, 'representativeuserid' => 100];

    // 1) Select published Native worksheet and freeze all runtime content into the session.
    $session = \mod_worksheetgrader\service\worksheet_snapshot_service::attach_published_version(20, 91, 200);
    assert_true($session->worksheetkind === 'native', 'session kind must be native');
    assert_true($session->contentmode === 'html', 'Native runtime should stay in portable local content mode');
    assert_true(str_contains((string)$session->contenthtml, 'Câu hỏi Native'), 'session must keep rendered Native snapshot');
    $snapshot = json_decode((string)$session->metadatajson, true);
    assert_true(($snapshot['nativejson'] ?? '') === $teacherjson, 'session metadata must freeze canonical Native JSON');
    assert_true((int)($snapshot['schemaversion'] ?? 0) === 1, 'session metadata must freeze schema version');

    // Prove attempt runtime does not need the source Library after selection.
    \local_worksheetlibrary\api::$published = null;
    $session->status = 'open';
    $GLOBALS['DB']->update_record('wsg_session', $session);

    // 2) Start attempt from frozen Native snapshot.
    $team = $GLOBALS['DB']->get_record('wsg_team', ['id' => 30], '*', MUST_EXIST);
    $attempt = \mod_worksheetgrader\service\attempt_manager::get_or_create($session, $team, 100);
    assert_true(json_decode((string)$attempt->answersjson, true) === json_decode($teacherjson, true), 'attempt must start from frozen Native JSON');

    // 3) Save Native student answer through the normal optimistic attempt version lane.
    $studentjson = json_encode([
        'type' => 'worksheet', 'version' => 1, 'content' => [[
            'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Câu hỏi Native']],
        ], [
            'type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Câu trả lời của học sinh']],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $attempt = \mod_worksheetgrader\service\attempt_manager::save(
        $attempt, json_decode($studentjson, true), 100, (int)$attempt->version
    );
    assert_true((int)$attempt->version === 2, 'Native save must increment attempt version');
    assert_true(json_decode((string)$attempt->answersjson, true) === json_decode($studentjson, true), 'Native save must persist canonical document');

    // 4) Submit freezes immutable server-rendered submission.
    $activity = $GLOBALS['DB']->get_record('worksheetgrader', ['id' => 10], '*', MUST_EXIST);
    $cm = (object)['id' => 99];
    \mod_worksheetgrader\service\attempt_manager::submit($attempt, $session, $activity, $cm, 100);
    $submitted = $GLOBALS['DB']->get_record('wsg_attempt', ['id' => $attempt->id], '*', MUST_EXIST);
    assert_true($submitted->status === 'submitted', 'attempt must be submitted');
    assert_true(str_contains((string)$submitted->submissionhtml, 'Câu trả lời của học sinh'), 'submission HTML must be frozen from Native answer');
    $frozenjson = $submitted->answersjson;
    $frozenhtml = $submitted->submissionhtml;

    $rejected = false;
    try {
        \mod_worksheetgrader\service\attempt_manager::save($submitted, json_decode($teacherjson, true), 100, (int)$submitted->version);
    } catch (\moodle_exception $e) {
        $rejected = true;
    }
    assert_true($rejected, 'post-submit mutation must be rejected');

    // 5) Grade + feedback must not rewrite the frozen answer/submission.
    \mod_worksheetgrader\service\grading_manager::save_attempt_grades(
        $submitted, $session, $activity, $cm, 8.5, 'Tốt', [], true, 200
    );
    $graded = $GLOBALS['DB']->get_record('wsg_attempt', ['id' => $submitted->id], '*', MUST_EXIST);
    assert_true($graded->status === 'graded', 'published grading must mark attempt graded');
    assert_true($graded->groupfeedback === 'Tốt', 'feedback must persist');
    assert_true($graded->answersjson === $frozenjson, 'grading must not rewrite frozen Native JSON');
    assert_true($graded->submissionhtml === $frozenhtml, 'grading must not rewrite frozen submission HTML');
    $grade = $GLOBALS['DB']->get_record('wsg_grade', ['attemptid' => $submitted->id, 'userid' => 100], '*', MUST_EXIST);
    assert_true((float)$grade->finalgrade === 8.5 && (int)$grade->published === 1, 'published grade must persist');

    echo "NATIVE_SESSION_SNAPSHOT=PASS\n";
    echo "NATIVE_ATTEMPT_SAVE=PASS\n";
    echo "NATIVE_SUBMIT_FREEZE=PASS\n";
    echo "POST_SUBMIT_MUTATION_REJECTED=PASS\n";
    echo "NATIVE_GRADING_IMMUTABILITY=PASS\n";
}
