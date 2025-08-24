<?php
    require_once __DIR__ . '/../../common/conn.php';
    require_once __DIR__ . '/../../common/cors.php';
    require_once __DIR__ . '/../../common/functions.php';
    //
    // 設定 Content-Type 標頭為 JSON
    header('Content-Type: application/json');
    //
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        // 如果已登入，回傳使用者資料
        $response = [
            'is_logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name'],
        ];
    } else {
        $response = [
            'is_logged_in' => false,
        ];
    }
    // 將 $response 陣列轉換為 JSON 字串並輸出給前端
    echo json_encode($response);
    //
    exit();
?>