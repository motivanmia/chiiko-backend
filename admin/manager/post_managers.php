<?php
  require_once __DIR__ . '/../../common/conn.php';
  require_once __DIR__ . '/../../common/cors.php';
  require_once __DIR__ . '/../../common/functions.php';

  require_method('POST');
  
  // 資料驗證
  $required_fields = ['name','account','password','role','status'];
  foreach($required_fields as $field){
    if(!isset($_POST[$field]) || empty($_POST[$field]) && $_POST[$field] !== '0'){
      http_response_code(400);
      echo json_encode(['error'=>"缺少必要欄位： {$field}"]);
      exit;
    }
  }

  // 準備資料庫資料
  $name = $_POST['name'];
  $account = $_POST['account'];
  $password = $_POST['password'];
  $role = $_POST['role'];
  $status = $_POST['status'];

  // 執行加入資料庫
  $sql = "INSERT INTO managers (
        name,
        account,
        password,
        role,
        status
        ) VALUES (?, ?, ?, ?, ?)";
  
  $stmt = $mysqli -> prepare($sql);

  $stmt -> bind_param(
    "sssss",
    $name,
    $account,
    $password,
    $role,
    $status
  );

  // 回傳結果
  if($stmt->execute()){
    http_response_code(201);
    send_json([
    'status' => 'success',
    'message' => '管理員新增成功',
    'manager_id' => $stmt->insert_id
  ]);
  }else{
    http_response_code(500);
    echo json_encode(['error' => '資料庫寫入失敗: ' . $stmt->error]);
  }

  $stmt -> close();
  $mysql -> close();
?>