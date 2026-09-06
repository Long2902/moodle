<?php
namespace local_digieramedia\restore;

final class clone_mode {
    public const SHARED_FOLLOW = 'shared_follow';
    public const SHARED_PINNED = 'shared_pinned';
    public const INDEPENDENT = 'independent';

    public static function assert_valid(string $mode): void {
        if (!in_array($mode, [self::SHARED_FOLLOW, self::SHARED_PINNED, self::INDEPENDENT], true)) {
            throw new \InvalidArgumentException('Unsupported DIGIERA Media clone mode');
        }
    }
}
