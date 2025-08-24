<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');

global $mysqli;

try {
    if (!isset($_GET['member_id']) || !isset($_GET['recipe_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'isFavorited' => false, 'message' => 'Invalid parameters.']);
        exit(); 
    }

    
    $memberId = $mysqli->real_escape_string($_GET['member_id']);
    $recipeId = $mysqli->real_escape_string($_GET['recipe_id']);

    
    $sql = "SELECT COUNT(*) FROM `recipe_favorite` WHERE `member_id` = '{$memberId}' AND `recipe_id` = '{$recipeId}'";

    $result = mysqli_query($mysqli, $sql);

    if ($result) {
        $row = mysqli_fetch_row($result);
        
        mysqli_free_result($result);

        $isFavorited = ($row[0] > 0);

        echo json_encode(['success' => true, 'isFavorited' => $isFavorited]);
    } else {
        throw new \mysqli_sql_exception('查詢失敗: ' . mysqli_error($mysqli));
    }

} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'isFavorited' => false, 'message' => 'Database operation failed.', 'error' => $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        mysqli_close($mysqli);
    }
}
?>