<?php

declare(strict_types=1);

/**
 * Zennから特定のトピックのRSSフィードを取得して画面に表示するクラス
 */
class ZennFeed {

  /** @var string zennのドメイン */
  private const string DOMAIN = "https://zenn.dev";

  /** @var string[] 取得対象のトピック名を格納する配列 */
  private readonly array $topicNames;

  /** @var array 取得したフィードデータを格納する配列 */
  private array $feedData = [];

  /**
   * 取得対象のトピック名を初期化する
   */
  public function __construct() {
    $this->topicNames = [
      "php",
      "javascript",
    ];
  }

  /**
   * フィードの取得と表示を実行するメインメソッド
   *
   * @return void
   */
  public function run(): void {
    foreach ($this->topicNames as $topicName) {
      // トピックごとにフィードを取得
      $response = $this->fetch($topicName);
      if (!$response) {
        printf("ネットワークのに失敗しました<br>\n");
        continue;
      }

      // 取得したデータをXMLオブジェクトとして読み込む
      $xmlData = $this->loadXml($response);
      if (!$xmlData) {
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      // XMLに必要な要素が存在しているか確認する
      if (!$this->isItemElement($xmlData)) {
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      // データを格納する
      $this->setFeedData($xmlData, $topicName);
    }

    // 全てのフィードが空の場合は終了
    if (empty(array_filter($this->feedData))) {
      return;
    }

    // 表示用のHTMLを生成する
    $contents = $this->createFeedContentsHtml();

    // データを表示する
    $this->displayContents($contents);
  }

  /**
   * フィードデータをネットワーク経由で取得する
   *
   * @param string $topicName トピック名
   * @return false|string 取得に成功した場合はフィードデータ、失敗した場合はfalse
   */
  private function fetch(string $topicName): bool|string {
    $ch = curl_init();

    // 取得するフィードのURLを生成する
    $topicUrl = sprintf(
      "%s/topics/%s/feed",
      self::DOMAIN,
      $topicName
    );

    curl_setopt($ch, CURLOPT_URL, $topicUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);

    // 実行
    $response = curl_exec($ch);

    // 取得失敗なら終了
    if ($response === false) {
      curl_close($ch);
      return false;
    }

    // HTTPステータスコードを取得
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    // レスポンスステータスコードが400以上なら終了
    if (400 <= $httpCode) {
      return false;
    }

    return $response;
  }

  /**
   * 取得したフィードデータ(XML)をSimpleXMLElementオブジェクトに変換する
   *
   * @param string $response フィードデータ
   *
   * @return false|simpleXMLElement
   * 変換に成功した場合はSimpleXMLElementオブジェクト、失敗した場合はfalse
   */
  private function loadXml(string $response): bool|simpleXMLElement {
//    $response = "<root><item>Item</item></root>";
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
      // エラー内容を出力する
      $this->displayErrors(libxml_get_errors());

      // エラーハンドルをクリアする
      libxml_clear_errors();
      return false;
    }

    return $xml;
  }

  /**
   * XMLパースエラーを画面に表示する
   *
   * @param array $errors XMLパースエラーの配列
   * @return void
   */
  function displayErrors(array $errors): void {
    foreach ($errors as $error) {
      printf("Error code: %s, ", $error->code);
    }
  }

  /**
   * xml内に`item`要素が存在するか確認する
   *
   * @param simpleXMLElement $xml
   * SimpleXMLElementオブジェクト
   *
   * @return bool
   * `item`要素が存在する場合はtrue、存在しない場合はfalse
   */
  private function isItemElement(simpleXMLElement $xml): bool {
    if (isset($xml->channel->item)) {
      return true;
    }

    return false;
  }

  /**
   * XMLデータを配列に変換し、フィードデータとして格納する
   *
   * @param simpleXMLElement $xml SimpleXMLElementオブジェクト
   * @param string $topicName トピック名
   * @return void
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

  /**
   * フィードデータから表示用HTMLを生成する
   *
   * @return string 生成されたHTML
   */
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

  /**
   * 生成したHTMLをビュー用のファイルに渡して表示する
   *
   * @param string $contents 生成されたHTML
   * @return void
   */
  private function displayContents(string $contents): void {
    $value = ['contents' => $contents];

    extract($value);

    $viewFile = '../views/view.php';
    if (file_exists($viewFile)) {
      include $viewFile;
    } else {
      include '../views/error.php';
    }
  }

  /**
   * htmlspecialchars()のラッパー関数
   *
   * @param string $s エスケープする文字列
   * @return string エスケープされた文字列
   */
  private function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
  }

}