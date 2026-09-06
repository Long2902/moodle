<?php
namespace local_digieramedia\lock;
interface lock_manager_interface { public function acquire(string $key, int $timeout=10): lock_handle_interface; }
