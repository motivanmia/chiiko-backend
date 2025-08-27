<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('POST');

  // 資料驗證
  $required_fields = ['name','product_category_id','unit_price','is_active','product_info'];
  foreach($required_fields as $field){
    if(!isset($_POST[$field]) || empty($_POST[$field]) && $_POST[$field] !== '0'){
      send_json(['error'=>"缺少必要欄位： {$field}"], 400);
    }
  }

  // 處理檔案上傳
  $preview_image_path = isset($_FILES['preview_image']) ? handleFileUpload($_FILES['preview_image'], false) : null;
  $product_image_paths = isset($_FILES['product_image']) ? handleFileUpload($_FILES['product_image'], true) : null;
  $content_image_paths = isset($_FILES['content_image']) ? handleFileUpload($_FILES['content_image'], true) : null;

  if($preview_image_path === null){
    send_json(['error'=>"預覽圖(preview_image) 上傳失敗或未提供"], 400);
  }

  // 準備資料庫資料
  $name = $_POST['name'];
  $product_category_id = intval($_POST['product_category_id']);
  $unit_price = intval($_POST['unit_price']);
  $is_active = intval($_POST['is_active']);
  $product_notes = isset($_POST['product_notes']) ? $_POST['product_notes'] : '';
  $product_info = isset($_POST['product_info']) ? $_POST['product_info'] : '';


  // 將圖片路徑陣列轉為json字串
  $product_image_json = json_encode($product_image_paths);
  $content_image_json = json_encode($content_image_paths);

  // 執行加入資料庫
  $sql = "INSERT INTO products (
        product_category_id,
        name,
        preview_image,
        unit_price,
        is_active,
        product_images,
        content_images,
        product_notes,
        product_info
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
  
  $stmt = $mysqli -> prepare($sql);

  $stmt -> bind_param(
    "ississsss",
    $product_category_id,
    $name,
    $preview_image_path,
    $unit_price,
    $is_active,
    $product_image_json,
    $content_image_json,
    $product_notes,
    $product_info
  );

  // 回傳結果
  if($stmt->execute()){
    $data_return = [
      'status' => 'success',
      'message' => '商品新增成功',
      'product_id' => $stmt->insert_id,
      'category_id' =>$product_category_id,
      'name'=>$name,
      'price' => $unit_price,
      'is_active'=>$is_active,
      'product_notes'=>$product_notes,
      'product_info'=>$product_info,
      'image' => [
        'preview_image'=>$preview_image_path,
        'porduct_image'=>$product_image_paths,
        'content_image'=>$content_image_paths,
      ]
    ];
    send_json($data_return, 201);
  }else{
    send_json(['error' => '資料庫寫入失敗: ' . $stmt->error], 500);
  }

  $stmt -> close();
  $mysqli -> close();
?>