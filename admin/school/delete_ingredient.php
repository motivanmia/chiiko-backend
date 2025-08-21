<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';     
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('DELETE'); // 只允許 DELETE
header('Content-Type: application/json; charset=utf-8');

/**
 * 1) 取得 id（querystring 或 body 皆可）
 */
$ingredientId = 0;
if (isset($_GET['ingredient_id']))                     $ingredientId = (int)$_GET['ingredient_id'];
elseif (isset($_GET['id']))                            $ingredientId = (int)$_GET['id'];
elseif (isset($GLOBALS['__BODY_JSON__']['ingredient_id'])) $ingredientId = (int)$GLOBALS['__BODY_JSON__']['ingredient_id'];
elseif (isset($GLOBALS['__BODY_JSON__']['id']))            $ingredientId = (int)$GLOBALS['__BODY_JSON__']['id'];

if ($ingredientId <= 0) {
  send_json(['status' => 'fail', 'message' => 'ingredient_id 必填'], 400);
}

/**
 * 2) 撈出 image 準備刪檔
 */
$sql  = "SELECT `image` FROM `ingredients` WHERE `ingredient_id` = " . (int)$ingredientId;
$res  = db_query($mysqli, $sql);
$row  = $res->fetch_assoc();

if (!$row) {
  // 找不到資料：語意上回 200，並宣告未刪任何資料
  send_json(['status' => 'success', 'deleted' => 0, 'message' => 'Not found'], 200);
}

$images = [];
if (!empty($row['image'])) {
  $decoded = json_decode($row['image'], true);
  if (is_array($decoded)) $images = $decoded;
}

/**
 * 3) 刪除（交易） — 刪 DB 成功才去刪實體檔
 */
$mysqli->begin_transaction();

$delSql = "DELETE FROM `ingredients` WHERE `ingredient_id` = " . (int)$ingredientId;
db_query($mysqli, $delSql);               // 失敗會直接結束並輸出錯誤
$affected = $mysqli->affected_rows;

/**
 * 4) 刪實體檔案（僅在 DB 有成功刪除時）
 */
if ($affected > 0 && !empty($images)) {
  // uploads 目錄（使用 realpath 防跳脫）
  $uploadDir = realpath(__DIR__ . '/../../uploads');
  if ($uploadDir !== false) {
    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    foreach ($images as $name) {
      if (!is_string($name) || $name === '') continue;

      // 僅允許檔名，不接受路徑（basename 可避免路徑跳脫）
      $path = $uploadDir . basename($name);

      if (is_file($path)) {
        @unlink($path); // 靜默失敗，避免影響交易提交
      }
    }
  }
}

$mysqli->commit();

//5) 回應
send_json(['status' => 'success', 'deleted' => $affected, 'message' => 'OK'], 200);
