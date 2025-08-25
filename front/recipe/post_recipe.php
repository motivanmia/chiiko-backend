<?php
//新增食譜功能
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/functions.php'; 
  require_once __DIR__ . '/../../common/conn.php';

  session_start();

  try {
    require_method('POST');

    $loggedInUser = checkUserLoggedIn();
    if (!$loggedInUser) {
      throw new Exception('使用者未登入，禁止操作', 401);
    }


    $data = get_json_input();

    $toIntOrNull = fn($v) => (isset($v) && $v !== '' && is_numeric($v)) ? (int)$v : null;
    $toStrOrNull = fn($v) => (isset($v) && $v !== '') ? (string)$v : null;

    $user_id            = $loggedInUser['user_id']; 
    $manager_id          = $toIntOrNull($data['manager_id'] ?? null); 
    $recipe_category_id = $toIntOrNull($data['recipe_category_id'] ?? null);
    
    $status_code        = is_numeric($data['status'] ?? 3) && in_array((int)($data['status'] ?? 3), [0,1,2,3], true) ? (int)$data['status'] : 3;

    // 欄位驗證
    if ($status_code === 0 || $status_code === 1) {
      $errors = [];
      if (empty(trim($data['name'] ?? ''))) $errors[] = '請輸入食譜名稱';
      if (!empty($errors)) {
        throw new Exception('驗證失敗', 400); 
      }
    }

    // SQL 操作
    $sql = "INSERT INTO `recipe`
(`user_id`, `manager_id`, `recipe_category_id`, `name`, `content`, `serving`, `image`, `cooked_time`, `status`, `tag`, `views`, `created_at`)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $types = "iiisssssisi";

    $params = [
      $user_id, 
      $manager_id, 
      $recipe_category_id,
      $data['name'] ?? '', $data['content'] ?? '', $data['serving'] ?? null,
      $data['image'] ?? '', $data['cooked_time'] ?? null, $status_code, $data['tag'] ?? '',
      $toIntOrNull($data['views'] ?? 0)
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
    $code = $e->getCode() ?: 500;
    $code = is_numeric($code) && $code >= 400 && $code < 600 ? $code : 500;
    send_json([
      'status'  => 'fail',
      'message' => $e->getMessage() ?: '伺服器發生未預期錯誤',
    ], $code);
  } finally {
    if (isset($mysqli)) {
      $mysqli->close();
    }
  }
?>