<?php
include dirname(__FILE__)."/local_settings.php";
include dirname(__FILE__)."/php/session.php";

$handler = new DBsession($db, $_SERVER);
session_set_save_handler($handler, TRUE);
ini_set("session.cookie_secure", 1);
session_start();


include dirname(__FILE__)."/php/yubikey.php";

$yubikey = new YubiKey($_SERVER, $db);

$responseAjax = $yubikey->ajaxResponse();
if($responseAjax !== NULL){
	if(isset($responseAjax['header']) && !empty($responseAjax['header']) ){
		header($responseAjax['header']);
		unset($responseAjax['header']);
	}
	echo json_encode($responseAjax);
	exit();
}

$error = "";
$success = FALSE;
try {
	$success = $yubikey->signInOrRegisterUser($_POST);
}catch(Exception $e){
	$error = $e->getMessage();
}

$userData = $yubikey->getUserData();
$user = NULL;
// $otherUsers = NULL;
if( !empty($userData['id']) && $userData['id'] > 0){
	try {
		$user = $yubikey->getCurrentUserAndKeys();
		// $otherUsers = $yubikey->getOtherUsers();
	} catch(Exception $e){
		$error = $e->getMessage();
	}
}

$content = $yubikey->getContent();

?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="shortcut icon" href="static/images/favicon.ico" type="image/x-icon" />
<title>YubiKey register & sign-in</title>
<link rel="stylesheet" type="text/css" href="static/css/style.css" />
<script type="text/javascript" src="static/js/main.js"></script>
</head>
<body>
<?php if($content['logged_in']){ ?><div class="menu">
	<div class="cell"> <a href="#">YubiKey register</a> </div>
	<div class="cell"> <a href="#">Your keys</a> </div>
	<?php if(!empty($user['is_superuser']) && $user['is_superuser']){ ?><div class="cell"> <a href="#">User accounts</a> </div><?php } ?>
</div><?php
	} else { ?><div class="menu" style="height:20%"><div class="cell">YubiKey register and sign-in<br /></div></div><?php } ?>
<div class="main"><div class="cell">
<form method="POST" id="register"><?php
if( !$content['logged_in'] ){ ?>
	<input type="text" name="login" placeholder="Login" <?php
		if($content['keep_form_values']){
			?>value=<?php echo "\"", $userData['login'], "\"";
		}
		if($userData['has_keys']){
			?> readonly="readonly"<?php
		}
	?> /><br />
	<input type="password" name="pswd" placeholder="Password" <?php
		if($content['keep_form_values']){
			?>value=<?php echo "\"", $userData['password'], "\"";
		}
		if($userData['has_keys']){
			?> readonly="readonly"<?php
		}
	?> /><br />
	<?php if(!$userData['has_keys']){ ?><input type="button" value="Register" /><input type="submit" value="Sign-in" /><?php } ?>
<?php
} else {
	?><input type="button" name="yubikey" value="Register YubiKey" /><input type="submit" value="Logout" /><?php
}
?><input type="hidden" name="ssid" value="<?php echo session_id(); ?>" />
</form>
<div id="info" style="margin-top:1em;"></div>
</div>
<div id="keys" class="cell"><?php /* card of keys; */
	if( !empty($user) ){
		if($userData['has_keys']){
			var_dump($user['keys']);
		} else {
			?>You have no FIDO keys to display.<?php
		}
	} else {
		?>No content of keys<?php var_dump($user);
	} ?></div>
<?php if(!empty($user['is_superuser']) && $user['is_superuser']){ ?><div id="users" class="cell">Users content</div><?php } ?>
<div class="message" <?php if(count($content['message']) < 1){ ?>style="display:none"<?php } ?>><span class="close">&times;</span><span class="content"><?php
	if(count($content['message']) > 0) echo implode("<br />", $content['message']);
?></span></div>
</div>
<script type="text/javascript">YKey.ini(<?php
	if($userData['has_keys']){
		echo "{check: ", $userData['id'], "}";
	}
?>);</script><?php
if(isset($content['logged_in']) && $content['logged_in'] === TRUE){
	?><script type="module">
	//import("./static/js/another_file.js").then( (module) => {module.default.startMethod();});
	</script><?php
}
?><script id="data_json" type="application/json">[{"url":"https://webauthn.guide/", "desc":"navigator.credentials @ webauthn.guide;"},{"url":"https://developers.yubico.com/U2F/Libraries/Using_a_library.html", "desc":"Yubico dev - Using a U2F library;"},{"url":"https://developers.yubico.com/WebAuthn/WebAuthn_Developer_Guide/WebAuthn_Client_Registration.html", "desc":"Yubico - WebAuthn Client Registration (some graphs);"},{"url":"https://fidoalliance.org/specs/fido-u2f-v1.2-ps-20170411/fido-u2f-overview-v1.2-ps-20170411.html#registration-creating-a-key-pair", "desc":"Universal 2nd Factor (U2F) Overview @ fidoalliance;"},
{"url":"https://github.com/Yubico/php-u2flib-server", "desc":"Yubico php @ github;"},{"url":"https://github.com/github/u2f-api/blob/master/u2f-api-polyfill.js", "desc":"U2F api polyfill @ github;"},{"url":"https://www.thepolyglotdeveloper.com/2018/11/u2f-authentication-yubikey-nodejs-jquery/", "desc":"U2F Authentication With A YubiKey Using Node.js And jQuery"}]</script>
</body></html>
