<?php
namespace local_worksheetlibrary\exception;

final class native_revision_conflict_exception extends \RuntimeException {
    public function __construct(private readonly int $currentrevision) {
        parent::__construct('Native worksheet revision conflict');
    }

    public function current_revision(): int {
        return $this->currentrevision;
    }
}
