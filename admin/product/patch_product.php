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
  // 讀取 request body
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Cannot read request body');
  $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
  // 檢查必填 product_id
  if (!isset($payload['product_id']) || !$payload['product_id']) {
    throw new Exception('product_id 必填');
  }
  $id = (int)$payload['product_id'];

  // 準備動態 UPDATE
  $fields = [];
  $types  = '';
  $params = [];

  if (array_key_exists('product_category_id', $payload)) {
    $fields[] = '`product_category_id` = ?';
    $types   .= 'i';
    $params[] = (int)$payload['product_category_id'];
  }

  if (array_key_exists('name', $payload)) {
    $fields[] = '`name` = ?';
    $types   .= 's';
    $params[] = (string)$payload['name'];
  }

  if (array_key_exists('unit_price', $payload)) {
    $fields[] = '`unit_price` = ?';
    $types   .= 'd'; // double / decimal
    $params[] = (float)$payload['unit_price'];
  }

  if (array_key_exists('is_active', $payload)) {
    $fields[] = '`is_active` = ?';
    $types   .= 'i'; // boolean/int
    $params[] = (int)$payload['is_active'];
  }

  if (array_key_exists('preview_image', $payload)) {
    $fields[] = '`preview_image` = ?';
    $types   .= 's';
    $params[] = (string)$payload['preview_image'];
  }

  if (array_key_exists('product_notes', $payload)) {
    $fields[] = '`product_notes` = ?';
    $types   .= 's';
    $params[] = (string)$payload['product_notes'];
  }

  if (array_key_exists('product_info', $payload)) {
    $fields[] = '`product_info` = ?';
    $types   .= 's';
    $params[] = (string)$payload['product_info'];
  }

  if (array_key_exists('product_images', $payload)) {
    $fields[] = '`product_images` = ?';
    $types   .= 's';
    $params[] = json_encode($payload['product_images'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }

  if (array_key_exists('content_images', $payload)) {
    $fields[] = '`content_images` = ?';
    $types   .= 's';
    $params[] = json_encode($payload['content_images'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }

  if (empty($fields)) {
    send_json(['status'=>'success','message'=>'沒有任何更新','updated'=>0],200);
  }

  // 組 UPDATE SQL
  $sql = "UPDATE `products` SET " . implode(', ', $fields) . " WHERE `product_id` = ?";
  $types .= 'i';
  $params[] = $id;

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $affected = $stmt->affected_rows;
  $stmt->close();

  // 回查更新後的資料
  $selectSql = "SELECT * FROM `products` WHERE `product_id` = " . (int)$id;
  $res = db_query($mysqli, $selectSql);
  $row = $res->fetch_assoc() ?: [];

  $mysqli->close();

  send_json([
    'status'  => 'success',
    'message' => '產品更新完成',
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
