<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/ecpay.php';

  // ----------------------
  // 讀取 POST 資料
  // ----------------------
  $postData = $_POST;

  if (empty($postData)) {
      echo '0|No Data';
      exit;
  }

  // ----------------------
  // 驗證 CheckMacValue
  // ----------------------
  $checkMac = generateCheckMacValue($postData, HASH_KEY, HASH_IV, true);

  if ($checkMac !== $postData['CheckMacValue']) {
      echo '0|ErrorHash';
      exit;
  }

  // ----------------------
  // 判斷交易狀態
  // ----------------------
  $MerchantTradeNo = $mysqli->real_escape_string($postData['MerchantTradeNo']); // 我們在建立訂單時產生的編號
  $RtnCode         = intval($postData['RtnCode']); // 1 代表成功
  // $TradeAmt        = intval($postData['TradeAmt']);

  if ($RtnCode === 1) {
      // 付款成功，更新訂單
    $sql = "
      UPDATE orders 
      SET payment_status = 1
      WHERE tracking_number = '{$MerchantTradeNo}'
      LIMIT 1
    ";
    $mysqli->query($sql);
  }

  // ----------------------
  // 一定要回傳給綠界
  // ----------------------
  echo '1|OK';

?>
