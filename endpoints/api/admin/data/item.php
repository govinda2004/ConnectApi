<?php

require_once __DIR__ . '/_data_common.php';

adminRequireAuth();
$db = getDB();

$resource = (string)($_GET['resource'] ?? '');
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) jsonError('Invalid id');

$cfg = adminDataResourceConfig($resource);
if (!$cfg) jsonError('Unsupported resource', 404);
adminDataEnsureTable($db, $cfg);

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $stmt = $db->prepare("DELETE FROM {$cfg['table']} WHERE {$cfg['id']} = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) jsonError('Record not found', 404);
    jsonSuccess(['id' => $id], 'Record deleted');
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    unset($input[$cfg['id']]);
    if (empty($input)) jsonError('No fields to update');

    $sets = [];
    $vals = [];
    foreach ($input as $k => $v) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', (string)$k)) continue;
        $sets[] = "`{$k}` = ?";
        if (is_array($v) || is_object($v)) $v = json_encode($v);
        $vals[] = $v;
    }
    if (empty($sets)) jsonError('No valid fields to update');
    $vals[] = $id;

    $sql = "UPDATE {$cfg['table']} SET " . implode(', ', $sets) . " WHERE {$cfg['id']} = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($vals);
    if ($stmt->rowCount() === 0) {
        $c = $db->prepare("SELECT {$cfg['id']} FROM {$cfg['table']} WHERE {$cfg['id']} = ? LIMIT 1");
        $c->execute([$id]);
        if (!$c->fetch()) jsonError('Record not found', 404);
    }
    jsonSuccess(['id' => $id], 'Record updated');
}

jsonError('Method not allowed', 405);
