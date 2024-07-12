<?php

declare(strict_types=1);

class Feed {

  private const string DOMAIN = "https://zenn.dev";

  private readonly array $topicNames;

  public function __construct() {
    $this->topicNames = [
      "php",
      "javascript",
    ];
  }

  public function run(): void {
    foreach ($this->topicNames as $topicName) {
      $response = $this->fetch($topicName);
      $this->xml($response);
    }
  }

  private function fetch(string $topicName): bool|string {
    // 新規cURLリソースを作成
    $ch = curl_init();

    $url = sprintf("%s/topics/%s/feed", self::DOMAIN, $topicName);
    curl_setopt($ch, CURLOPT_URL, $url);

    // curl_exec()の戻り値を文字列で返す
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // タイムアウト（秒）
    $timeout = 3;
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    // 実行
    $response = curl_exec($ch);

    if ($response === false) {
      // cURLリソースを閉じる
      curl_close($ch);
//      throw new Exception("curl error: " . curl_error($ch));
    }

    // 最後に受け取ったHTTPコード取得する
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (400 <= $httpCode) {
      // cURLリソースを閉じる
      curl_close($ch);
//      throw new Exception("http code: " . $httpCode);
    }

    return $response;
  }

  private function xml($response): void {

      $response = "<root><item>Item</item></root>";
//    $response = "<root><item>Item</root>";

    // エラー処理を有効にする
    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($response);
    if ($xml === false) {
      $errors = libxml_get_errors();

      foreach ($errors as $error) {
        $errorMessage = "";
        switch ($error->level) {
          case LIBXML_ERR_WARNING:
            $errorMessage .= "Warning $error->code: ";
            break;
          case LIBXML_ERR_ERROR:
            $errorMessage .= "Error $error->code: ";
            break;
          case LIBXML_ERR_FATAL:
            $errorMessage .= "Fatal Error $error->code: ";
            break;
        }


        $errorMessage .= trim($error->message) . "\n<br>";

        echo $errorMessage;
      }

      // エラーハンドルをクリアする
      libxml_clear_errors();

      return;
    }

    $this->output($xml);

  }

  function displayXmlErrors(): void {

  }

  private function output(SimpleXMLElement $xml): void {
    echo "<hr>";
    printf("トピック: %s", $xml->channel->title ?? "トピック取得失敗");
    echo "<hr>";

    if (!isset($xml->channel->item)) {
      echo "タイトルの取得に失敗しました";
      return;
    }

    foreach ($xml->channel->item as $item) {
      printf(
        "<a href='%s' target=_blank>%s</a><br>",
        htmlspecialchars((string)$item->link, ENT_QUOTES),
        htmlspecialchars((string)$item->title, ENT_QUOTES),
      );
    }
  }

}