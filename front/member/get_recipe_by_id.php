<?php
  require_once __DIR__ . '/../../common/config.php';
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  try {
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'fail', 'message' => '使用者未登入']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['status' => 'fail', 'message' => 'Method Not Allowed']);
        exit;
    }

    // 從 URL 查詢字串中獲取 recipe_id
    $recipe_id = isset($_GET['recipe_id']) ? $_GET['recipe_id'] : null;
    if ($recipe_id === null || !is_numeric($recipe_id) || $recipe_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'fail', 'message' => '無效的食譜 ID']);
        exit;
    }

    $clean_recipe_id = $mysqli->real_escape_string($recipe_id);
    $clean_user_id = $mysqli->real_escape_string($user_id);

    $sql = "
        SELECT * FROM `recipe` 
        WHERE recipe_id = '{$clean_recipe_id}' AND user_id = '{$clean_user_id}'
    ";

    $result = $mysqli->query($sql);
    if (!$result) {
        throw new Exception("SQL 查詢失敗: " . $mysqli->error);
    }

    if ($result->num_rows > 0) {
        $recipeData = $result->fetch_assoc();
        
        $recipeData['image'] = IMG_BASE_URL . '/' . htmlspecialchars($recipeData['image']);

        http_response_code(200);
        echo json_encode(['status' => 'success', 'data' => $recipeData]);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'fail', 'message' => '找不到該食譜或無權限存取']);
    }

    $result->free();

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'fail', 'message' => '伺服器發生錯誤: ' . $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}

?>