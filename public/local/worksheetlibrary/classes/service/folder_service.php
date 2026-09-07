<?php
namespace local_worksheetlibrary\service;

final class folder_service {
    private static function require_active_target(int $folderid): void {
        global $DB;
        if ($folderid !== 0 && !$DB->record_exists('wslib_folder', ['id' => $folderid, 'archived' => 0])) {
            throw new \invalid_argument_exception('Target folder not found');
        }
    }

    public static function create(int $parentid, string $name, int $userid): int {
        global $DB;
        self::require_active_target($parentid);
        $name = trim($name);
        if ($name === '') {
            throw new \invalid_argument_exception('Folder name required');
        }
        $now = time();
        return $DB->insert_record('wslib_folder', (object)[
            'parentid' => $parentid,
            'name' => $name,
            'sortorder' => 0,
            'archived' => 0,
            'createdby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    public static function move(int $id, int $parentid): void {
        global $DB;
        $DB->get_record('wslib_folder', ['id' => $id, 'archived' => 0], 'id', MUST_EXIST);
        self::require_active_target($parentid);
        if ($id === $parentid) {
            throw new \invalid_argument_exception('Folder cycle');
        }
        $cursor = $parentid;
        $seen = [];
        while ($cursor) {
            if ($cursor === $id) {
                throw new \invalid_argument_exception('Folder cycle');
            }
            if (isset($seen[$cursor])) {
                throw new \invalid_argument_exception('Corrupt folder tree');
            }
            $seen[$cursor] = true;
            $parent = $DB->get_record('wslib_folder', ['id' => $cursor, 'archived' => 0], 'id,parentid', MUST_EXIST);
            $cursor = (int)$parent->parentid;
        }
        $DB->update_record('wslib_folder', (object)[
            'id' => $id,
            'parentid' => $parentid,
            'timemodified' => time(),
        ]);
    }

    public static function tree(): array {
        global $DB;
        $rows = $DB->get_records('wslib_folder', ['archived' => 0], 'parentid,sortorder,name,id');
        $byparent = [];
        foreach ($rows as $row) {
            $byparent[(int)$row->parentid][] = $row;
        }
        $walk = function(int $parentid, int $depth) use (&$walk, &$byparent): array {
            $out = [];
            foreach ($byparent[$parentid] ?? [] as $row) {
                $row->depth = $depth;
                $out[] = $row;
                $out = array_merge($out, $walk((int)$row->id, $depth + 1));
            }
            return $out;
        };
        return $walk(0, 0);
    }

    public static function copy_tree(int $id, int $newparentid, int $userid): int {
        global $DB;
        $source = $DB->get_record('wslib_folder', ['id' => $id, 'archived' => 0], '*', MUST_EXIST);
        self::require_active_target($newparentid);
        $newid = self::create($newparentid, $source->name . ' - Copy', $userid);
        foreach ($DB->get_records('wslib_item', ['folderid' => $id, 'archived' => 0]) as $item) {
            version_service::copy_item((int)$item->id, $newid, $userid);
        }
        foreach ($DB->get_records('wslib_folder', ['parentid' => $id, 'archived' => 0]) as $child) {
            self::copy_tree((int)$child->id, $newid, $userid);
        }
        return $newid;
    }
}
