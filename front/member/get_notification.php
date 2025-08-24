<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('GET');



if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// $me = checkUserLoggedIn();
// if ($me === false || empty($me['user_id'])) {
//   send_json(['status' => 'fail', 'message' => '未登入或 Session 已失效'], 401);
// }

$user_id = $_SESSION['user_id'] ?? 0;

$sql = "
  SELECT
    notification_id,
    receiver_id,
    comment_id,
    recipe_id,
    order_id,
    type,
    content,
    status,
    created_at
  FROM notification
  WHERE receiver_id = {$user_id}
  ORDER BY created_at DESC, notification_id DESC
";

$result = db_query($mysqli, $sql);
$data   = $result->fetch_all(MYSQLI_ASSOC);

send_json([
  'status'  => 'success',
  'message' => '通知取得成功',
  'data'    => $data,
]);

$mysqli->close();

?>