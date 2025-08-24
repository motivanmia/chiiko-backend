<?php

//更新（修改）食譜
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/functions.php';

try {
  require_method('POST');
  $data = get_json_input();

  $tidy = fn($s) => is_string($s) ? trim($s) : $s;

  $recipe_id = isset($data['recipe_id']) && is_numeric($data['recipe_id']) ? (int)$data['recipe_id'] : null;
  if (!$recipe_id) throw new Exception('缺少 recipe_id', 400);

  // 先查目前資料（用來判斷舊圖/狀態）
  $rs = $mysqli->prepare("SELECT status, image FROM recipe WHERE recipe_id = ?");
  $rs->bind_param('i', $recipe_id);
  $rs->execute();
  $cur = $rs->get_result()->fetch_assoc();
  $rs->close();
  if (!$cur) throw new Exception('找不到食譜', 404);

  // 允許更新的欄位
  $fields = [
    'user_id'            => 'i',
    'manage_id'          => 'i',   // 注意不是 manager_id
    'recipe_category_id' => 'i',
    'name'               => 's',
    'content'            => 's',
    'serving'            => 's',
    'image'              => 's',
    'cooked_time'        => 's',
    'status'             => 'i',
    'tag'                => 's',
  ];

  // 蒐集有效欄位
  $set = [];
  $types = '';
  $params = [];

  foreach ($fields as $k => $t) {
    if (array_key_exists($k, $data)) {
      $val = $tidy($data[$k]);
      $set[] = "`$k` = ?";
      $types .= $t;
      $params[] = $val;
    }
  }

  if (empty($set)) throw new Exception('沒有可更新的欄位', 400);

  // ---- 驗證（防擋）----
  // 判斷更新後狀態：若沒帶 status 就沿用舊值
  $nextStatus = array_key_exists('status', $data) ? (int)$data['status'] : (int)$cur['status'];

  // 若要上線/送審（0=待審核、1=上架）就強制檢查欄位
  if ($nextStatus === 0 || $nextStatus === 1) {
    $name        = array_key_exists('name',        $data) ? $tidy($data['name'])        : null;
    $content     = array_key_exists('content',     $data) ? $tidy($data['content'])     : null;
    $tag         = array_key_exists('tag',         $data) ? $tidy($data['tag'])         : null;
    $cooked_time = array_key_exists('cooked_time', $data) ? $tidy($data['cooked_time']) : null;
    $serving     = array_key_exists('serving',     $data) ? $tidy($data['serving'])     : null;
    $image       = array_key_exists('image',       $data) ? $tidy($data['image'])       : $cur['image'];

    // 若前端只改 status（審核流程），不會帶上述欄位，就從資料庫舊值補齊拿來驗證
    if ($name === null || $content === null || $tag === null || $cooked_time === null || $serving === null) {
      $q = $mysqli->prepare("SELECT name, content, tag, cooked_time, serving FROM recipe WHERE recipe_id = ?");
      $q->bind_param('i', $recipe_id);
      $q->execute();
      $row = $q->get_result()->fetch_assoc();
      $q->close();
      if ($name        === null) $name        = $row['name']        ?? '';
      if ($content     === null) $content     = $row['content']     ?? '';
      if ($tag         === null) $tag         = $row['tag']         ?? '';
      if ($cooked_time === null) $cooked_time = $row['cooked_time'] ?? '';
      if ($serving     === null) $serving     = $row['serving']     ?? '';
    }

    // 具體檢查
    $errors = [];
    if ($name === '' || mb_strlen($name) > 15)         $errors[] = '標題必填且 ≤ 15 字';
    if ($content === '' || mb_strlen($content) > 40)   $errors[] = '內文必填且 ≤ 40 字';
    if ($tag === '' || strpos($tag, '#') === false)    $errors[] = '至少一個 TAG，如 #蛋#家常';
    $allowTimes = ['5~10','10~15','15~30','30~60','60~120','120~180','180+'];
    if (!in_array($cooked_time, $allowTimes, true))    $errors[] = '烹煮時間不合法';
    $allowServings = ['1~2','3~4','5~6','7~8','9~10'];
    if (!in_array($serving, $allowServings, true))     $errors[] = '料理份數不合法';
    if (!$image)                                       $errors[] = '請上傳圖片';

    if (!empty($errors)) throw new Exception('欄位驗證失敗：' . implode('；', $errors), 400);
  }
  // ---- 驗證結束 ----

  $sql = "UPDATE recipe SET " . implode(', ', $set) . " WHERE recipe_id = ?";
  $types .= 'i';
  $params[] = $recipe_id;

  $stmt = $mysqli->prepare($sql);
  $stmt->bind_param($types, ...$params);
  $stmt->execute();
  if ($stmt->errno) throw new Exception('資料更新失敗：' . $stmt->error, 500);
  $stmt->close();

  send_json(['status'=>'success','message'=>'食譜已更新']);

} catch (Throwable $e) {
  $code = $e->getCode() ?: 500;
  $code = ($code>=400 && $code<600) ? $code : 500;
  send_json(['status'=>'fail','message'=>$e->getMessage()], $code);
} finally {
  if (isset($mysqli)) $mysqli->close();
}