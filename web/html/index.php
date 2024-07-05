<?php
declare(strict_types=1);

$feedUrl = "https://zenn.dev/topics/php/feed";
$fetchFeed = file_get_contents($feedUrl);

$feed = new simpleXMLElement($fetchFeed);
//print_r($feed);

printf("Title: %s", $feed->channel->title);
echo "<hr>";

//foreach ($feed->xpath('//channel/item') as $item) {
//  echo $item->title . "<br>";
//}
//echo "<hr>";

foreach ($feed->channel->item as $item) {
  printf("<a href='%s' target=_blank>%s</a><br>", $item->link, $item->title);
//  printf(
//    "<a href='%s' target=_blank>%s</a><br>",
//    $item->link,
//    $item->title
//  );
}
echo "<hr>";

$simplexmlLoadString = simplexml_load_string($fetchFeed);
foreach ($simplexmlLoadString->channel->item as $item) {
 echo $item->title . "<br>";
}
echo "<hr>";

$simpleLoadFile = simplexml_load_file($fetchFeed);
foreach ($simpleLoadFile->channel->item as $item) {
  echo $item->title . "<br>";
}