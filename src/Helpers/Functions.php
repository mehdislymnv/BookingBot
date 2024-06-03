<?php
namespace Helpers;

function sendMessage($chatId, $message) {
    $url = $GLOBALS['website']."/sendMessage?chat_id=".$chatId."&text=".urlencode($message);
    file_get_contents($url);
}
?>
