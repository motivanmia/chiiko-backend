<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');

$recipe_id = get_int_param('recipe_id');

if (!$recipe_id) {
  send_json(['status' => 'fail', 'message' => '缺少必要參數 recipe_id'], 400);
}

// 用 LEFT JOIN，ingredient_id 可能為 NULL（只手填名稱）
$sql = "
  SELECT
  ii.ingredient_item_id,
  ii.recipe_id,
  ii.ingredient_id,
  COALESCE(i.name, ii.name) AS name,   
  ii.name,           
  ii.serving,
  i.name AS ingredient_name,
  i.ingredients_category_id,
  i.image AS image,
  i.status,
  i.storage_method,
  i.content
  FROM ingredient_item AS ii
  LEFT JOIN ingredients AS i
  ON i.ingredient_id = ii.ingredient_id
  WHERE ii.recipe_id = {$recipe_id}
  ORDER BY ii.ingredient_item_id ASC
";

$result = db_query($mysqli, $sql);
$rows = $result->fetch_all(MYSQLI_ASSOC);

// 整理輸出：保留 item 層（一定有），若 ingredient_id 存在，再附上完整 ingredient 物件
$data = array_map(function ($r) {
  return [
    'ingredient_item_id' => (int)$r['ingredient_item_id'],
    'recipe_id'          => (int)$r['recipe_id'],
    'ingredient_id'      => $r['ingredient_id'] !== null ? (int)$r['ingredient_id'] : null,
    'name'               => $r['name'],      
    'serving'            => $r['serving'],
    'ingredient'         => $r['ingredient_id'] !== null ? [
      'ingredient_id'            => (int)$r['ingredient_id'],
      'name'                     => $r['ingredient_name'] ?? null,  
      'ingredients_category_id'  => isset($r['ingredients_category_id']) ? (int)$r['ingredients_category_id'] : null,
      'image'                    => $r['image'],
      'status'                   => isset($r['status']) ? (int)$r['status'] : null,
      'storage_method'           => $r['storage_method'],
      'content'                  => $r['content'],
    ] : null,
  ];
}, $rows);

send_json([
  'status'  => 'success',
  'message' => '食譜食材取得成功',
  'data'    => $data,
], 200);
