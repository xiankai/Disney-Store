<?

require 'vendor/autoload.php';
$client = new Predis\Client();
$client->set('test', true);
$client->expire('test', 100);
$client->set('test2', true);
$client->expire('test2', 100);
$client->set('test3', true);
$client->expire('test3', 100);
$client->set('test4', true);
$client->expire('test4', 100);
$result = $client->keys('*');
var_dump($result);