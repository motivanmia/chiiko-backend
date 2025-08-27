<?php
require_once __DIR__ . '/../common/cors.php';
require_once __DIR__ . '/../common/conn.php';
require_once __DIR__ . '/../common/functions.php';

require_method('POST');

// 確認登入
if (!isset($_SESSION['manager_id'])) {
    send_json(['status' => 'fail', 'message' => '尚未登入後台'], 401);
}

// 讀取登入者資料
$my_manager_id = $_SESSION['manager_id'];
$my_role       = $_SESSION['role'] ?? 1; // 預設系統管理員

// if ($my_role != 0) { // 只有超級管理員可以改權限
//     send_json(['status' => 'fail', 'message' => '沒有權限操作'], 403);
// }

// 讀取 JSON
$input = get_json_input();
// $manager_id = isset($input['manager_id']) ? intval($input['manager_id']) : null;
$new_role   = isset($input['new_role']) ? intval($input['new_role']) : null;

if ($new_role === null || !in_array($new_role, [0,1])) {
    send_json(['status' => 'fail', 'message' => '參數錯誤'], 400);
}

// 避免自己降級
if ($my_manager_id && $new_role != 0) {
    send_json(['status' => 'fail', 'message' => '不能降低自己的權限'], 400);
}

// 檢查管理員是否存在
$check_sql = "SELECT manager_id FROM managers WHERE manager_id = " . $my_manager_id;
$check_result = $mysqli->query($check_sql);
if (!$check_result || $check_result->num_rows === 0) {
    send_json(['status' => 'fail', 'message' => '管理員不存在'], 404);
}

// 更新權限
$update_sql = "UPDATE managers SET role = $new_role WHERE manager_id = $my_manager_id";
$update_result = $mysqli->query($update_sql);

if ($update_result) {
    if ($mysqli->affected_rows > 0) {
        send_json(['status' => 'success', 'message' => '權限更新成功']);
    } else {
        send_json(['status' => 'success', 'message' => '權限沒有改變']);
    }
} else {
    send_json(['status' => 'fail', 'message' => '更新失敗: ' . $mysqli->error], 500);
}

$mysqli->close();
?>
