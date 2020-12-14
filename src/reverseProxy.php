<?php

// connect to DB
$dbConnection =  new mysqli(getenv('DBHOST'), getenv('DBUSR'), getenv('DBPASS'), getenv('DBSCHEMA'), getenv('DBPORT'));
// if we cant connect, respond with server error
if ($dbConnection->connect_error) {
	respondWith("HTTP/1.1 503 Site Unavailable", 'Site temporarily unavailable.');
	exit;
}

// check to make sure we have origin set
$myDbResults = $dbConnection->query("select origin from cache.info limit 0, 1");
if(! $myOrigin = $myDbResults->fetch_assoc()) {
	// if not, we ask to set it
	require_once(__DIR__ . '/setOrigin.php');
	exit;
}

// use the URL as the key (ie. path/to/my/page )
//   we have configured our .htaccess file to pass the path via query string
if(! $myKey = htmlspecialchars($_GET["q"]) ){
	// if we didnt have a path, we assume the homepage
	$myKey = '/';
}

// look-up in cache data store
$myDbResults = $dbConnection->query("select * from cache.dataStore where `key` = '$myKey'");

	////// CHECK IF REGENERATION IS REQUESTED 

if (isset($_GET['regenerate']) & $_GET['regenerate'] == 'true') {
	
	////// IF REGENERATION IS REQUESTED, INTERCEPT THE CACHE REQUEST
	////// AND RE-INSERT THE URL INTO THE DB FOR UPDATING
	////// KEEP THE REFRESH, APPEND THE URL WITHOUT THE QUERYSTRING TO THE REFRESH HEADER 
	////// TO REFRESH TO THE APPROPRIATE URL WITHOUT TRIGGERING REGENERATION AGAIN

	////// GERNERATE AN APPROPRIATE URL WHEN REFRESHING THE FRONT PAGE

		if ($myKey == '/') {
			$redirectURL = '/';
		} else {	
			$redirectURL = '/' . $myKey;
		}

		// send response
		respondWith("HTTP/1.1 404 Not Found\nRefresh: 10; url=$redirectURL\nContent-Type: text/html; charset=UTF-8", "Regenerating the page: <b>$myKey</b> as requested.</br></br>Page will automatically refresh", $myKey);

		// add to queue
		$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
} else {

	////// IF NO REGENERATION IS REQUESTED CONTINUE WITH NORMAL OPERATION

	// if we have data, 
	if($cacheRecord = $myDbResults->fetch_assoc()) {

		// send response
		respondWith($cacheRecord['header'], $cacheRecord['html'], $myKey);

		// check expiry 
		$now = date('YmdHis');
		if($now > $cacheRecord['expiry']) {
			// if expired, add to queue
			$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
		}

	// if we did not have data, serve a 404 (with refresh header) and queue page to get generated
	} else {
		// send response
		respondWith("HTTP/1.1 404 Not Found\nRefresh: 10;\nContent-Type: text/html; charset=UTF-8", "Generating cache for <b>$myKey</b></br></br>Page will automatically refresh", $myKey);
		// add to queue
		$dbConnection->query("insert into cache.queue (payload) values ('$myKey')");
	}

}



// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //
// helper functions
// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ //

////// ADD MYKEY TO FUNCTION
////// WE NEED THIS TO CREATE THE REGENERATION URL FOR THE BUTTON

function respondWith($header, $payload, $myKey) {
	// break up header into lines
	$headerLines = explode("\n",  $header);

	// send each header line
	foreach($headerLines as $h) {
		header($h);
	}
	
	////// INTERCEPTED THE OUTPUT
	////// CHECK TO SEE IF THE ADMIN QUERYSTRING IS SET
	////// IF SO, APPEND A REGENERATE BUTTON TO THE PAGE
	////// GERNERATE AN APPROPRIATE URL WHEN REGENERATING THE FRONT PAGE

	if ($myKey == '/') {
		$regenerationURL = '/?regenerate=true';
	} else {	
		$regenerationURL = '/' . $myKey . '?regenerate=true';
	}

	if ($_GET['admin'] == 'true') {
			$payload = $payload . '<a href="' . $regenerationURL . '"><div style="cursor: pointer;font-family: helvetica, sans-serif; font-size: 14px; background-color: #2973A2; color: white; border-radius: 8px; border: 1px solid white; padding: 8px 10px; position: fixed; bottom: 50px; left: 30px;">Regenerate Page</div></a>';
	}

	// send data
	echo $payload;
	return;
}
