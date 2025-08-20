<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('PATCH');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Cannot read request body');
  $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  if (!isset($payload['ingredient_id']) || !$payload['ingredient_id']) {
    throw new Exception('ingredient_id 必填');
  }
  $id = (int)$payload['ingredient_id'];

  // 正規化工具
  $toJson = function ($val) {
    if (is_array($val))  return json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($val)) return json_encode([$val], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return null;
  };

  // 動態組 UPDATE
  $fields = [];
  $types  = '';
  $params = [];

  if (array_key_exists('ingredients_categary_id', $payload)) {
    $fields[] = '`ingredients_categary_id` = ?';
    $types   .= 'i';
    $params[] = (int)$payload['ingredients_categary_id'];
  }
  if (array_key_exists('name', $payload)) {
    $fields[] = '`name` = ?';
    $types   .= 's';
    $params[] = (string)$payload['name'];
  }
  if (array_key_exists('image', $payload)) {
    $fields[] = '`image` = ?';
    $types   .= 's';
    $params[] = $toJson($payload['image']);
  }
  if (array_key_exists('status', $payload)) {
    $fields[] = '`status` = ?';
    $types   .= 's';
    $params[] = (string)$payload['status'];
  }
  if (array_key_exists('storage_method', $payload)) {
    $fields[] = '`storage_method` = ?';
    $types   .= 's';
    $params[] = (string)$payload['storage_method'];
  }
  if (array_key_exists('content', $payload)) {
    $fields[] = '`content` = ?';
    $types   .= 's';
    $params[] = $toJson($payload['content']);
  }

  if (empty($fields)) {
    send_json(['status'=>'success','message'=>'沒有任何更新','updated'=>0],200);
  }

  // 組 UPDATE SQL
  $sql = "UPDATE `ingredients` SET " . implode(', ', $fields) . " WHERE `ingredient_id` = ?";
  $types .= 'i';
  $params[] = $id;

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  // 回查更新後的資料
  $q = $mysqli->prepare("SELECT * FROM `ingredients` WHERE `ingredient_id` = ?");
  $q->bind_param("i", $id);
  $q->execute();
  $res = $q->get_result();
  $row = $res->fetch_assoc() ?: [];
  $q->close();

  $mysqli->close();

  send_json([
    'status'  => 'success',
    'message' => '更新完成',
    'updated' => $affected,
    'data'    => $row,
  ], 200);

} catch (Throwable $e) {
  send_json([
    'status'  => 'fail',
    'message' => 'Server error',
    'error'   => $e->getMessage(),
  ], 500);
}
