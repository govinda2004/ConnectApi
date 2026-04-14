<?php

require_once __DIR__ . '/../_common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Method not allowed', 405);
}

adminRequireAuth();
$db = getDB();

function tableHasCreatedAt(PDO $db, string $table): bool
{
    try {
        $stmt = $db->query("SHOW COLUMNS FROM {$table} LIKE 'created_at'");
        return $stmt !== false && $stmt->fetch() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

$days = max(7, min(30, (int)($_GET['days'] ?? 14)));

// Full-table activity mix from notifications.
$mix = [];
try {
    $stmt = $db->query("SELECT type, COUNT(*) AS total FROM notifications GROUP BY type ORDER BY total DESC");
    $mix = $stmt->fetchAll();
} catch (Throwable $e) {
    $mix = [];
}

$entities = ['users', 'posts', 'jobs', 'messages', 'notifications'];
$hasCreatedAt = [];
foreach ($entities as $t) $hasCreatedAt[$t] = tableHasCreatedAt($db, $t);

$points = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', strtotime("-{$i} day"));
    $row = ['date' => $date];
    $total = 0;
    foreach ($entities as $t) {
        $count = 0;
        if ($hasCreatedAt[$t]) {
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM {$t} WHERE DATE(created_at) = ?");
                $stmt->execute([$date]);
                $count = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $count = 0;
            }
        }
        $row[$t] = $count;
        $total += $count;
    }
    $row['total'] = $total;
    $points[] = $row;
}

jsonSuccess([
    'days' => $days,
    'growth_points' => $points,
    'activity_mix' => $mix,
], 'Dashboard analytics fetched');
