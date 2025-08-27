<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

// 這個是正確且唯一的函式
function parseHtmlContent($html) {
    if (!$html) {
        return ['text' => '', 'images' => []];
    }
    
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $images = [];
    $imageTags = $dom->getElementsByTagName('img');
    
    foreach ($imageTags as $tag) {
        $src = $tag->getAttribute('src');
        if ($src) {
            $parts = explode('/', $src);
            $filename = end($parts);
            $images[] = $filename;
        }
    }
    
    foreach ($imageTags as $tag) {
        $tag->parentNode->removeChild($tag);
    }
    
    $text = '';
    $paragraphs = $dom->getElementsByTagName('p');
    foreach ($paragraphs as $p) {
        $text .= $p->textContent . "\n";
    }
    
    $text = trim($text);
    
    return ['text' => $text, 'images' => $images];
}

// ---
// 以下為 API 請求處理邏輯
// ---

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    // 呼叫你的圖片上傳函式
    $filename = handleFileUpload($_FILES['image']);

    // 檢查回傳值，並建立 JSON 物件
    if ($filename) {
        // 使用定義好的常數來組合完整的 URL
        $full_url = IMG_BASE_URL . '/' . $filename;
        echo json_encode(["status" => "success", "url" => $full_url]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "檔案上傳失敗"]);
    }
    // 確保程式碼在這裡結束，不會再有其他輸出
    die();
}

require_method('PATCH');

if (!isset($_GET['product_id'])) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "商品 ID 缺失"]));
}
$id = $_GET['product_id'];

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !is_array($data) || count($data) === 0) {
    http_response_code(400);
    die(json_encode(["status" => "error", "message" => "沒有提供更新內容"]));
}

// 在這裡處理編輯器的內容
$product_info_parsed = parseHtmlContent($data['product_info'] ?? '');
$product_notes_parsed = parseHtmlContent($data['product_notes'] ?? '');

$set_clause = [];

foreach ($data as $key => $value) {
    if ($key !== 'product_info' && $key !== 'product_notes') {
        if (is_string($value)) {
            $set_clause[] = "`" . $key . "` = '" . $mysqli->real_escape_string($value) . "'";
        } else {
            $set_clause[] = "`" . $key . "` = " . $value;
        }
    }
}

$set_clause[] = "`product_info` = '" . $mysqli->real_escape_string($product_info_parsed['text']) . "'";
$set_clause[] = "`product_notes` = '" . $mysqli->real_escape_string($product_notes_parsed['text']) . "'";

$product_images_json = json_encode($product_info_parsed['images']);
$content_images_json = json_encode($product_notes_parsed['images']);

$set_clause[] = "`product_images` = '" . $mysqli->real_escape_string($product_images_json) . "'";
$set_clause[] = "`content_images` = '" . $mysqli->real_escape_string($content_images_json) . "'";

$sql = "UPDATE products SET " . implode(", ", $set_clause) . " WHERE product_id = '" . $mysqli->real_escape_string($id) . "'";

if ($mysqli->query($sql) === TRUE) {
    if ($mysqli->affected_rows > 0) {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "商品已成功更新"]);
    } else {
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "找不到該商品或沒有內容被更新"]);
    }
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "更新失敗: " . $mysqli->error]);
}

$mysqli->close();
?>