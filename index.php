<?php

require 'config.php';

//Check if there is slug cache data
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$bIsRedisWork = empty($redis->ping()) ? false : true;
if (!$bIsRedisWork) {
	die("Redis does not work.");
}


$sCacheKey = "s_". $_GET['s']; 
$sSlugCacheData = $redis->get($sCacheKey);
if (!empty($sSlugCacheData)) {    
	header('Location: ' . $sSlugCacheData);	
	exit;
}


$url = "";
if (isset($_GET['s'])) {
	
	$slug = trim($_GET['s']);
	$slug = preg_replace('/[^a-z0-9]/si', '', $slug);
	
	$db = new MySQLi(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
	$db->set_charset('utf8mb4');

	$escapedSlug = $db->real_escape_string($slug);
	$redirectResult = $db->query('SELECT url FROM redirect WHERE slug = "' . $escapedSlug . '"');

	if ($redirectResult && $redirectResult->num_rows > 0) {
		$url = $redirectResult->fetch_object()->url;
	} 

	$db->close();

	if (!empty($url)) {
		$redis->set($sCacheKey, $url);
	}
	
}



if (empty($url)) {
	echo "<font size = '4' color = 'red'>抱歉，您輸入的URL無法轉址，請重新產生有效的URL。<a href = 'http://short.com/getShortUrlForm.html'> http://short.com/getShortUrlForm.html</a></font>";
	exit;
}



header('Location: ' . $url);	

?>
