<?php
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/config.php';



$channel_id = '2008003983';
$channel_secret = 'a4d7cfda41ae3661d5034dabd4dcc7e7'; // 


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => '無效的請求方法。']);
    exit;
}

// 從前端接收 JSON 資料
$input = json_decode(file_get_contents('php://input'), true);
$code = $input['code'] ?? '';
$redirect_uri = $input['redirect_uri'] ?? '';

error_log("Received redirect_uri: " . $redirect_uri);


// 檢查是否收到授權碼
if (empty($code)) {
    http_response_code(400);
    echo json_encode(['message' => '缺少授權碼。']);
    exit;
}

try {
    // 1. 使用授權碼換取 Access Token
    $token_url = 'https://api.line.me/oauth2/v2.1/token';
    $post_data = http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'client_id' => $channel_id,
        'client_secret' => $channel_secret,
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //禁用SSL憑證檢驗
    curl_setopt($ch,CURLOPT_CAINFO,__DIR__.'/../../certs/cacert.pem');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $token_response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    $token_data = json_decode($token_response, true);

    if (isset($token_data['error'])) {
        throw new Exception($token_data['error_description'] ?? '換取 Access Token 失敗。');
    }
    $access_token = $token_data['access_token'];

    // 2. 使用 Access Token 取得使用者資料
    $profile_url = 'https://api.line.me/v2/profile';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profile_url);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//禁用SSL憑證檢驗
    curl_setopt($ch,CURLOPT_CAINFO, __DIR__ . '/../../certs/cacert.pem');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $profile_response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    $profile_data = json_decode($profile_response, true);

    if (isset($profile_data['error'])) {
        throw new Exception($profile_data['message'] ?? '獲取使用者資料失敗。');
    }

    // 3. 處理使用者資料並寫入資料庫
    $line_user_id = $mysqli->real_escape_string($profile_data['userId']);
    $display_name = $mysqli->real_escape_string($profile_data['displayName']);
    // 取得原始的圖片 URL
    $original_picture_url = $profile_data['pictureUrl'];
    // 將 URL 字串截斷為 60 個字元（或你資料庫欄位的最大長度）
    $truncated_picture_url = substr($original_picture_url, 0, 60);
    // 將截斷後的字串寫入資料庫
    $picture_url = $mysqli->real_escape_string($truncated_picture_url);

    $sql_check = "SELECT user_id, name, nickname, image FROM users WHERE account = '$line_user_id'";
    $result_check = $mysqli->query($sql_check);

    $user_record = [];
    $is_new_user = false;

    if ($result_check->num_rows > 0) {
        $user_record = $result_check->fetch_assoc();
        $message = '使用者已存在，成功登入。';
    } else {
        $sql_insert = "
            INSERT INTO users (name, nickname, account, image,password ,created_at) 
            VALUES ('$display_name', '$display_name', '$line_user_id', '$picture_url','',NOW())
        ";
        $mysqli->query($sql_insert);

        if ($mysqli->affected_rows > 0) {
            $is_new_user = true;
            $user_record = [
                'user_id' => $mysqli->insert_id,
                'name' => $display_name,
                'nickname' => $display_name,
                'image' => $picture_url,
            ];
            $message = '新使用者，已寫入資料庫，成功登入。';
        } else {
            throw new Exception("資料庫插入失敗。");
        }
    }
    
    $_SESSION['is_logged_in'] = true;
    $_SESSION['user_id'] = $user_record['user_id'];
    $_SESSION['user_name'] = $user_record['name'];
    $_SESSION['nickname'] = $user_record['nickname'];
    $_SESSION['image'] = $user_record['image'];

    echo json_encode([
        'success' => true,
        'message' => $message,
        'user' => [
            'id' => $user_record['user_id'],
            'name' => $user_record['name'],
            'nickname' => $user_record['nickname'],
            'image' => $user_record['image'],
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'message' => '伺服器錯誤: ' . $e->getMessage(),
        'success' => false
    ]);
}
?>