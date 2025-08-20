<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';     // 產出 $mysqli
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';


require_method('DELETE'); // 只允許 DELETE
header('Content-Type: application/json; charset=utf-8');

// 1) 取得 id（querystring 或 body 皆可）
$ingredientId = 0;
if (isset($_GET['ingredient_id'])) $ingredientId = (int)$_GET['ingredient_id'];
elseif (isset($_GET['id']))        $ingredientId = (int)$_GET['id'];
elseif (isset($GLOBALS['__BODY_JSON__']['ingredient_id'])) $ingredientId = (int)$GLOBALS['__BODY_JSON__']['ingredient_id'];
elseif (isset($GLOBALS['__BODY_JSON__']['id']))            $ingredientId = (int)$GLOBALS['__BODY_JSON__']['id'];

if ($ingredientId <= 0) {
  send_json(['status'=>'fail','message'=>'ingredient_id 必填'], 400);
}

// 2) 撈出 image 準備刪檔
$stmt = $mysqli->prepare("SELECT `image` FROM `ingredients` WHERE `ingredient_id` = ?");
$stmt->bind_param("i", $ingredientId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
  send_json(['status'=>'success','deleted'=>0,'message'=>'Not found'], 200);
}

$images = [];
if (!empty($row['image'])) {
  $decoded = json_decode($row['image'], true);
  if (is_array($decoded)) $images = $decoded;
}

// 3) 刪除（交易）
$mysqli->begin_transaction();

$stmt = $mysqli->prepare("DELETE FROM `ingredients` WHERE `ingredient_id` = ?");
$stmt->bind_param("i", $ingredientId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

// 4) 刪實體檔案
if ($affected > 0 && !empty($images)) {
  // uploads 目錄
  $uploadDir = realpath(__DIR__ . '/../../uploads');
  if ($uploadDir !== false) {
    $uploadDir = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    foreach ($images as $name) {
      if (!is_string($name) || $name === '') continue;
      $path = $uploadDir . basename($name); // 防路徑跳脫
      if (is_file($path)) @unlink($path);
    }
  }
}

$mysqli->commit();

send_json(['status'=>'success','deleted'=>$affected,'message'=>'OK'], 200);
