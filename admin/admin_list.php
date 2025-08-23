<?php
    require_once __DIR__ . '/../common/cors.php';
    require_once __DIR__ . '/../common/conn.php';
    require_once __DIR__ . '/../common/functions.php';
    //
    // 檢查是否已登入
    if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
        send_json(['status' => 'fail', 'message' => '未經授權。'], 401);
    }
    //
    // 檢查使用者權限 (只有 role=0 才能看)
    if (isset($_SESSION['role']) && $_SESSION['role'] !== 0) {
        send_json(['status' => 'fail', 'message' => '權限不足。'], 403);
    }
    //
    try {
        // 3. 從資料庫查詢管理員資料
        $sql = "SELECT manager_id, name, account, role, status FROM managers ORDER BY manager_id";
        $result = mysqli_query($mysqli, $sql);
        //
        // 檢查查詢是否成功
        if (!$result) {
            throw new Exception("SQL 查詢失敗: " . mysqli_error($mysqli));
        }
        //
        $admin_list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $admin_list[] = $row;
        }
        //
        mysqli_free_result($result);
        //
        // 回傳 JSON 格式的資料
        send_json(['status' => 'success', 'data' => $admin_list]);
        //
    } catch (Exception $e) {
        send_json(['status' => 'fail', 'message' => '伺服器內部錯誤。'], 500);
    } finally {
        if (isset($mysqli)) {
            mysqli_close($mysqli);
        }
    }
?>