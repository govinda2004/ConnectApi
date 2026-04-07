<?php
/**
 * JSON response helpers.
 */

function jsonSuccess($data = null, string $message = 'Success', int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ]);
    exit;
}

function jsonError(string $message = 'Error', int $code = 400, $errors = null): void {
    http_response_code($code);
    header('Content-Type: application/json');
    $resp = [
        'success' => false,
        'message' => $message,
    ];
    if ($errors !== null) $resp['errors'] = $errors;
    echo json_encode($resp);
    exit;
}

function jsonAuth($token, $user, string $message = 'Success'): void {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'token'   => $token,
        'user'    => $user,
    ]);
    exit;
}
