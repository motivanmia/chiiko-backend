<?php
    require_once __DIR__ . '/../common/cors.php';
    require_once __DIR__ . '/../common/conn.php';
    require_once __DIR__ . '/../common/functions.php';

    require_method('POST');

    // 讀取 raw JSON
    $input = get_json_input();

    // 檢查必要參數
    $manager_id = isset($input['manager_id']) ? intval($input['manager_id']) : null;
    $new_status = isset($input['status']) ? intval($input['status']) : null;

    if ($manager_id === null || $new_status === null) {
        send_json([
            'status' => 'fail',
            'message' => '缺少必要的參數 (manager_id 或 status)'
        ], 400);
    }

    $safe_manager_id = mysqli_real_escape_string($mysqli, $manager_id);
    $safe_new_status = mysqli_real_escape_string($mysqli, $new_status);

    // 檢查 manager_id 是否存在於資料庫中
    $check_admin_sql = "SELECT `manager_id` FROM `managers` WHERE `manager_id` = '$safe_manager_id'";
    $check_result = mysqli_query($mysqli, $check_admin_sql);

    if (!$check_result || mysqli_num_rows($check_result) === 0) {
        send_json([
            'status' => 'fail',
            'message' => '指定管理員不存在'
        ], 404);
    }

    // 釋放結果集
    mysqli_free_result($check_result);

    // 更新管理員狀態
    $update_sql = "UPDATE `managers` SET `status` = '$safe_new_status' WHERE `manager_id` = '$safe_manager_id'";
    $update_result = mysqli_query($mysqli, $update_sql);

    if ($update_result) {
        // 檢查是否有資料被更新
        if (mysqli_affected_rows($mysqli) > 0) {
            send_json([
                'status' => 'success',
                'message' => '狀態已成功更新'
            ]);
        } else {
            send_json([
                'status' => 'success',
                'message' => '狀態沒有改變，無需更新'
            ]);
        }
    } else {
        // 執行失敗
        send_json([
            'status' => 'fail',
            'message' => '更新失敗: ' . mysqli_error($mysqli)
        ], 500);
    }

    // 關閉連線
    mysqli_close($mysqli);
?>