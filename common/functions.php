<?php
  // 統一輸出 JSON
function send_json($data, $status_code = 200) {
    // 判斷是否完整網址
    $is_absolute = function ($val) {
        // return is_string($val) && preg_match('#^(https?:)?//#i', $val) || str_starts_with($val, 'data:');
    };

    // 加上 IMG_BASE_URL
    $prefix = function ($val) use ($is_absolute) {
        if (!is_string($val) || $val === '' || $is_absolute($val)) {
            return $val;
        }
        return rtrim(IMG_BASE_URL, '/') . '/' . ltrim($val, '/');
    };

    // 嘗試把JSON字串轉回PHP值（失敗就回null）
    $try_json_decode = function ($val) {
        if (!is_string($val)) return null;
        $trim = trim($val);
        if ($trim === '' || ($trim[0] !== '[' && $trim[0] !== '{' && $trim[0] !== '"')) return null;
        $decoded = json_decode($trim, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
    };

    // 遞迴處理
    $process = function ($item) use (&$process, $prefix, $try_json_decode) {
        if (is_array($item)) {
            $out = [];
            foreach ($item as $k => $v) {
                if ($k === 'image') {
                    // 1) 陣列：逐一加前綴
                    if (is_array($v)) {
                        $out[$k] = array_map($prefix, $v);
                    }
                    // 2) 字串：可能是檔名或JSON陣列字串
                    elseif (is_string($v)) {
                        $maybe = $try_json_decode($v);
                        if (is_array($maybe)) {
                            // JSON 陣列字串 → 轉陣列後逐一加前綴
                            $out[$k] = array_map($prefix, $maybe);
                        } else {
                            // 單一檔名字串 → 加前綴
                            $out[$k] = $prefix($v);
                        }
                    } else {
                        $out[$k] = $v; // 其他型別照原樣
                    }
                } else {
                    // 其他欄位照舊遞迴（不動 content 等）
                    $out[$k] = $process($v);
                }
            }
            return $out;
        }
        return $item;
    };

    $data = $process($data);

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
