<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('GET');
  
  // SQL 查詢
  $sql = "SELECT
    p.*,
    pc.name AS category_name
  FROM products AS p
  INNER JOIN
    product_categories AS pc
  ON p.product_category_id = pc.product_category_id
  ORDER BY p.product_id ";

  // 取得資料
  $result = db_query($mysqli, $sql);
  $data = $result->fetch_all(MYSQLI_ASSOC);

  $processed_products = [];
foreach ($data as $row) {
    // 檢查 product_images 欄位是否為 JSON 字串
    $product_images = json_decode($row['product_images'], true);

    // 判斷圖片陣列是否有效且不為空
    if (is_array($product_images) && count($product_images) > 0) {
        // 如果有圖片，將預覽圖設定為陣列中的第一張圖
        $row['preview_image'] = $product_images[0];
    } else {
        // 如果沒有圖片，將預覽圖設定為空字串或預設圖
        $row['preview_image'] = ''; // 或者 'default.jpg'
    }

    // 將處理後的單一商品資料加入新的陣列
    $processed_products[] = $row;
}

// ✨ 最後回傳處理好的資料陣列 $processed_products
send_json([
    'status' => 'success',
    'message' => '資料取得成功',
    'data' => $processed_products
]);

// ✨ 修正：關閉正確的連線變數 $mysqli
$mysqli->close();
?>