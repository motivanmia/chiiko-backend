<?php
  require_once __DIR__ . '/../common/cors.php';
  require_once __DIR__ . '/../common/conn.php';
  require_once __DIR__ . '/../common/functions.php';

// 1. 檢查使用者是否已登入
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'fail','message' => '未經授權。']);
    exit();
}

// 2. 檢查使用者權限 (只有 role=0 的使用者才能看此列表)
if (isset($_SESSION['role']) && $_SESSION['role'] !== 0) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'fail','message' => '權限不足。']);
    exit();
}

try {
    // 3. 從資料庫查詢管理員資料
    $sql = "SELECT manager_id, name, account, role, status FROM managers ORDER BY manager_id";
    $result = mysqli_query($mysqli, $sql);

    // 檢查查詢是否成功
    if (!$result) {
        throw new Exception("SQL 查詢失敗: " . mysqli_error($mysqli));
    }

    $admin_list = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $admin_list[] = $row;
    }

    mysqli_free_result($result);

    // 4. 回傳 JSON 格式的資料
    echo json_encode(['status' => 'success', 'data' => $admin_list]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => 'fail', 'message' => '伺服器內部錯誤。']);
    } finally {
        // 確保在腳本結束前關閉資料庫連線
        if (isset($mysqli)) {
            mysqli_close($mysqli);
        }
    }

?>