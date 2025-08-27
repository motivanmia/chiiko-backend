<?php
  function linepay_signature($channelSecret, $method, $apiPath, $body, $nonce) {
    $msg = $channelSecret . $apiPath;
    if ($method === "POST" && !empty($body)) {
        $msg .= $body;
    }
    $msg .= $nonce;

    return base64_encode(hash_hmac('sha256', $msg, $channelSecret, true));
  }
?>
