<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('POST');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 讓 mysqli 丟例外，好用 try/catch 接
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
  $raw = file_get_contents('php://input');
  if ($raw === false) throw new Exception('Cannot read request body');
  $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  // 正規化工具
  $toJson = function ($val) {
    if (is_array($val))  return json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($val)) return json_encode([$val], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return json_encode([],   JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  };

  // 讀欄位（※名稱要和前端一致）
  $ingredients_category_id = (int)($payload['ingredients_category_id'] ?? 0);
  $name                    = (string)($payload['name'] ?? '');
  $image_json              = $toJson($payload['image'] ?? []);
  $status                  = (string)($payload['status'] ?? '0');
  $storage_method          = (string)($payload['storage_method'] ?? '');
  $content_json            = $toJson($payload['content'] ?? []);

  // 最基本的驗證
  if ($ingredients_category_id <= 0) throw new Exception('ingredients_category_id 無效');
  if ($name === '') throw new Exception('name 必填');

  // INSERT（用反引號避免欄位名踩雷）
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
  $q = $mysqli->prepare("SELECT * FROM `ingredients` WHERE `ingredient_id` = ?");
  $q->bind_param("i", $new_id);
  $q->execute();
  $res = $q->get_result();
  $row = $res->fetch_assoc() ?: [];
  $q->close();

  $mysqli->close();

  send_json([
    'status'  => 'success',
    'message' => '新增成功',
    'data'    => $row,
  ], 201);

} catch (Throwable $e) {
  // 把真正錯誤回給前端（開發期）
  send_json([
    'status'  => 'fail',
    'message' => 'Server error',
    'error'   => $e->getMessage(),   // 這裡會包含 SQL/JSON 解析錯誤等
  ], 500);
}
