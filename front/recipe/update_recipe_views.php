<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
header('Content-Type: application/json');
global $mysqli;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
        return;
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['recipe_id']) || !is_numeric($data['recipe_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid recipe_id parameter.']);
        return;
    }

    $recipeId = $data['recipe_id'];
    $escapedRecipeId = $mysqli->real_escape_string($recipeId);
    $sql = "UPDATE recipe SET views = views + 1 WHERE recipe_id = '{$escapedRecipeId}'";
    $result = $mysqli->query($sql);

    if (!$result) {
        throw new mysqli_sql_exception("Database query failed: " . $mysqli->error);
    }
    
    if ($mysqli->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Recipe view count updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Recipe not found or view count already up to date.']);
    }

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database operation failed.', 'error' => $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>