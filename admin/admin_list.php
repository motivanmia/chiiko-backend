<?php
  require_once __DIR__ . '/../common/cors.php';
  require_once __DIR__ . '/../common/conn.php';
  require_once __DIR__ . '/../common/functions.php';

// 1. 檢查使用者是否已登入
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['message' => '未經授權。']);
    exit();
}

// 2. 檢查使用者權限 (只有 role=0 的使用者才能看此列表)
if (isset($_SESSION['role']) && $_SESSION['role'] !== 0) {
    http_response_code(403); // Forbidden
    echo json_encode(['message' => '權限不足。']);
    exit();
}

try {
    // 3. 從資料庫查詢管理員資料
    // 選取你需要顯示的欄位
    $stmt = $mysqli->prepare("SELECT manager_id, name, account, role, status FROM managers ORDER BY manager_id DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    $admin_list = [];
    while ($row = $result->fetch_assoc()) {
        $admin_list[] = $row;
    }

    $stmt->close();

    // 4. 回傳 JSON 格式的資料
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $admin_list]);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '伺服器內部錯誤。']);
}
?>