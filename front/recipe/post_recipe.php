<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/functions.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/conn.php';

  try {
    require_method('POST');
    $data = get_json_input();

    // 數據清洗與準備...
    $toIntOrNull = fn($v) => (isset($v) && $v !== '' && is_numeric($v)) ? (int)$v : null;
    $toStrOrNull = fn($v) => (isset($v) && $v !== '') ? (string)$v : null;

    $user_id            = $toIntOrNull($data['user_id'] ?? null);
    $manage_id          = $toIntOrNull($data['manage_id'] ?? null);
    $recipe_category_id = $toIntOrNull($data['recipe_category_id'] ?? null);
    // ... 其他欄位 ...
    $status_code        = is_numeric($data['status'] ?? 3) && in_array((int)($data['status'] ?? 3), [0,1,2,3], true) ? (int)$data['status'] : 3;

        // 這些是數字型別的欄位。如果前端沒給，就當作 NULL 存入資料庫。
        // 使用 intval() 確保它們是數字，防止 SQL 注入。
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : 'NULL';
        $manage_id = isset($data['manage_id']) ? intval($data['manage_id']) : 'NULL';
        $recipe_category_id = isset($data['recipe_category_id']) ? intval($data['recipe_category_id']) : 'NULL';
        $status = isset($data['status']) ? intval($data['status']) : 0; // status 通常給個預設值 0

        // 這些是字串型別的欄位。
        // 必須使用 real_escape_string 進行安全轉義，並在兩側加上單引號 "'"。
        // 使用 ?? '' 確保即使前端沒給值，我們也會傳入一個安全的空字串，而不是 null。
        $name = "'" . $mysqli->real_escape_string($data['name'] ?? '') . "'";
        $content = "'" . $mysqli->real_escape_string($data['content'] ?? '') . "'";
        $serving = "'" . $mysqli->real_escape_string($data['serving'] ?? '') . "'";
        $image = "'" . $mysqli->real_escape_string($data['image'] ?? '') . "'";
        $cooked_time = "'" . $mysqli->real_escape_string($data['cooked_time'] ?? '') . "'";
        $tag = "'" . $mysqli->real_escape_string($data['tag'] ?? '') . "'";

        // 拼接 SQL 查詢字串
        $sql = "INSERT INTO `recipe` (
            `user_id`, `manage_id`, `recipe_category_id`, `name`, `content`, 
            `serving`, `image`, `cooked_time`, `status`, `tag`
        ) VALUES (
            {$user_id}, {$manage_id}, {$recipe_category_id}, {$name}, {$content},
            {$serving}, {$image}, {$cooked_time}, {$status}, {$tag}
        )";

        $result = $mysqli->query($sql);

        // 檢查查詢是否成功
        if ($result) {
            // ⭐️ 功能擴充：取得剛剛建立的那筆資料的 ID
            $new_recipe_id = $mysqli->insert_id; 
            
            http_response_code(201); // 201 Created
            
            // ⭐️ 功能擴充：將新建立的 recipe_id 一併回傳給前端
            // 這樣前端才能接著去新增屬於這篇食譜的食材和步驟
            echo json_encode([
                'message' => '食譜新增成功！',
                'recipe_id' => $new_recipe_id
            ]);
        } else {
            // 如果是 SQL 語法錯誤，回傳 400 Bad Request 會更語意化
            http_response_code(400); 
            echo json_encode(['error' => '新增食譜失敗: ' . $mysqli->error]);
        }
    } catch (\Exception $e) {
        // 捕捉其他所有未預期的錯誤，例如 JSON 解析失敗等
        http_response_code(500);
        echo json_encode(['error' => '伺服器發生未預期錯誤: ' . $e->getMessage()]);
    // 欄位驗證...
    if ($status_code === 0 || $status_code === 1) {
      $errors = [];
      if (empty(trim($data['name'] ?? ''))) $errors[] = '請輸入食譜名稱';
      // ... 其他驗證 ...
      if (!empty($errors)) {
        // 直接拋出一個例外，讓下面的 catch 區塊來處理
        throw new Exception('驗證失敗', 400); 
      }
    }

    // SQL 操作...
    $sql = "INSERT INTO `recipe` (...) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())";
    $types = "iiisssssis";
    $params = [
      $user_id, $manage_id, $recipe_category_id,
      $data['name'] ?? '', $data['content'] ?? '', $data['serving'] ?? null,
      $data['image'] ?? '', $data['cooked_time'] ?? null, $status_code, $data['tag'] ?? ''
    ];

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $new_recipe_id = $stmt->insert_id;
    $stmt->close();

    send_json([
      'status'    => 'success',
      'message'   => '食譜新增成功！',
      'recipe_id' => $new_recipe_id
    ], 201);

  } catch (Throwable $e) {
    // 統一的錯誤處理出口
    $code = $e->getCode() ?: 500; // 如果沒有 code，就用 500
    $code = is_numeric($code) && $code >= 400 && $code < 600 ? $code : 500;
    send_json([
      'status'  => 'fail',
      'message' => $e->getMessage() ?: '伺服器發生未預期錯誤',
    ], $code);
  } finally {
    // 確保資料庫連線總是會被關閉
    if (isset($mysqli)) {
      $mysqli->close();
    }
  }
?>