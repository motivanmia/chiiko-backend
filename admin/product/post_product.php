<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('POST');
  
  // -----處理檔案上傳 單一檔案或多檔案輔助
  /**
   * 處理檔案上傳，支援單檔或多檔
   * @param array $file_info $_FILES['input_name']
   * @param bool $is_multiple 是否為多檔上傳
   * @return string|array|null 成功則回傳儲存路徑(或路徑陣列)，失敗回傳 null
   */
  function handleFileUpload($file_info, $is_multiple = false){
    // 確保資料夾存在且可寫入
    $upload_dir = './uploads/';
    if(!is_dir($upload_dir)){
      mkdir($upload_dir,0755,true);
    }

    // 允許上傳的檔案副檔名
    $allowed_types=['jpg','jpeg','png','gif'];

    if(!$is_multiple){
      // 處理單一檔案
      if($file_info['error'] !== UPLOAD_ERR_OK) return null;

      // 獲得檔案副檔名
      $extension = strtolower(pathinfo($file_info['name'] ,PATHINFO_EXTENSION));

      // 檢查是否為允許的附檔名 不符則回傳null 中斷執行
      if(!in_array($extension,$allowed_types)){
        return null;
      }

      // 產生新檔名避免檔名衝突
      $new_filename = uniqid('product_') . time() . '.' . $extension;

      // 設定路徑
      $target_path = $upload_dir . $new_filename;

      if(move_uploaded_file($file_info['tmp_name'],$target_path)){
        return './uploads/' . $new_filename;
      }
      return null;
    }else{
      // 處理多檔
      $save_paths = [];
      $file_count = count($file_info['name']);

      for($i = 0; $i<$file_count; $i++){
        if($file_info['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        $extension = strtolower(pathinfo($file_info['name'][$i] ,PATHINFO_EXTENSION));

        // 如果檔案格式不符就跳過 處理下一個檔案
        if(!in_array($extension,$allowed_types)){
          continue;
        }

        $new_filename = uniqid('product_') . time() . '_' . $i . '.' . $extension;
        $target_path = $upload_dir . $new_filename;

        if(move_uploaded_file($file_info['tmp_name'][$i],$target_path)){
          $save_paths[] = './uploads/' . $new_filename;
        }
      }
      return $save_paths;
    }
  };

  // -----主要邏輯
  // 資料驗證
  $required_fields = ['name','product_category_id','unit_price','is_active'];
  foreach($required_fields as $field){
    if(!isset($_POST[$field]) || empty($_POST[$field]) && $_POST[$field] !== '0'){
      http_response_code(400);
      echo json_encode(['error'=>"缺少必要欄位： {$field}"]);
      exit;
    }
  }

  // 處理檔案上傳
  $preview_image_path = isset($_FILES['preview_image']) ? handleFileUpload($_FILES['preview_image'], false) : null;
  $product_image_paths = isset($_FILES['product_image']) ? handleFileUpload($_FILES['product_image'], true) : null;
  $content_image_paths = isset($_FILES['content_image']) ? handleFileUpload($_FILES['content_image'], true) : null;

  if($preview_image_path === null){
    http_response_code(400);
    echo json_encode(['error'=>"預覽圖(preview_image) 上傳失敗或未提供"]);
    exit();
  }

  // 準備資料庫資料
  $name = $_POST['name'];
  $product_category_id = intval($_POST['product_category_id']);
  $unit_price = intval($_POST['unit_price']);
  $is_active = intval($_POST['is_active']);
  $product_notes = isset($_POST['product_notes']) ? $_POST['product_notes'] : '';

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
        product_notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
  
  $stmt = $mysqli -> prepare($sql);

  $stmt -> bind_param(
    "ississss",
    $product_category_id,
    $name,
    $preview_image_path,
    $unit_price,
    $is_active,
    $product_image_json,
    $content_image_json,
    $product_notes
  );

  // 回傳結果
  if($stmt->execute()){
    http_response_code(201);
    send_json([
    'status' => 'success',
    'message' => '商品新增成功',
    'product_id' => $stmt->insert_id
  ]);
  }else{
    http_response_code(500);
    echo json_encode(['error' => '資料庫寫入失敗: ' . $stmt->error]);
  }

  $stmt -> close();
  $mysql -> close();
?>