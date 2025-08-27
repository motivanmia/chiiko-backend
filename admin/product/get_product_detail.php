<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');
  
  $sql = "SELECT 
	  p.product_id,
    p.name as product_name,
    pc.name as category_name,
    p.preview_image,
    p.unit_price,
    p.is_active,
    p.product_images,
    p.content_images,
    p.product_notes,
    p.product_info
FROM chiiko.products p
left join chiiko.product_categories pc on 
p.product_category_id = pc.product_category_id;";

  // 取得資料
  $result = db_query($mysqli, $sql);
  $data = $result->fetch_all(MYSQLI_ASSOC);
  $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

  

  send_json([
    'status' => 'success',
    'message' => '資料取得成功',
    'data' => $jsonData
  ]);
  $conn->close();
?>