<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/cors.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

try {
  if (!isset($_FILES['files'])) {
    throw new Exception('No files uploaded.');
  }

  // 支援單/多檔
  $names = (array) $_FILES['files']['name'];
  $tmps  = (array) $_FILES['files']['tmp_name'];
  $types = (array) $_FILES['files']['type']; // 來自瀏覽器的宣告，當作最後備援
  $sizes = (array) $_FILES['files']['size'];
  $errs  = (array) $_FILES['files']['error'];
  $count = count($names);

  // 專案根 /uploads
  $uploadDir = dirname(__DIR__, 2) . '/uploads/';
  if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    throw new Exception('Cannot create upload directory.');
  }

  $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  $maxSize = 8 * 1024 * 1024;

  // 可用再建立 finfo
  $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;

  $result = [];

  for ($i = 0; $i < $count; $i++) {
    $name = $names[$i];
    $tmp  = $tmps[$i];
    $size = (int) $sizes[$i];
    $err  = (int) $errs[$i];

    if ($err !== UPLOAD_ERR_OK) throw new Exception("Upload error for file {$name}, code={$err}");
    if (!is_uploaded_file($tmp)) throw new Exception("Invalid temp file for {$name}");

    // 👇 多層後備：finfo → mime_content_type → getimagesize → $_FILES['type']
    $detected = '';
    if ($finfo) {
      $detected = $finfo->file($tmp) ?: '';
    }
    if (!$detected && function_exists('mime_content_type')) {
      $detected = @mime_content_type($tmp) ?: '';
    }
    if (!$detected) {
      $imgInfo = @getimagesize($tmp);
      if ($imgInfo && !empty($imgInfo['mime'])) {
        $detected = $imgInfo['mime'];
      }
    }
    if (!$detected) {
      $detected = $types[$i] ?? '';
    }

    if (!in_array($detected, $allowedMimes, true)) {
      throw new Exception("Invalid mime: {$detected}");
    }
    if ($size > $maxSize) {
      throw new Exception("File too large: {$name}");
    }

    // 唯一檔名
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $uniq = $safe . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

    $dest = $uploadDir . $uniq;
    if (!move_uploaded_file($tmp, $dest)) {
      throw new Exception("Failed to move uploaded file: {$name}");
    }

    $url = rtrim(IMG_BASE_URL, '/') . '/' . $uniq;

    $result[] = [
      'original' => $name,
      'filename' => $uniq,
      'url'      => $url,
      'size'     => $size,
      'mime'     => $detected,
    ];
  }

  echo json_encode(['status' => 'success', 'files' => $result], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['status' => 'fail', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
