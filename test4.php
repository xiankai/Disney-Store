<?

require 'vendor/autoload.php';
$client = new Predis\Client();
var_dump($client->get('Frozen Blu-ray Collector\'s Edition'));