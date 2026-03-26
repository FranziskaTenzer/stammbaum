<?php
// Call the required initialization and import scripts in sequence.
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_init.php';
require_once 'importThierbach.php';
require_once 'importOrte.php';
?>