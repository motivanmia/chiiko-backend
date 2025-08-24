<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';


header('Content-Type: application/json');

global $mysqli;

try {
    $memberId = $_SESSION['user_id'] ?? null;

    $recipeId = isset($_GET['recipe_id']) ? $mysqli->real_escape_string($_GET['recipe_id']) : null;

    if (!$memberId || !$recipeId) {

        http_response_code(200); 
        echo json_encode(['success' => true, 'is_collected' => false, 'message' => 'User not logged in or invalid recipe ID.']);
        exit();
    }

    $query = "SELECT COUNT(*) AS count FROM `recipe_favorite` WHERE `member_id` = '{$memberId}' AND `recipe_id` = '{$recipeId}'";

    $result = $mysqli->query($query);

    if (!$result) {
        throw new \mysqli_sql_exception('查詢失敗: ' . $mysqli->error);
    }

    $row = $result->fetch_assoc();
    $is_collected = ($row['count'] > 0);

    echo json_encode(['success' => true, 'is_collected' => $is_collected, 'message' => 'Status retrieved successfully.']);

} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'is_collected' => false, 'message' => 'Database operation failed.', 'error' => $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}

?>