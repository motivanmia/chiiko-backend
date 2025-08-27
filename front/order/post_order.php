<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';
  require_once __DIR__ . '/../../common/ecpay.php';
  require_once __DIR__ . '/../../common/linepay.php';

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

// 轉換付款方式：card → 0, cash → 1, linepay → 1
if ($payment_type_str === 'card') {
  $payment_type = 0;
} elseif ($payment_type_str === 'cash') {
  $payment_type = 1;
} elseif ($payment_type_str === 'linepay') {
  $payment_type = 2;
} else {
  send_json(['status' => 'fail', 'message' => '付款方式錯誤'], 400);
}

// 轉換收件地點：mainIsland → 0, outlyingIslands → 1
$location_map = [
    'mainIsland'     => 0,
    'outlyingIslands'=> 1
];
if (!isset($location_map[$payment_location_str])) {
    send_json(['status' => 'fail', 'message' => '收件地點錯誤'], 400);
}
$payment_location = $location_map[$payment_location_str];

// 撈購物車商品
$sql_cart = "SELECT c.product_id, c.quantity, p.name, p.unit_price
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
$item_details = [];
foreach ($cart_items as $item) {
  $total_price += $item['unit_price'] * $item['quantity'];
  $item_details[] = $item['name'] . " " . $item['unit_price'] . "x" . $item['quantity'];
}

// 運費判斷
if ($total_price >= 1000) {
    $freight = 0;
} else {
    $freight = ($payment_location === 0) ? 100 : 200;
}

// 如果有運費就加上去
if ($freight > 0) {
  $item_details[] = "運費 " . $freight . "x1";
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
// $sql_clear = "DELETE FROM carts WHERE user_id = {$user_id}";
// db_query($mysqli, $sql_clear);

create_notification($mysqli, [
    'receiver_id' => $user_id,
    'order_id'    => $order_id,
    'type'        => 19,
    'title'       => '訂單已建立',
    'content'     => "您的訂單 #{$order_id} 已建立，我們會盡快處理！"
  ]);

if ($payment_type === 0) {

  $ecpay_params = [
    'MerchantID'        => MERCHANT_ID,
    'MerchantTradeNo'   => $tracking_number,
    'MerchantTradeDate' => date("Y/m/d H:i:s"),
    'PaymentType'       => 'aio',
    'TotalAmount'       => $final_price,
    'TradeDesc'         => '訂單測試',
    'ItemName'          => implode('#', $item_details),
    'ReturnURL'         => BASE_URL . '/front/order/ecpay_callback.php',
    'ChoosePayment'     => 'Credit',
    'ClientBackURL'     => FRONT_BASE_URL . '/order-success?order_id=' . $order_id,
    'EncryptType'       => 1
  ];

  // 計算檢查碼
  $ecpay_params['CheckMacValue'] = generateCheckMacValue($ecpay_params, HASH_KEY, HASH_IV);

  send_json([
    'status' => 'success',
    'message' => '訂單建立成功',
    'data' => [
      'order_id' => $order_id,
      'tracking_number' => $tracking_number,
      'final_price' => $final_price,
      'freight' => $freight,
      'payment_url' => 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5',
      'params' => $ecpay_params
    ]
  ]);
}

if ($payment_type === 2) {
    $linePayChannelId = LINE_PAY_CHANNEL_ID;
    $linePayChannelSecret = LINE_PAY_CHANNEL_SECRET;
    $linePayApiUrl = "https://sandbox-api-pay.line.me/v3/payments/request";
    $nonce = uniqid();

    // 自己生成 linePay 專用的 order id
    $linePayOrderId = $tracking_number;
    // $linePayOrderId = "LP" . $order_id . time();

    // 更新 DB：存下 LinePay 的暫時交易編號
    $updateSql = "UPDATE orders SET tracking_number = '" . $mysqli->real_escape_string($linePayOrderId) . "' WHERE order_id = '$order_id'";
    
    db_query($mysqli, $updateSql);
    // $updateSql = "UPDATE orders SET transaction_id = '" . $mysqli->real_escape_string($linePayOrderId) . "' WHERE order_id = {$order_id}";
    // db_query($mysqli, $updateSql);

    // 包裝商品
    $linepay_products = [];
    foreach ($cart_items as $item) {
        $linepay_products[] = [
            "id"       => (string)$item['product_id'],
            "name"     => $item['name'],
            "quantity" => (int)$item['quantity'],
            "price"    => (int)$item['unit_price']
        ];
    }
    if ($freight > 0) {
      $linepay_products[] = [
        "id" => "shipping-fee",
        "name" => "運費",
        "quantity" => 1,
        "price" => (int)$freight
      ];
    }

    $requestBody = [
      "amount"   => (int)$final_price,
      "currency" => "TWD",
      "orderId"  => $linePayOrderId,
      "packages" => [
        [
          "id"       => "PKG-" . $order_id,
          "amount"   => (int)$final_price,
          "products" => $linepay_products
        ]
      ],
      "redirectUrls" => [
          "confirmUrl" => BASE_URL . '/front/order/linepay_confirm.php' . "?orderId=" . $linePayOrderId,
          // "cancelUrl"  => $_ENV['LINEPAY_CANCEL_URL'] . "?orderId=" . $linePayOrderId
      ]
    ];

    $requestBodyJson  = json_encode($requestBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signatureUrl     = "/v3/payments/request";
    $signatureContent = $linePayChannelSecret . $signatureUrl . $requestBodyJson . $nonce;
    $signature        = linepay_signature(LINE_PAY_CHANNEL_SECRET, "POST", $signatureUrl, $requestBodyJson, $nonce);
    // $signature        = base64_encode(hash_hmac('sha256', $signatureContent, $linePayChannelSecret, true));

    $headers = [
      "Content-Type: application/json",
      "X-LINE-ChannelId: " . $linePayChannelId,
      "X-LINE-Authorization-Nonce: " . $nonce,
      "X-LINE-Authorization: " . $signature,
    ];

    $ch = curl_init($linePayApiUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBodyJson);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $result   = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
      $error_msg = curl_error($ch);
      curl_close($ch);
      http_response_code(500);
      echo json_encode([
          "error" => "cURL 請求失敗",
          "message" => "網路連線錯誤：" . $error_msg
      ]);
      exit();
    }
    curl_close($ch);

    $linePayResponse = json_decode($result, true);

    if ($httpcode === 200 && isset($linePayResponse['returnCode']) && $linePayResponse['returnCode'] === '0000') {
      send_json([
        'status'  => 'success',
        'message' => '訂單建立成功，準備跳轉至 Line Pay',
        'data'    => [
          'order_id' => $order_id,
          'tracking_number' => $tracking_number,
          'final_price' => $final_price,
          'freight' => $freight,
          'redirect_url' => $linePayResponse['info']['paymentUrl']['web']
        ]
      ]);
    } else {
      send_json([
        'status'  => 'fail',
        'message' => $linePayResponse['returnMessage'] ?? 'Line Pay 建立交易失敗',
        'raw'     => $linePayResponse
      ], 500);
    }
}

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
