<?php
  // 統一輸出 JSON
  function send_json($data, $status_code = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  // 只允許使用 $method
  function require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        send_json(['status' => 'fail', 'message' => "只允許 {$method} 請求"], 405);
    }
  }

  // 取得 query string
  function get_int_param($name) {
    return isset($_GET[$name]) ? intval($_GET[$name]) : null;
  }

  // 處理資料庫 result
  function db_query($mysqli, $sql) {
    $result = $mysqli->query($sql);
    if (!$result) {
        send_json(['status' => 'fail', 'message' => $mysqli->error], 500);
    }
    return $result;
  }
?>
