<?php

/**
 * Firebase Cloud Messaging (HTTP v1) helper.
 *
 * Required configuration (one of):
 * 1) ENV CONNECTIN_FCM_SERVICE_ACCOUNT_JSON = raw JSON content
 * 2) ENV CONNECTIN_FCM_SERVICE_ACCOUNT_FILE = absolute file path
 */

function fcmBase64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function fcmMaskToken(string $token): string
{
    $len = strlen($token);
    if ($len <= 12) return $token;
    return substr($token, 0, 6) . '...' . substr($token, -6);
}

function loadFcmServiceAccount(): ?array
{
    $raw = getenv('CONNECTIN_FCM_SERVICE_ACCOUNT_JSON');
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    $file = getenv('CONNECTIN_FCM_SERVICE_ACCOUNT_FILE');
    if (is_string($file) && trim($file) !== '' && is_file($file)) {
        $content = file_get_contents($file);
        if ($content === false) return null;
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    return null;
}

function getFcmAccessToken(): ?string
{
    $service = loadFcmServiceAccount();
    if (!$service) return null;

    $clientEmail = $service['client_email'] ?? '';
    $privateKey = $service['private_key'] ?? '';
    if ($clientEmail === '' || $privateKey === '') return null;

    $now = time();
    $header = fcmBase64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = fcmBase64UrlEncode(json_encode([
        'iss' => $clientEmail,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600,
    ]));
    $unsignedJwt = $header . '.' . $claims;

    $signature = '';
    $ok = openssl_sign($unsignedJwt, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok) return null;
    $jwt = $unsignedJwt . '.' . fcmBase64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) return null;
    $decoded = json_decode($response, true);
    $token = $decoded['access_token'] ?? null;
    return is_string($token) && $token !== '' ? $token : null;
}

function sendFcmToDeviceToken(
    string $deviceToken,
    string $title,
    string $body,
    array $data = [],
    ?array &$meta = null,
    ?string $imageUrl = null
): bool {
    $meta = [
        'function_hit' => true,
        'attempted' => false,
        'ok' => false,
        'reason' => '',
        'http_status' => null,
        'response_excerpt' => null,
        'token_masked' => fcmMaskToken($deviceToken),
    ];
    if ($deviceToken === '') {
        error_log('[FCM] skipped: empty device token');
        $meta['reason'] = 'empty_device_token';
        return false;
    }

    $service = loadFcmServiceAccount();
    if (!$service) {
        error_log('[FCM] skipped: service account not configured');
        $meta['reason'] = 'service_account_not_configured';
        return false;
    }
    $projectId = $service['project_id'] ?? '';
    if ($projectId === '') {
        error_log('[FCM] skipped: project_id missing in service account');
        $meta['reason'] = 'project_id_missing';
        return false;
    }

    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        error_log('[FCM] skipped: access token generation failed');
        $meta['reason'] = 'access_token_failed';
        return false;
    }

    $maskedToken = fcmMaskToken($deviceToken);
    $meta['attempted'] = true;
    error_log('[FCM] send attempt: project=' . $projectId . ' token=' . $maskedToken . ' title=' . $title);

    $payload = [
        'message' => [
            'token' => $deviceToken,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $data,
            'android' => ['priority' => 'high'],
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ],
    ];
    if (is_string($imageUrl) && trim($imageUrl) !== '') {
        $payload['message']['notification']['image'] = trim($imageUrl);
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $meta['http_status'] = $status;
    $meta['response_excerpt'] = is_string($response) ? mb_substr($response, 0, 300) : null;

    if ($response === false) {
        error_log('[FCM] curl failed: token=' . $maskedToken . ' error=' . $curlError);
        $meta['reason'] = 'curl_failed';
        $meta['response_excerpt'] = mb_substr((string)$curlError, 0, 300);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log('[FCM] send failed: token=' . $maskedToken . ' http=' . $status . ' response=' . $response);
        $meta['reason'] = 'http_' . $status;
        return false;
    }

    error_log('[FCM] send success: project=' . $projectId . ' token=' . $maskedToken . ' http=' . $status . ' response=' . $response);
    $meta['ok'] = true;
    $meta['reason'] = 'sent';
    return true;
}
