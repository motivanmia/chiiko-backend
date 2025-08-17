<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');


  $recipe_id = get_int_param('recipe_id');

  
  if (!$recipe_id) {
    send_json([
        'status' => 'error',
        'message' => '缺少必要的 recipe_id 參數'
    ], 400); 
    exit;
  }
  
  
  // 查詢 recipe 資料表，並透過 JOIN 順便把作者的名字也抓出來
  // 使用 r 和 u 作為資料表的別名，可以讓 SQL 語句更簡潔
  $sql = "SELECT 
      r.recipe_id,
      r.user_id,
      u.name AS author_name, -- 從 users 表抓作者名字，並重新命名為 author_name
      r.name,
      r.content,
      r.serving,
      r.image, 
      r.cooked_time,
      r.status,
      r.created_at
    FROM recipe AS r
    JOIN users AS u ON r.user_id = u.user_id
    WHERE r.recipe_id = {$recipe_id} -- 關鍵！只查詢 URL 參數指定的那一筆食譜
  ";

  //我們預期只會找到 "一筆" 資料，而不是像購物車那樣是個列表
  $result = db_query($mysqli, $sql);
  
  // 使用 fetch_assoc() 來取得單一筆結果
  $data = $result->fetch_assoc(); 

  
  send_json([
    'status' => 'success',
    'message' => '食譜資料取得成功',
    'data' => $data 
  ]);
?>