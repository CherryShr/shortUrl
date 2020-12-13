<?php
require 'config.php';
header('Content-Type: text/plain;charset=UTF-8');
//header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
$url = isset($_POST['url']) ? urldecode(trim($_POST['url'])) : '';

$aReturnData = [
	"sError" => "",
	"sUrl" => "",
];



$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$bIsRedisWork = empty($redis->ping()) ? false : true;
if (!$bIsRedisWork) {
	$aReturnData["sError"] = 'Redis does not work.';
	echo json_encode($aReturnData);
	exit;
}


$sCacheKey = "url_". $url; 
$sUrlCacheData = $redis->get($sCacheKey);
if (!empty($sUrlCacheData)) {    
	$aReturnData["sUrl"] = $sUrlCacheData;
	echo json_encode($aReturnData);
	exit;
}



if (in_array($url, array('', 'about:blank', 'undefined', 'http://localhost/'))) {
	$aReturnData["sError"] = 'Enter a URL.';
	echo json_encode($aReturnData);
	exit;
}

// If the URL is already a short URL on this domain, don’t re-shorten it
if (strpos($url, SHORT_URL) === 0) {
	$aReturnData["sUrl"] = $url;
	echo json_encode($aReturnData);
	exit;
}

function nextLetter(&$str) {
	$str = ('z' == $str ? 'a' : ++$str);
}

function getNextShortURL($s) {
	$a = str_split($s);
	$c = count($a);
	if (preg_match('/^z*$/', $s)) { // string consists entirely of `z`
		return str_repeat('a', $c + 1);
	}
	while ('z' == $a[--$c]) {
		nextLetter($a[$c]);
	}
	nextLetter($a[$c]);
	return implode($a);
}

$db = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
$db->set_charset('utf8mb4');

$url = $db->real_escape_string($url);




$result = $db->query('SELECT slug FROM redirect WHERE url = "' . $url . '" LIMIT 1');
if ($result && $result->num_rows > 0) { // If there’s already a short URL for this URL
	$aReturnData["sUrl"] = SHORT_URL . $result->fetch_object()->slug;
	echo json_encode($aReturnData);
	exit;
	
} else {
	$result = $db->query('SELECT slug, url FROM redirect ORDER BY date DESC, slug DESC LIMIT 1');
	if ($result && $result->num_rows > 0) {
		$slug = getNextShortURL($result->fetch_object()->slug);
		if ($db->query('INSERT INTO redirect (slug, url, date, hits) VALUES ("' . $slug . '", "' . $url . '", NOW(), 0)')) {
			header('HTTP/1.1 201 Created');
			$db->query('OPTIMIZE TABLE `redirect`');
			
			$aReturnData["sUrl"] = SHORT_URL . $slug;
			$redis->set($sCacheKey, $aReturnData["sUrl"]);
			echo json_encode($aReturnData);
			exit;
		}
	}
}


?>