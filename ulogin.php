<?php

/** 
 * Auth via uLogin.ru
 * @package SMF
 * @subpackage uLogin Package
 * @author uLogin team@ulogin.ru http://ulogin.ru/ 
 * @license GPL3 
 */

if (!defined('SMF'))
{
	die('Hacking attempt...');
}

define('ULOGIN_URL', urlencode(__redirect_url()));
define('ULOGIN_SHOWN', 'vkontakte,odnoklassniki,mailru,facebook'); /* Сервисы, выводимые сразу */
define('ULOGIN_HIDDEN', 'twitter,google,yandex,livejournal,openid'); /* Сервисы, выводимые при наведении */
/* полный список сервисов по адрес: http://ulogin.ru/ */
/**
 * Generate redirect url
 * 
 * @return 	string				return generated url
 */
function __redirect_url()
{
	global $boardurl;
	return $boardurl."/index.php?action=ulogin&=";
}

function ulogin()
{
	global $modSettings, $smcFunc, $sourcedir;
	
	require_once($sourcedir . '/class_ulogin.php');
	require_once($sourcedir . '/Subs-Auth.php');
	
	$uLogin = new uLogin($smcFunc);
	
	if (!$user_settings = $uLogin->auth())
	{
		if ($uLogin->register())
		{
			$user_settings = $uLogin->auth();
		}
	}
	
	if (!$user_settings)
	{
		redirectexit('?');
	}
	
	setLoginCookie(60 * $modSettings['cookieTime'], $user_settings['id_member'], sha1($user_settings['passwd'] . $user_settings['password_salt']));
	redirectexit('?');
}

?>