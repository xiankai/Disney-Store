<?

require 'vendor/autoload.php';
require 'DisneyStore.php';
require 'Redis.php';

$redis = new Redis(300);
$DisneyStoreUS = new DisneyStore('http://www.disneystore.com', $redis);
$DisneyStoreUS->init();