<?php
header('Content-Type: application/json');
echo json_encode([
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'NOT SET',
    'PATH_INFO' => $_SERVER['PATH_INFO'] ?? 'NOT SET',
    'SCRIPT_NAME' => $_SERVER['SCRIPT_NAME'] ?? 'NOT SET',
    'PHP_SELF' => $_SERVER['PHP_SELF'] ?? 'NOT SET',
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? 'NOT SET',
    'argv' => $_SERVER['argv'] ?? 'NOT SET',
    'REDIRECT_URL' => $_SERVER['REDIRECT_URL'] ?? 'NOT SET',
]);
