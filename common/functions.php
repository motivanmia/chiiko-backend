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

    // write_log("data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $data = $process($data);
    // write_log("before data: ". $data);
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
    $allowed_extensions=['jpg','jpeg','png','gif', 'webp'];

    if(!$is_multiple){
      // 處理單一檔案
      if($file_info['error'] !== UPLOAD_ERR_OK) return null;

      // 獲得檔案副檔名
      $extension = strtolower(pathinfo($file_info['name'] ,PATHINFO_EXTENSION));

      // 如果副檔名不在允許列表，則回傳 null
      if(!in_array($extension, $allowed_extensions)){
        return null;
      }

      // 產生新檔名避免檔名衝突
      $new_filename = uniqid('file_') . time() . '.' . $extension;

      // 設定路徑
      $target_path = $upload_dir . $new_filename;

      if(move_uploaded_file($file_info['tmp_name'],$target_path)){
        return  $new_filename;
      }
      return null;
    }else{
      // 處理多檔
      $save_names = [];
      $file_count = count($file_info['name']);

      for($i = 0; $i<$file_count; $i++){
        // 檢查單個檔案的錯誤
        if($file_info['error'][$i] !== UPLOAD_ERR_OK) continue;
        
        // 獲取檔案副檔名
        $extension = strtolower(pathinfo($file_info['name'][$i], PATHINFO_EXTENSION));

        // 檢查是否為允許的檔案類型
        if(!in_array($extension, $allowed_extensions)){
          continue;
        }

        // 產生新檔名
        $new_filename = uniqid('file_') . time() . '_' . $i . '.' . $extension;
        $target_path = $upload_dir . $new_filename;

        if(move_uploaded_file($file_info['tmp_name'][$i], $target_path)){
          $save_names[] = $new_filename;
        }
      }
      return $save_names;
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
  function write_log($message) {
    $logDir = 'C:/logs';           // 日誌資料夾
    $datetime = date('Y-m-d-H');
    $logFile = $logDir . '/'. $datetime .'.log'; // 日誌檔案

    // 如果資料夾不存在就建立
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // 時間戳記
    $time = date('Y-m-d H:i:s');

    // 將訊息寫入檔案
    file_put_contents($logFile, "[$time] $message" . PHP_EOL, FILE_APPEND);
  }

  /**
 * 建立一筆通知
 * @param mysqli $db
 * @param array $args [
 *   'receiver_id' => (int) 必填,
 *   'type'        => (int) 必填,
 *   'title'       => (string) 必填,   // 會包進 content JSON
 *   'content'     => (string) 必填,   // 會包進 content JSON
 *   'order_id'    => (int|null) 選填,
 *   'recipe_id'   => (int|null) 選填,
 *   'comment_id'  => (int|null) 選填,
 * ]
 * @return int 新增的 notification_id
 * @throws Exception on error
 */
  function create_notification(mysqli $db, array $args): int {
    $receiver_id = (int)($args['receiver_id'] ?? 0);
    $type        = (int)($args['type'] ?? 0);
    $title       = trim((string)($args['title'] ?? ''));
    $content     = trim((string)($args['content'] ?? ''));
    $order_id    = array_key_exists('order_id',   $args) ? (int)$args['order_id']   : null;
    $recipe_id   = array_key_exists('recipe_id',  $args) ? (int)$args['recipe_id']  : null;
    $comment_id  = array_key_exists('comment_id', $args) ? (int)$args['comment_id'] : null;

    if ($receiver_id <= 0 || $type === 0 || $title === '' || $content === '') {
      throw new Exception("create_notification: 缺少必要參數");
    }

    // content 欄位是 VARCHAR(1000) 
    $title   = mb_substr($title, 0, 100);
    $content = mb_substr($content, 0, 1000);
    $content_json = json_encode(['title' => $title, 'content' => $content], JSON_UNESCAPED_UNICODE);

    $sql = "INSERT INTO notification
              (receiver_id, comment_id, recipe_id, order_id, type, content, status, created_at)
            VALUES
              (?, ?, ?, ?, ?, ?, 0, NOW())";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
      throw new Exception("create_notification: prepare failed - " . $db->error);
    }
    $stmt->bind_param('iiiiss', $receiver_id, $comment_id, $recipe_id, $order_id, $type, $content_json);
    if (!$stmt->execute()) {
      $err = $stmt->error;
      $stmt->close();
      throw new Exception("create_notification: execute failed - " . $err);
    }
    $id = $stmt->insert_id;
    $stmt->close();
    return $id;
  }

  /**
   * 依「訂單狀態轉移」發通知
   * 0->1 出貨(type=20) / 1->2 完成(type=21) / 0->3 取消(type=22)
   */
  function notify_order_on_status_change(mysqli $db, int $old, int $new, int $order_id, int $user_id): void {
    if ($old === 0 && $new === 1) {
      create_notification($db, [
        'receiver_id' => $user_id,
        'order_id'    => $order_id,
        'type'        => 20,
        'title'       => '訂單已出貨',
        'content'     => "您的訂單 #{$order_id} 已出貨，請留意物流訊息。",
      ]);
    } elseif ($old === 1 && $new === 2) {
      create_notification($db, [
        'receiver_id' => $user_id,
        'order_id'    => $order_id,
        'type'        => 21,
        'title'       => '訂單已完成',
        'content'     => "您的訂單 #{$order_id} 已完成，感謝您的購買！",
      ]);
    } elseif ($old === 0 && $new === 3) {
      create_notification($db, [
        'receiver_id' => $user_id,
        'order_id'    => $order_id,
        'type'        => 22,
        'title'       => '訂單已取消',
        'content'     => "您的訂單 #{$order_id} 已取消，如有疑問請聯繫客服。",
      ]);
    }
  }

  /**
   *食譜狀態 0->1 上架(type=10) / 0->4 退回(type=11)
   */
  function notify_recipe_on_status_change(mysqli $db, int $old, int $new, int $recipe_id, int $author_id, string $recipe_name): void {
    if ($old === 0 && $new === 1) {
      create_notification($db, [
        'receiver_id' => $author_id,
        'recipe_id'   => $recipe_id,
        'type'        => 10,
        'title'       => '食譜已上架',
        'content'     => "你的食譜《{$recipe_name}》已通過並上架！",
      ]);
    } elseif ($old === 0 && $new === 4) {
      create_notification($db, [
        'receiver_id' => $author_id,
        'recipe_id'   => $recipe_id,
        'type'        => 11,
        'title'       => '食譜審核未通過',
        'content'     => "你的食譜《{$recipe_name}》未通過審核，請調整後再送審。",
      ]);
    }
  }

  /**
   * 新留言通知作者 (type=30)
   */
  function notify_recipe_new_comment(mysqli $db, int $author_id, int $recipe_id, int $comment_id, string $recipe_name, string $comment_content): void {
    $preview = mb_substr($comment_content, 0, 50);
    create_notification($db, [
      'receiver_id' => $author_id,
      'recipe_id'   => $recipe_id,
      'comment_id'  => $comment_id,
      'type'        => 30,
      'title'       => "食譜《{$recipe_name}》有新留言",
      'content'     => $preview,
    ]);
  }
?>
