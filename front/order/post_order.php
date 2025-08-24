<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('POST');

  // 確認登入
  $user = checkUserLoggedIn();
  if (!$user) {
    send_json([
      'status' => 'fail',
      'message' => '尚未登入'
    ], 401);
  }
  $user_id = $user['user_id'];

// 讀取 raw JSON
  $input = get_json_input();

  // 必填資訊（收件人、電話、地址、付款方式）
  $recipient            = $mysqli->real_escape_string($input['recipient']) ?? null;
  $recipient_phone      = $mysqli->real_escape_string($input['recipient_phone']) ?? null;
  $shopping_address     = $mysqli->real_escape_string($input['shopping_address']) ?? null;
  $payment_type_str     = $mysqli->real_escape_string($input['payment_type']) ?? null;
  $payment_location_str = $mysqli->real_escape_string($input['payment_location'] ?? null);

if (
  !$recipient ||
  !$recipient_phone ||
  !$shopping_address ||
  !$payment_type_str ||
  !$payment_location_str
) {
  send_json(['status' => 'fail', 'message' => '請填寫完整收件資訊'], 400);
}

// 轉換付款方式：card → 0, cash → 1
if ($payment_type_str === 'card') {
  $payment_type = 0;
} elseif ($payment_type_str === 'cash') {
  $payment_type = 1;
} else {
  send_json(['status' => 'fail', 'message' => '付款方式錯誤'], 400);
}

// 轉換收件地點：mainIsland → 0, outlyingIslands → 2
$location_map = [
    'mainIsland'     => 0,
    'outlyingIslands'=> 2
];
if (!isset($location_map[$payment_location_str])) {
    send_json(['status' => 'fail', 'message' => '收件地點錯誤'], 400);
}
$payment_location = $location_map[$payment_location_str];

// 撈購物車商品
$sql_cart = "
  SELECT c.product_id, c.quantity, p.name, p.unit_price
  FROM carts c
  JOIN products p ON c.product_id = p.product_id
  WHERE c.user_id = {$user_id}
";
$result = db_query($mysqli, $sql_cart);
$cart_items = $result->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
  send_json(['status' => 'fail', 'message' => '購物車是空的'], 400);
}

// 計算金額
$total_price = 0;
foreach ($cart_items as $item) {
  $total_price += $item['unit_price'] * $item['quantity'];
}


// 運費判斷
if ($total_price >= 1000) {
    $freight = 0;
} else {
    $freight = ($payment_location === 0) ? 100 : 200;
}

$final_price = $total_price + $freight;

// 產生 tracking_number（10碼亂碼）
$tracking_number = strtoupper(bin2hex(random_bytes(5)));

// 建立訂單
$sql_order = sprintf(
  "INSERT INTO orders 
  (user_id, recipient, recipient_phone, shopping_address, total_price, freight, final_price, payment_type, tracking_number, payment_location)
  VALUES (%d, '%s', '%s', '%s', %d, %d, %d, %d, '%s', %d)",
  $user_id,
  $recipient,
  $recipient_phone,
  $shopping_address,
  $total_price,
  $freight,
  $final_price,
  $payment_type,
  $tracking_number,
  $payment_location
);
db_query($mysqli, $sql_order);

// 取得新建立的 order_id
$order_id = $mysqli->insert_id;

// 建立 order_item
foreach ($cart_items as $item) {
  $sql_item = sprintf(
    "INSERT INTO order_item (order_id, product_id, name, quantity, unit_price)
    VALUES (%d, %d, '%s', %d, %d)",
    $order_id,
    $item['product_id'],
    $mysqli->real_escape_string($item['name']),
    $item['quantity'],
    $item['unit_price']
  );
  db_query($mysqli, $sql_item);
}

// 清空購物車
$sql_clear = "DELETE FROM carts WHERE user_id = {$user_id}";
db_query($mysqli, $sql_clear);

// 回傳成功
send_json([
  'status' => 'success',
  'message' => '訂單建立成功',
  'data' => [
    'order_id' => $order_id,
    'tracking_number' => $tracking_number,
    'final_price' => $final_price,
    'freight' => $freight
  ]
]);
?>
