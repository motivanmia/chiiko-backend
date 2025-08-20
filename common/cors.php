<?php
  // 定義允許的網域清單
  $allowedOrigins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'http://localhost:5174',
    'http://127.0.0.1:5174',
    'http://localhost:5175',
    'http://127.0.0.1:5175',
    'http://localhost:5176',
    'http://127.0.0.1:5176',
    'http://localhost:5177',
    'http://127.0.0.1:5177',
    'http://localhost:5178',
    'http://127.0.0.1:5178',
    'http://localhost:5179',
    'http://127.0.0.1:5179',
    'http://localhost:5180',
    'http://127.0.0.1:5180',
    'http://localhost:5181',
    'http://127.0.0.1:5181',
    'http://localhost:5182',
    'http://127.0.0.1:5182'
  ];

  // 取得發送請求的來源網域
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  
  // 檢查來源是否在允許的清單內
  if(in_array($origin, $allowedOrigins)){
    // 如果來源在清單內 則動態設定標頭
    header("Access-Control-Allow-Origin: $origin");
    // 加上這行讓瀏覽器傳送和接收session cookie
    header("Access-Control-Allow-Credentials: true");
  }
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");

  // 如果是 OPTIONS 預檢請求就直接回 200
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
  }
  header("Access-Control-Allow-Headers: Content-Type");
  // 允許前端傳送 Content-Type 標頭，以解決 CORS 預檢請求錯誤
?>
