<?php
  require_once __DIR__ . '/config.php';

  try {
    $mysqli = new mysqli(
      DB_HOST,
      DB_USER,
      DB_PSW,
      DB_NAME,
      DB_PORT
    );

    $mysqli->query('SET NAMES UTF8');
    $mysqli->query('SET time_zone = "+8:00"');

    // echo '<h1 style="color: green;">連線成功。</h1>';
    // echo "<hr>";
    // echo '主機資訊：' . $mysqli->host_info;
    // echo '<br>';
    // echo 'MySQL 版本資訊：' . $mysqli->server_info;
    
  } catch (mysqli_sql_exception $e) {
    echo '<h1 style="color: red;">連線失敗。</h1>';
    echo "<hr>";
    echo '錯誤代碼：' . $e->getCode() . '<br>';
    echo '錯誤訊息：' . $e->getMessage() . '<br>';
    exit();
  }
?>
