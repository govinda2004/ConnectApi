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
$user = trim((string)($_GET['user'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

// Full-table activity mix from notifications.
$mix = [];
try {
    $where = [];
    $params = [];
    if ($user !== '') {
        $where[] = 'EXISTS (SELECT 1 FROM users ux WHERE ux.id = notifications.actor_id AND (ux.name LIKE ? OR ux.email LIKE ?))';
        $params[] = "%{$user}%";
        $params[] = "%{$user}%";
    }
    if ($action !== '') {
        $where[] = '(type LIKE ? OR message LIKE ?)';
        $params[] = "%{$action}%";
        $params[] = "%{$action}%";
    }
    if ($from !== '') {
        $where[] = 'DATE(created_at) >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $where[] = 'DATE(created_at) <= ?';
        $params[] = $to;
    }
    $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
    $sql = "SELECT type, COUNT(*) AS total FROM notifications {$whereSql} GROUP BY type ORDER BY total DESC";
    $stmt = $db->prepare($sql);
    $i = 1;
    foreach ($params as $p) $stmt->bindValue($i++, $p, PDO::PARAM_STR);
    $stmt->execute();
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
