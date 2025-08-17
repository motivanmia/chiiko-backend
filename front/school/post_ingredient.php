<?php

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('POST'); 

// ---- 1) 假資料：全部寫在 PHP 裡----
$payload = [
  "ingredients_categary_id" => 1,
  "name"                    => "空心菜",
  // 單張或多張都可以，這裡示範多張
  "image"                   => ["waterspinach.png", "waterspinash-ng.png"],
  "status"                  => "0",
  "storage_method"          => "- 整株空心菜可放置於 5～10°C 的冷藏環境，建議使用報紙或廚房紙巾包裹後裝袋，避免水氣。\n- 暫不使用時，根部朝下立放可減緩失水。\n- 最佳賞味期 2 日內。",
  "content"                 => [
    [
      "goodtitle"   => "葉片鮮綠完整",
      "badtitle"    => "葉色暗沉捲曲",
      "goodcontent" => "色澤翠綠、葉面無損傷代表新鮮。",
      "badcontent"  => "枯黃或捲曲顯示水分流失與老化。"
    ],
    [
      "goodtitle"   => "莖部挺直水潤",
      "badtitle"    => "莖部軟化乾癟",
      "goodcontent" => "莖部粗壯且斷面濕潤為佳。",
      "badcontent"  => "中空或扁塌代表已失水不新鮮。"
    ],
    [
      "goodtitle"   => "無異味或斑點",
      "badtitle"    => "葉面黑斑異味",
      "goodcontent" => "氣味清新自然、無腐敗異味。",
      "badcontent"  => "出現腐敗味或黑點為變質前兆。"
    ],
  ],
];

// ---- 2) 正規化：image / content 轉成 JSON 字串存 DB ----
$toJson = function ($val) {
  if (is_array($val)) {
    return json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  if (is_string($val)) {
    // 字串就包成單元素陣列存
    return json_encode([$val], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
  return json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$ingredients_categary_id = (int)$payload['ingredients_categary_id'];
$name                    = (string)$payload['name'];
$image_json              = $toJson($payload['image']);
$status                  = (string)$payload['status'];
$storage_method          = (string)$payload['storage_method'];
$content_json            = $toJson($payload['content']);

// ---- 3) INSERT（ingredient_id 為 AUTO_INCREMENT，不帶）----
$sql = "INSERT INTO ingredients
        (ingredients_categary_id, name, image, status, storage_method, content)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
  send_json(['status'=>'fail','message'=>'SQL 準備失敗','error'=>$mysqli->error], 500);
}

$stmt->bind_param(
  "isssss",
  $ingredients_categary_id,
  $name,
  $image_json,
  $status,
  $storage_method,
  $content_json
);

if (!$stmt->execute()) {
  send_json(['status'=>'fail','message'=>'新增失敗','error'=>$stmt->error], 500);
}

$new_id = $stmt->insert_id;
$stmt->close();

// ---- 4) 取回剛新增的資料回傳（讓前端看到實際入庫結果）----
$q = $mysqli->prepare("SELECT * FROM ingredients WHERE ingredient_id = ?");
$q->bind_param("i", $new_id);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc() ?: [];
$q->close();

$mysqli->close();

// 這裡先原樣回傳 DB 內容，方便對照。
send_json([
  'status'  => 'success',
  'message' => '新增成功（PHP 內建假資料）',
  'data'    => $row,
], 201);

?>