<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('PATCH');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
    $sql_origin = "SELECT ingredients_category_id, name FROM `ingredients` WHERE `ingredient_id` = {$id} LIMIT 1";
    $res_origin = $mysqli->query($sql_origin);
    if ($res_origin === false) throw new Exception('查詢原始資料失敗：' . $mysqli->error, 500);
    $origin = $res_origin->fetch_assoc();
    $res_origin->free();
    if (!$origin) {
      send_json(['status'=>'fail','message'=>'找不到該食材'], 404);
    }
  }

  // 正規化工具
  $toJson = function ($val) {
    if (is_array($val)) return json_encode($val, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (is_string($val)) return json_encode([$val], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    return 'NULL'; // 返回 'NULL' 字串而非 null 值，用於 SQL
  };

  // 檢查名稱是否重複
  if (array_key_exists('name', $payload)) {
    $newName = trim((string)$payload['name']);
    if ($newName === '') {
      send_json(['status'=>'fail','message'=>'name 不可為空','field'=>'name'], 400);
    }

    $safeName = $mysqli->real_escape_string($newName);
    $dupSql = "SELECT 1 FROM `ingredients` WHERE `name` = '{$safeName}' AND `ingredient_id` <> {$id} LIMIT 1";
    $dupRes = $mysqli->query($dupSql);
    if ($dupRes === false) throw new Exception('查詢重複名稱失敗：' . $mysqli->error, 500);

    if ($dupRes->num_rows > 0) {
      $dupRes->free();
      send_json([
        'status'  => 'fail',
        'message' => '名稱已存在，請更換 name',
        'field'   => 'name'
      ], 409);
    }
    $dupRes->free();
  }

  // —— 動態組 UPDATE —— 
  $updates = [];
  if (array_key_exists('ingredients_category_id', $payload)) {
    $categoryId = (int)$payload['ingredients_category_id'];
    $updates[] = "`ingredients_category_id` = {$categoryId}";
  }
  if (array_key_exists('name', $payload)) {
    $safeName = $mysqli->real_escape_string(trim((string)$payload['name']));
    $updates[] = "`name` = '{$safeName}'";
  }
  if (array_key_exists('image', $payload)) {
    $imageJson = $toJson($payload['image']);
    $updates[] = "`image` = '{$imageJson}'";
  }
  if (array_key_exists('status', $payload)) {
    $safeStatus = $mysqli->real_escape_string((string)$payload['status']);
    $updates[] = "`status` = '{$safeStatus}'";
  }
  if (array_key_exists('storage_method', $payload)) {
    $safeStorage = $mysqli->real_escape_string((string)$payload['storage_method']);
    $updates[] = "`storage_method` = '{$safeStorage}'";
  }
  if (array_key_exists('content', $payload)) {
    $contentJson = $toJson($payload['content']);
    $updates[] = "`content` = '{$contentJson}'";
  }

  if (empty($updates)) {
    send_json(['status'=>'success','message'=>'沒有任何更新','updated'=>0],200);
  }

  $sql = "UPDATE `ingredients` SET " . implode(', ', $updates) . " WHERE `ingredient_id` = {$id}";

  $mysqli->begin_transaction();
  $res = $mysqli->query($sql);
  if ($res === false) {
    throw new Exception('更新失敗：' . $mysqli->error, 500);
  }
  $affected = $mysqli->affected_rows;

  // 回查
  $res = $mysqli->query("SELECT * FROM `ingredients` WHERE `ingredient_id` = {$id}");
  if ($res === false) throw new Exception('回查失敗：' . $mysqli->error, 500);
  $row = $res->fetch_assoc() ?: [];
  $res->free();

  $mysqli->commit();

  send_json([
    'status'  => 'success',
    'message' => '更新完成',
    'updated' => $affected,
    'data'    => $row,
  ], 200);

} catch (Throwable $e) {
  if (isset($mysqli)) $mysqli->rollback();
  $code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
  $message = $e->getMessage();
  // 特殊處理唯一鍵重複錯誤
  if (strpos($message, 'Duplicate entry') !== false && strpos($message, 'for key') !== false) {
    $message = '名稱已存在，請更換 name';
    $code = 409;
  }
  
  send_json([
    'status'  => 'fail',
    'message' => $message,
    'error'   => $e->getMessage(),
  ], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}
