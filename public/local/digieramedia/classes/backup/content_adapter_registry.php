<?php
namespace local_digieramedia\backup;

use local_digieramedia\backup\adapter\book_adapter;
use local_digieramedia\backup\adapter\generic_intro_adapter;
use local_digieramedia\backup\adapter\label_adapter;
use local_digieramedia\backup\adapter\page_adapter;

final class content_adapter_registry {
    public function for_module(string $modname): ?content_adapter_interface {
        return match ($modname) {
            'page' => new page_adapter(),
            'label' => new label_adapter(),
            'book' => new book_adapter(),
            'subsection' => null,
            default => $this->generic($modname),
        };
    }

    private function generic(string $modname): ?content_adapter_interface {
        $adapter = new generic_intro_adapter($modname);
        return $adapter->supports($modname) ? $adapter : null;
    }
}
