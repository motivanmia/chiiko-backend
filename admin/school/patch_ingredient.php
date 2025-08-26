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

  $origin = null;
  {
    $q = $mysqli->prepare("SELECT ingredients_category_id, name FROM `ingredients` WHERE `ingredient_id` = ? LIMIT 1");
    $q->bind_param('i', $id);
    $q->execute();
    $origin = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$origin) {
      send_json(['status'=>'fail','message'=>'找不到該食材'], 404);
    }
  }

  // 正規化工具
  $toJson = function ($val) {
    if (is_array($val))  return json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($val)) return json_encode([$val], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return null; 
  };

  if (array_key_exists('name', $payload)) {
    $newName = trim((string)$payload['name']);
    if ($newName === '') {
      send_json(['status'=>'fail','message'=>'name 不可為空','field'=>'name'], 400);
    }

    $dupSql = "SELECT 1 FROM `ingredients` WHERE `name` = ? AND `ingredient_id` <> ? LIMIT 1";
    $dupStmt = $mysqli->prepare($dupSql);
    $dupStmt->bind_param("si", $newName, $id);

    $dupStmt->execute();
    $dupStmt->store_result();
    if ($dupStmt->num_rows > 0) {
      $dupStmt->close();
      send_json([
        'status'  => 'fail',
        'message' => '名稱已存在，請更換 name',
        'field'   => 'name'
      ], 409);
    }
    $dupStmt->close();
  }

  // —— 動態組 UPDATE —— 
  $fields = [];
  $types  = '';
  $params = [];

  if (array_key_exists('ingredients_category_id', $payload)) {
    $fields[] = '`ingredients_category_id` = ?';
    $types   .= 'i';
    $params[] = (int)$payload['ingredients_category_id'];
  }
  if (array_key_exists('name', $payload)) {
    $fields[] = '`name` = ?';
    $types   .= 's';
    $params[] = trim((string)$payload['name']);
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

  $sql = "UPDATE `ingredients` SET " . implode(', ', $fields) . " WHERE `ingredient_id` = ?";
  $types .= 'i';
  $params[] = $id;

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  // 回查
  $res = db_query($mysqli, "SELECT * FROM `ingredients` WHERE `ingredient_id` = " . (int)$id);
  $row = $res->fetch_assoc() ?: [];

  $mysqli->close();

  send_json([
    'status'  => 'success',
    'message' => '更新完成',
    'updated' => $affected,
    'data'    => $row,
  ], 200);

} catch (mysqli_sql_exception $e) {
  if ($e->getCode() === 1062) {
    send_json([
      'status'  => 'fail',
      'message' => '名稱已存在，請更換 name',
      'field'   => 'name'
    ], 409);
  }
  send_json([
    'status'  => 'fail',
    'message' => 'Server error',
    'error'   => $e->getMessage(),
  ], 500);
} catch (Throwable $e) {
  send_json([
    'status'  => 'fail',
    'message' => 'Server error',
    'error'   => $e->getMessage(),
  ], 500);
}
