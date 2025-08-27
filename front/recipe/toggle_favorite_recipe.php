<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';


header('Content-Type: application/json');

global $mysqli;

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // 修正：不檢查 $data['member_id']，因為它會從 Session 獲取
    if (!isset($data['recipe_id']) || !isset($data['action'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        exit();
    }
    // 確保 Session 有效
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not logged in.']);
        exit();
    }

    $memberId = $_SESSION['user_id'];
    $recipeId = mysqli_real_escape_string($mysqli, $data['recipe_id']);
    $action = $data['action'];

    if ($action === 'add') {
        $check_sql = "SELECT COUNT(*) FROM `recipe_favorite` WHERE `user_id` = '{$memberId}' AND `recipe_id` = '{$recipeId}'";
        
        $check_result = mysqli_query($mysqli, $check_sql);
        if (!$check_result) {
            throw new \mysqli_sql_exception("Check query failed: " . mysqli_error($mysqli));
        }
        $row = mysqli_fetch_row($check_result);
        mysqli_free_result($check_result);
        
        if ($row[0] > 0) {
            echo json_encode(['success' => false, 'message' => '食譜已被您收藏囉!']);
            exit();
        }

        $sql = "INSERT INTO `recipe_favorite` (`user_id`, `recipe_id`) VALUES ('{$memberId}', '{$recipeId}')";
        
        $result = mysqli_query($mysqli, $sql);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Recipe favorited successfully.']);
        } else {
            throw new \mysqli_sql_exception("Insert failed: " . mysqli_error($mysqli));
        }
    } elseif ($action === 'remove') {
        $sql = "DELETE FROM `recipe_favorite` WHERE `user_id` = '{$memberId}' AND `recipe_id` = '{$recipeId}'";
        
        $result = mysqli_query($mysqli, $sql);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Recipe removed from favorites.']);
        } else {
            throw new \mysqli_sql_exception("Delete failed: " . mysqli_error($mysqli));
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }

} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database operation failed.', 'error' => $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        mysqli_close($mysqli);
    }
}
?>