<?php

declare(strict_types=1);

require_once "./fetchFeed.php";
$cls = new fetchFeed();
echo $cls->url;

exit();

$feedUrl = "https://zenn.dev/topics/php/feed";

// 新規cURLリソースを作成
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $feedUrl);

// curl_exec()の戻り値を文字列で返す
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// タイムアウト（秒）
$timeout = 1;
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

// 実行
$response = curl_exec($ch);

if ($response === false) {
  echo "curl error: " . curl_error($ch) . "\n";
  // cURLリソースを閉じる
  curl_close($ch);
  exit();
}

// 最後に受け取ったHTTPコード取得する
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($httpCode !== 200) {
  echo "http code: " . $httpCode . "\n";

  // cURLリソースを閉じる
  curl_close($ch);
  exit();
}

$response = "<root><item>Item</item></root>";

try {
  // エラー処理を有効にする
  libxml_use_internal_errors(true);

  $feed = new simpleXMLElement($response);

  printf("Title: %s", $feed->channel->title);
  echo "<hr>";

  if(!isset($feed->channel->item)){
    throw new Exception("Failed to parse XML data.\n");
  }

  foreach ($feed->channel->item as $item) {
    printf(
      "<a href='%s' target=_blank>%s</a><br>",
      htmlspecialchars((string)$item->link, ENT_QUOTES),
      htmlspecialchars((string)$item->title, ENT_QUOTES),
    );
  }
} catch (Exception $e) {
  echo $e->getMessage();
} finally {
  // エラーハンドルをクリアする
  libxml_clear_errors();
}