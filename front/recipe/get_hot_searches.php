<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');
global $mysqli;

try {
    $sql = "SELECT recipe_id, name FROM recipe ORDER BY views DESC LIMIT 4";
    
    $result = $mysqli->query($sql);

    if (!$result) {
        throw new mysqli_sql_exception("Database query failed: " . $mysqli->error);
    }

    $hot_searches = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $hot_searches[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $hot_searches]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database operation failed.', 'error' => $e->getMessage()]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>