<?

$headers = '

	{}&&
	{
	"requestProperties": {
	"catEntryId": "1349621",
	"orderId": ["1251798909"],
	"originalStoreId": "10051",
	"quantity": "1",
	"orderItemId": ["1326191359"],
	"storeId": "10051"
}
	,"oscTag_pr": ""
	
	}';

	$matches = stripos($headers, '"catEntryId": "' . "1349621" . '"') !== false;
		// preg_match_all('/Set-Cookie: (.*?);/', $headers, $matches);

		var_dump($matches);