<?php
  session_start();
  require_once __DIR__ . '/config.php';
  require_once __DIR__ . '/functions.php';

  try {
    $mysqli = new mysqli(
      DB_HOST,
      DB_USER,
      DB_PSW,
      DB_NAME,
      DB_PORT
    );

    // 設定連線編碼與時區
    $mysqli->query('SET NAMES UTF8');
    $mysqli->query('SET time_zone = "+8:00"');
  } catch (mysqli_sql_exception $e) {
    send_json([
        'status' => 'fail',
        'message' => '資料庫連線失敗',
        'error_code' => $e->getCode(),
        'error_msg' => $e->getMessage()
    ], 500);
  }
?>
