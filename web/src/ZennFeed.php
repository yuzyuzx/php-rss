<?php

declare(strict_types=1);

/**
 * Zennから特定のトピックのRSSフィードを取得して画面に表示するクラス
 */
class ZennFeed {

  /** @var string zennのドメイン */
  private const string DOMAIN = "https://zenn.dev";

  /** @var string[] 取得対象のトピック名を格納する配列 */
  private readonly array $topics;

  /** @var array 取得したフィードデータを格納する配列 */
  private array $feeds = [];

  /**
   * 取得対象のトピック名を初期化する
   */
  public function __construct() {
    $this->topics = [
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
    foreach ($this->topics as $topicName) {
      // トピックごとにフィードを取得
      $response = $this->fetch($topicName);
      if (!$response) {
        printf("ネットワークのに失敗しました<br>\n");
        continue;
      }

      // 取得したデータをXMLオブジェクトとして読み込む
      $xmlData = $this->parseXml($response);
      if (!$xmlData) {
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      // XMLに必要な要素が存在しているか確認する
      if (!$this->hasItemElement($xmlData)) {
        printf("{$topicName}フィードの取得に失敗しました<br>\n");
        continue;
      }

      // データを格納する
      $this->addFeedData($xmlData, $topicName);
    }

    // 全てのフィードが空の場合は終了
    if (empty(array_filter($this->feeds))) {
      return;
    }

    // 表示用のHTMLを生成する
    $contents = $this->createFeedHtml();

    // データを表示する
    $this->render($contents);
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
  private function parseXml(string $response): bool|simpleXMLElement {
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
  private function displayErrors(array $errors): void {
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
  private function hasItemElement(simpleXMLElement $xml): bool {
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
  private function addFeedData(simpleXMLElement $xml, string $topicName): void {
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

    $this->feeds[$topicName] = $data;
  }

  /**
   * フィードデータから表示用HTMLを生成する
   *
   * @return string 生成されたHTML
   */
  private function createFeedHtml(): string {
    $contents = "";

    foreach ($this->topics as $topicName) {
      $tmpContents = [];
      $titleList = [];

      foreach ($this->feeds[$topicName]["feed"] as $feedData) {
        $titleList[] = sprintf(
          "<li><a href='%s' target=_blank>%s</a></li>",
          $this->h($feedData["link"]),
          $this->h($feedData["title"]),
        );
      }

      $tmpContents["topic"] = sprintf(
        "<h3>%s</h3>",
        $this->h($this->feeds[$topicName]["topic"]),
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
  private function render(string $contents): void {
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