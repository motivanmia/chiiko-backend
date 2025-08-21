<?php
  // 統一輸出 JSON
  function send_json($data, $status_code = 200) {
  // 判斷是否為完整網址或 data:/blob:，是的話就不加前綴
    $is_absolute = function ($val) {
      return is_string($val) && (
        preg_match('#^(?:https?:)?//#i', $val) ||
        str_starts_with($val, 'data:') ||
        str_starts_with($val, 'blob:')
      );
    };

    // 加上 IMG_BASE_URL
    $prefix = function ($val) use ($is_absolute) {
      if (!is_string($val) || $val === '' || $is_absolute($val)) {
        return $val;
      }
      return rtrim(IMG_BASE_URL, '/') . '/' . ltrim($val, '/');
    };

    // 嘗試把 JSON 字串轉回 PHP 值（失敗回 null）
    $try_json_decode = function ($val) {
      if (!is_string($val)) return null;
      $trim = trim($val);
      if ($trim === '' || ($trim[0] !== '[' && $trim[0] !== '{' && $trim[0] !== '"')) return null;
      $decoded = json_decode($trim, true);
      return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    };

    // 欄位白名單
    $should_prefix_key = function ($key) {
      return (bool)preg_match('/(?:^|_)(?:image|images)$/i', $key);
    };

    // 遞迴處理
    $process = function ($item) use (&$process, $prefix, $try_json_decode, $should_prefix_key) {
      if (is_array($item)) {
        $out = [];
        foreach ($item as $k => $v) {
          if ($should_prefix_key($k)) {
            // 1) 陣列：每個值都加前綴
            if (is_array($v)) {
              $out[$k] = array_map($prefix, $v);
            }
            // 2) 字串：可能是檔名或 JSON 陣列字串
            elseif (is_string($v)) {
              $maybe = $try_json_decode($v);
              if (is_array($maybe)) {
                $out[$k] = array_map($prefix, $maybe);
              } else {
                $out[$k] = $prefix($v);
              }
            } else {
              $out[$k] = $v; // 其他型別照原樣
            }
          } else {
            // 其他欄位繼續遞迴（支援巢狀結構）
            $out[$k] = $process($v);
          }
        }
        return $out;
      }
      return $item;
    };

    $data = $process($data);

    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }



  // 只允許使用 $method
  function require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        send_json(['status' => 'fail', 'message' => "只允許 {$method} 請求"], 405);
    }
  }

  // 取得 query string
  function get_int_param($name) {
    return isset($_GET[$name]) ? intval($_GET[$name]) : null;
  }

  // 處理資料庫 result
  function db_query($mysqli, $sql) {
    $result = $mysqli->query($sql);
    if (!$result) {
        send_json(['status' => 'fail', 'message' => $mysqli->error], 500);
    }
    return $result;
  }

  // 取得並解析 raw JSON
  function get_json_input() {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!is_array($input)) {
      send_json([
        'status' => 'fail',
        'message' => '請傳送正確的 JSON'
      ], 400);
    }

    return $input;
  }


  // -----處理檔案上傳&命名 單一檔案或多檔案輔助
  /**
   * 處理檔案上傳，支援單檔或多檔
   * @param array $file_info $_FILES['input_name']
   * @param bool $is_multiple 是否為多檔上傳
   * @return string|array|null 成功則回傳儲存路徑(或路徑陣列)，失敗回傳 null
   */
  function handleFileUpload($file_info, $is_multiple = false){
    // 確保資料夾存在且可寫入
    $upload_dir = '../../uploads/';
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
        return  $new_filename;
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
          $save_paths[] =  $new_filename;
        }
      }
      return $save_paths;
    }
  };

  function checkUserLoggedIn() {
    // 檢查 Session 中是否有 'is_logged_in' 且為 true
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        // 如果已登入，回傳使用者資料
        return [
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name']
        ];
    }
    
    // 如果未登入，回傳 false
    return false;
  }
?>
