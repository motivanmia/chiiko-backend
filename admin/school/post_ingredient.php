<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('POST');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Cannot read request body');
  $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $toJson = function ($val) {
    if (is_array($val))  return json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($val)) return json_encode([$val], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return json_encode([],   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  };

  $ingredients_category_id = (int)($payload['ingredients_category_id'] ?? 0);
  $name                    = trim((string)($payload['name'] ?? '')); // ← trim
  $image_json              = $toJson($payload['image'] ?? []);
  $status                  = (string)($payload['status'] ?? '0');
  $storage_method          = (string)($payload['storage_method'] ?? '');
  $content_json            = $toJson($payload['content'] ?? []);

  if ($ingredients_category_id <= 0) throw new Exception('ingredients_category_id 無效');
  if ($name === '') throw new Exception('name 必填');

  $dupSql = "SELECT 1 FROM `ingredients` WHERE `name` = ? LIMIT 1";
  $dupStmt = $mysqli->prepare($dupSql);
  $dupStmt->bind_param("s", $name);
  $dupStmt->execute();
  $dupStmt->store_result();
  if ($dupStmt->num_rows > 0) {
    $dupStmt->close();
    send_json([
      'status'  => 'fail',
      'message' => '名稱已存在，請更換 name',
      'field'   => 'name'
    ], 409); // Conflict
  }
  $dupStmt->close();

  // ---- INSERT
  $sql = "INSERT INTO `ingredients`
          (`ingredients_category_id`, `name`, `image`, `status`, `storage_method`, `content`)
          VALUES (?, ?, ?, ?, ?, ?)";
  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param("isssss",
    $ingredients_category_id, $name, $image_json, $status, $storage_method, $content_json
  );
  $stmt->execute();
  $new_id = $stmt->insert_id;
  $stmt->close();

  // 回查
  $selectSql = "SELECT * FROM `ingredients` WHERE `ingredient_id` = " . (int)$new_id;
  $res = db_query($mysqli, $selectSql);
  $row = $res->fetch_assoc() ?: [];

  $mysqli->close();

  send_json([
    'status'  => 'success',
    'message' => '新增成功',
    'data'    => $row,
  ], 201);

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
