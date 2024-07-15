<?php

declare(strict_types=1);

class Feed {

  private const string DOMAIN = "https://zenn.dev";

  private readonly array $topicNames;

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
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      if(!$this->isItemElement($xmlData)) {
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      $this->setFeedData($xmlData, $topicName);
    }

    if (empty(array_filter($this->feedData))) {
      return;
    }

    $contents = $this->createFeedContentsHtml();

    $this->displayContents($contents);
  }

  /**
   * @param string $topicName
   * @return bool|string
   */
  private function fetch(string $topicName): bool|string {
    // 新規cURLリソースを生成する
    $ch = curl_init();

    // 取得するフィードのURLを生成する
    $topicUrl = sprintf(
      "%s/topics/%s/feed",
      self::DOMAIN,
      $topicName
    );
    curl_setopt($ch, CURLOPT_URL, $topicUrl);

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
   * @return bool|simpleXMLElement
   */
  private function loadXml($response): bool|simpleXMLElement {
//     $response = "<root><item>Item</item></root>";
//    $response = "<root><item>Item</root>";
//    $response = "
//    <rss>
//      <channel>
//        <title>P'H'P</title>
//        <item>
//          <title>t\"it'le</title>
//          <link>https://zenn.dev/?id=3name=yuz</link>
//         </item>
//       </channel>
//    </rss>";

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
  }

  /**
   * @param array $errors
   * @return void
   */
  function displayErrors(array $errors): void {
    foreach ($errors as $error) {
      printf("Error code: %s, ", $error->code);
    }
  }

  private function isItemElement(simpleXMLElement $xml): bool {
    // `item`要素が存在する
    if (isset($xml->channel->item)) {
      return true;
    }

    return false;
  }

  /**
   * @param simpleXMLElement $xml
   * @param string $topicName
   * @return void
   *
   * TODO:エラー処理
   */
  private function setFeedData(simpleXMLElement $xml, string $topicName): void {

    $data = [];
    $data["topic"] = (string)$xml->channel->title;

    $feed = [];
    foreach ($xml->channel->item as $item) {
      $tmp = [];
      $tmp["link"] = (string)$item->link;
      $tmp["title"] = (string)$item->title;
      $feed[] = $tmp;
    }
    $data["feed"] = $feed;

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
          $this->h($feedData["link"]),
          $this->h($feedData["title"]),
        );
      }

      $tmpContents["topic"] = sprintf(
        "<h3>%s</h3>",
        $this->h($this->feedData[$topicName]["topic"]),
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

  private function displayContents(string $contents): void {
    $value = ['contents' => $contents];

    extract($value);

    $viewFile = '../views/view.php';
    if (file_exists($viewFile)) {
      include $viewFile;
    }
  }

  private function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }

}