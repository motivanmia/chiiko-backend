<?php
  function generateCheckMacValue($params, $HashKey, $HashIV, $forCallback = false) {
    if ($forCallback) {
      unset($params['CheckMacValue']);
    }

    ksort($params);
    $checkStr = "HashKey=$HashKey&" . urldecode(http_build_query($params)) . "&HashIV=$HashIV";
    $checkStr = strtolower(urlencode($checkStr));
    $checkStr = str_replace('%2d', '-', $checkStr);
    $checkStr = str_replace('%5f', '_', $checkStr);
    $checkStr = str_replace('%2e', '.', $checkStr);
    $checkStr = str_replace('%21', '!', $checkStr);
    $checkStr = str_replace('%2a', '*', $checkStr);
    $checkStr = str_replace('%28', '(', $checkStr);
    $checkStr = str_replace('%29', ')', $checkStr);
    $checkStr = str_replace('%20', '+', $checkStr);

    return strtoupper(hash("sha256", $checkStr));
}
?>
