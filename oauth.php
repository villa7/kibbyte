<?php
session_start();

require_once 'includes/OAuth2/Google/autoload.php';
$client = new Google_Client();
$client->setAuthConfigFile('includes/OAuth2/client_secret.json');
$client->setAccessType('offline');//required to get a refresh token
$client->setApprovalPrompt('auto');
//$client->addScope("https://www.googleapis.com/auth/plus.login"); //openid profile email
$client->addScope("profile https://www.googleapis.com/auth/drive");
$client->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/kibbyte/oauth.php');
if (isset($_SESSION['access_token'])) $client->setAccessToken($_SESSION['access_token']);

if (isset($_GET['error'])) {
	echo 'error: ' . $_GET['error'];
	die();
}
if (isset($_GET['code'])) {
	try {
		$client->authenticate($_GET['code']);
	} catch (Exception $e) {
		echo 'Authentication Failed: ' . $e;
		header("Location: http://" . $_SERVER['HTTP_HOST'] . "/kibbyte/oauth.php");
		die();
	}
	$access_token = $client->getAccessToken();
	$refresh_token = $client->getRefreshToken(); // $client->refreshToken($token)
	$_SESSION['access_token'] = $access_token;
	
	$tokeninfo = json_decode($access_token, true);

	$access_token = $tokeninfo['access_token'];
	$_SESSION['token'] = $access_token;
	$json = file_get_contents('https://www.googleapis.com/oauth2/v1/userinfo?access_token='.$access_token);
	//$json = file_get_contents("https://www.googleapis.com/plus/v1/people/".$access_token);
	$userinfo = json_decode($json, true);
	$_SESSION['user_id'] = $userinfo['id'];
	$_SESSION['user_name'] = $userinfo['name'];
	$_SESSION['first_name'] = $userinfo['given_name'];
	$_SESSION['last_name'] = $userinfo['family_name'];
	//$_SESSION['user_email'] = $userinfo['email'];
	$_SESSION['user_picture'] = $userinfo['picture'];
	$_SESSION['user_locale'] = $userinfo['locale'];

	if ($refresh_token !== null) {
		//save refresh tokens in some better way than this later
		$userstore = array();
		$us = file_get_contents('includes/OAuth2/user_refresh_tokens.json');
		if ($us != '') {
			$userstore = json_decode($us, true);
		}
		$userstore[$userinfo['id']] = $refresh_token;
		$us = json_encode($userstore);
		file_put_contents('includes/OAuth2/user_refresh_tokens.json', $us);
	} else {
		$userstore = array();
		$us = file_get_contents('includes/OAuth2/user_refresh_tokens.json');
		$userstore = json_decode($us, true);
		$refresh_token = $userstore[$userinfo['id']];
	}

	if ($refresh_token == null) { //if there was no refresh token, force reauth
		$client->setApprovalPrompt('force');
		$auth_url = $client->createAuthUrl();
		header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
	}

	$_SESSION['refresh_token'] = $refresh_token;

	header("Location: http://" . $_SERVER['HTTP_HOST'] . "/kibbyte");
	die();
}
if (isset($_GET['revoke'])) {
	$client->revokeToken();
	foreach ($_SESSION as $val) {
		$val = null;
	}
	session_destroy();
	header("Location: http://" . $_SERVER['HTTP_HOST'] . "/kibbyte");
	die();
}
if (isset($_GET['logout'])) {
	foreach ($_SESSION as $val) {
		$val = null;
	}
	session_destroy();
	header("Location: http://" . $_SERVER['HTTP_HOST'] . "/kibbyte");
	die();
}

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
