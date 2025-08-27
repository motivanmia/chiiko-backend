<?php
// ...你的共用檔案引入...
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/conn.php';
require_once __DIR__ . '/../../common/cors.php';
require_once __DIR__ . '/../../common/functions.php';

require_method('POST');

/**
 * 輔助函式：從 HTML 中提取所有圖片的檔案名稱
 * @param string $html
 * @return array
 */
function extractImageFilenames($html) {
    preg_match_all('/<img[^>]+src="([^">]+)"/', $html, $matches);
    $urls = $matches[1] ?? [];
    
    // 使用 basename() 函式，只保留路徑的最後一個部分（檔名）
    $filenames = array_map('basename', $urls);
    return $filenames;
}

/**
 * 輔助函式：從 HTML 內容中移除所有標籤，只保留純文字
 * @param string $html
 * @return string
 */
function stripHtmlTags($html) {
    if (!$html) return '';
    // ✨ 修正：先將 </p> 替換為換行符號，確保分段被保留
    $text = str_replace('</p>', "\n", $html);
    // 然後再移除所有剩餘的 HTML 標籤
    $text = strip_tags($text);
    return trim($text);
}

// 取得前端傳來的 JSON 資料
$json_data = file_get_contents("php://input");
$data = json_decode($json_data, true);

// 資料驗證
$required_fields = ['name', 'product_category_id', 'unit_price', 'is_active', 'product_info'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || (empty($data[$field]) && $data[$field] !== '0')) {
        send_json(['error' => "缺少必要欄位： {$field}"], 400);
    }
}

// 準備資料庫資料
$name = $data['name'];
$product_category_id = intval($data['product_category_id']);
$unit_price = intval($data['unit_price']);
$is_active = intval($data['is_active']);
$preview_image = $data['preview_image'] ?? '';
$product_info_html = $data['product_info'] ?? '';
$product_notes_html = $data['product_notes'] ?? '';

// 從 HTML 中提取圖片檔案名稱
$product_image_filenames = extractImageFilenames($product_info_html);
$content_image_filenames = extractImageFilenames($product_notes_html);

// 從 HTML 中提取純文字內容
$product_info_text = stripHtmlTags($product_info_html);
$product_notes_text = stripHtmlTags($product_notes_html);

// 將圖片檔案名稱陣列轉為 JSON 字串，準備存入資料庫
$product_image_json = json_encode($product_image_filenames);
$content_image_json = json_encode($content_image_filenames);

// 執行加入資料庫
$sql = "INSERT INTO products (
    product_category_id,
    name,
    preview_image,
    unit_price,
    is_active,
    product_images,
    content_images,
    product_notes,
    product_info
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $mysqli->prepare($sql);

$stmt->bind_param(
    "ississsss",
    $product_category_id,
    $name,
    $preview_image,
    $unit_price,
    $is_active,
    $product_image_json,
    $content_image_json,
    $product_notes_text,
    $product_info_text
);

// 回傳結果
if ($stmt->execute()) {
    $data_return = [
        'status' => 'success',
        'message' => '商品新增成功',
        'product_id' => $stmt->insert_id,
        'category_id' => $product_category_id,
        'name' => $name,
        'price' => $unit_price,
        'is_active' => $is_active,
        'product_notes' => $product_notes_text,
        'product_info' => $product_info_text,
        'image' => [
            'preview_image' => $preview_image,
            'product_image' => $product_image_filenames,
            'content_image' => $content_image_filenames,
        ]
    ];
    send_json($data_return, 201);
} else {
    send_json(['error' => '資料庫寫入失敗: ' . $stmt->error], 500);
}

$stmt->close();
$mysqli->close();
?>