<?php
require_once 'mysqlHandle.php';

$dsn = sprintf("mysql:host=%s;dbname=%s;port=%d;charset=%s", DB_HOST, DB_NAME, DB_PORT, DB_CHARSET);
$pdo = new mysqlHandle($dsn, DB_USER, DB_PASSWORD, 1);
