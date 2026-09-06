<?php
namespace local_digieramedia\db;
interface transaction_runner_interface { public function run(callable $callback): mixed; }
