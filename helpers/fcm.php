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
    array $data = []
): bool {
    if ($deviceToken === '') {
        error_log('[FCM] skipped: empty device token');
        return false;
    }

    $service = loadFcmServiceAccount();
    if (!$service) {
        error_log('[FCM] skipped: service account not configured');
        return false;
    }
    $projectId = $service['project_id'] ?? '';
    if ($projectId === '') {
        error_log('[FCM] skipped: project_id missing in service account');
        return false;
    }

    $accessToken = getFcmAccessToken();
    if (!$accessToken) {
        error_log('[FCM] skipped: access token generation failed');
        return false;
    }

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

    if ($response === false) {
        error_log('[FCM] curl failed: ' . $curlError);
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log('[FCM] send failed: http=' . $status . ' response=' . $response);
        return false;
    }

    error_log('[FCM] send success: project=' . $projectId . ' http=' . $status);
    return true;
}
