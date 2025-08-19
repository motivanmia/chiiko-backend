<?php
  // $allowed_origins = [
  //   "http://127.0.0.1:5500",
  //   "http://localhost:5500",
  // ];
  
  // $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

  // if (in_array($origin, $allowed_origins)) {
  //   header("Access-Control-Allow-Origin: " . $origin);
  // }
  header("Access-Control-Allow-Origin: *");
  
  header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");

  header("Access-Control-Allow-Headers: Content-Type");
  // 允許前端傳送 Content-Type 標頭，以解決 CORS 預檢請求錯誤
?>
