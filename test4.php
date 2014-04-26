<?

require 'vendor/autoload.php';
$client = new Predis\Client();
$client->set('test', true);
$client->expire('test', 100);