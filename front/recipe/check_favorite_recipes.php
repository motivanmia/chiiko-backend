<?php
  // =================================================================
  //  檢查單一食譜收藏狀態 API (check_favorite_recipes.php)
  //  [最終修正版 - 為了匹配 recipeCollectStore.js 的期望格式]
  // =================================================================

  // 引入共用檔案
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';



  // 限制 API 只允許 GET 方法的請求
  require_method('GET');

  // 檢查使用者是否登入
  // 這裡假設你登入成功後，存在 session 中的 key 是 'member_id'
  if (!isset($_SESSION['member_id'])) {
      // ✅ 核心修正：
      // 對於未登入的用戶，這是一次「成功」的查詢，結果是「未收藏」。
      // 輸出 store 期望的扁平結構。
      send_json([
          'success' => true,
          'is_collected' => false
      ]);
      exit;
  }

  // 取得必要的參數
  $recipe_id = get_int_param('recipe_id');
  $member_id = $_SESSION['member_id'];

  if (!$recipe_id) {
      // ✅ 核心修正：
      // 對於缺少參數的情況，輸出 store 期望的失敗結構。
      send_json([
          'success' => false, 
          'message' => '缺少 recipe_id 參數'
      ], 400);
      exit;
  }

  // 查詢資料庫
  $sql = "SELECT COUNT(*) as count FROM recipe_favorite WHERE recipe_id = ? AND member_id = ?";
  $stmt = $mysqli->prepare($sql);

  if (!$stmt) {
    send_json(['success' => false, 'message' => '資料庫查詢準備失敗: ' . $mysqli->error], 500);
    exit;
  }

  $stmt->bind_param('ii', $recipe_id, $member_id);
  $stmt->execute();
  $result = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $is_collected = $result['count'] > 0;

  // ✅ 核心修正：
  // 將最終的成功結果，也輸出為 store 期望的扁平結構。
  send_json([
      'success' => true,
      'is_collected' => $is_collected
  ]);

  $mysqli->close();
?>