<?php
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/config.php';

header('Content-Type: application/json');
global $mysqli; 

try {

    $request_data = $_GET;


    if (!isset($request_data['q'])) {
    http_response_code(400); 
    echo json_encode(['success' => false, 'message' => 'Query parameter is missing.']);
    return;
}

    // $searchQuery = urldecode($_POST['q']);
    $searchQuery = $request_data['q'];
    
    $escapedSearchQuery = $mysqli->real_escape_string($searchQuery);
    
    $sql = "SELECT recipe_id, name, cooked_time, image
            FROM recipe
            WHERE name LIKE '%{$escapedSearchQuery}%'
            OR tag LIKE '%{$escapedSearchQuery}%'";
    
    $result = $mysqli->query($sql);
    
    if (!$result) {
        throw new mysqli_sql_exception('Database query failed: ' . $mysqli->error);
    }

    $recipes = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $row['image'] = IMG_BASE_URL . '/' . $row['image'];
            $recipes[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'data' => $recipes]);

} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database q failed.', 'error_detail' => $e->getMessage()]);

} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>