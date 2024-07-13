<?php

declare(strict_types=1);

class Feed {

  private const string DOMAIN = "https://zenn.dev";

  private readonly array $topicNames;

  private string $topicUrl;

  private array $feedData = [];

  public function __construct() {
    $this->topicNames = [
      "php",
      "javascript",
    ];
  }

  /**
   * @return void
   */
  public function run(): void {
    foreach ($this->topicNames as $topicName) {
      $response = $this->fetch($topicName);

      if (!$response) {
        printf("ネットワークのに失敗しました<br>\n");
        continue;
      }

      $xmlData = $this->loadXml($response);
      if (!$xmlData) {
        printf("フィードの取得に失敗しました<br>\n");
        continue;
      }

      $this->setFeedData($xmlData, $topicName);
    }

    $contents = $this->createFeedContentsHtml();
  }

  /**
   * @param string $topicName
   * @return bool|string
   */
  private function fetch(string $topicName): bool|string {
    // 新規cURLリソースを生成する
    $ch = curl_init();

    // 取得するフィードのURLを生成する
    $this->topicUrl = sprintf("%s/topics/%s/feed", self::DOMAIN, $topicName);
    curl_setopt($ch, CURLOPT_URL, $this->topicUrl);

    // curl_exec()の戻り値を文字列で返す
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // タイムアウト（秒）をセットする
    $timeout = 3;
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    // 実行
    $response = curl_exec($ch);

    if ($response === false) {
      // cURLリソースを閉じる
      curl_close($ch);
      return false;
    }

    // 最後に受け取ったHTTPコード取得する
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (400 <= $httpCode) {
      // cURLリソースを閉じる
      curl_close($ch);
      return false;
    }

    return $response;
  }

  /**
   * @param $response
   * @return void
   */
  private function loadXml($response): bool|simpleXMLElement {
//      $response = "<root><item>Item</item></root>";
//    $response = "<root><item>Item</root>";

    // エラー処理を有効にする
    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($response);

    if ($xml === false) {
      $errors = libxml_get_errors();
      $this->displayErrors($errors);

      // エラーハンドルをクリアする
      libxml_clear_errors();
      return false;
    }

    return $xml;
//    $this->displayContent($xml);
  }

  /**
   * @param array $errors
   * @return void
   */
  function displayErrors(array $errors): void {
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
  }

  /**
   * @param simpleXMLElement $xml
   * @param string $topicName
   * @return void
   *
   * TODO:エラー処理
   */
  private function setFeedData(simpleXMLElement $xml, string $topicName): void {
    // `item`要素がない場合は`foreach`で`warning`が出るのでここで止める
//    if (!isset($xml->channel->item)) {
//      echo "フィードの取得に失敗しました<hr>";
//      return;
//    }

    $data = [];
    $data["topic"] = htmlspecialchars(
      (string)$xml->channel->title,
      ENT_QUOTES
    );

    $feed = [];
    foreach ($xml->channel->item as $item) {
      $tmp = [];
      $tmp["link"] = (string)$item->link;
      $tmp["title"] = (string)$item->title;
      $feed[] = $tmp;
    }
    $data["feed"] = $feed;
//    print_r($data);

    $this->feedData[$topicName] = $data;
  }

  private function createFeedContentsHtml(): string {
    $contents = "";

    foreach ($this->topicNames as $topicName) {
      $tmpContents = [];
      $titleList = [];

      foreach ($this->feedData[$topicName]["feed"] as $feedData) {
        $titleList[] = sprintf(
          "<li><a href='%s' target=_blank>%s</a></li>",
          htmlspecialchars($feedData["link"], ENT_QUOTES),
          htmlspecialchars($feedData["title"], ENT_QUOTES),
        );
      }

      $tmpContents["topic"] = sprintf(
        "<h3>%s</h3>",
        $this->feedData[$topicName]["topic"],
      );

      $tmpContents["titleList"] = sprintf(
        "<ul>%s</ul>",
        implode("\n", $titleList)
      );

      $contents .= $tmpContents["topic"];
      $contents .= $tmpContents["titleList"];
    }

    return $contents;
  }

  /**
   * @param SimpleXMLElement $xml
   * @return void
   */
  private function displayContent(SimpleXMLElement $xml): void {
    printf(
      "<h3><a href='%s' target=_blank>%s</a></h3>",
      htmlspecialchars($this->topicUrl, ENT_QUOTES),
      htmlspecialchars((string)$xml->channel->title, ENT_QUOTES),
    );

    // `item`要素がない場合は`foreach`で`warning`が出るのでここで止める
    if (!isset($xml->channel->item)) {
      echo "フィードの取得に失敗しました<hr>";
      return;
    }

    echo "<ul>";
    foreach ($xml->channel->item as $item) {
      printf(
        "<li><a href='%s' target=_blank>%s</a></li>",
        htmlspecialchars((string)$item->link, ENT_QUOTES),
        htmlspecialchars((string)$item->title, ENT_QUOTES),
      );
    }
    echo "</ul>";
  }
}