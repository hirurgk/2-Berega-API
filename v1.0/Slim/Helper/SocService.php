<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 26.06.2015
 * Time: 19:03
 */

namespace Slim\Helper;

class SocService
{
    /**
     * Авторизация по токену через Вконтакте
     * @param $socServiceName
     * @param $token
     *
     * @return array
     */
    public static function vkontakte($socServiceName, $token)
    {
        $params = array(
            'fields'       => 'uid,first_name,last_name,screen_name,sex,bdate,photo_big,domain',
            'access_token' => $token
        );

        $result = json_decode(file_get_contents('https://api.vk.com/method/users.get' . '?' . urldecode(http_build_query($params))), true);
        $userInfo = $result["response"][0];

        return $arFields= array(
            'EXTERNAL_AUTH_ID'  => $socServiceName,
            'LOGIN'             => 'VKuser'.$userInfo['uid'],
            'NAME'              => $userInfo['first_name'],
            'LAST_NAME'         => $userInfo['last_name'],
            'XML_ID'            => $userInfo['uid'],
            'OATOKEN'           => $token
        );
    }


    /**
     * Авторизация через Facebook
     *
     * @param $socServiceName
     * @param $token
     *
     * @return array
     */
    public static function facebook( $socServiceName, $token )
    {
        $params = array('access_token' => $token);
        $userInfo = json_decode(file_get_contents('https://graph.facebook.com/me' . '?' . urldecode(http_build_query($params))), true);

        # соберем массив для вставки в базу
        return $arFields = array(
            'EXTERNAL_AUTH_ID'  => $socServiceName,
            'XML_ID'            => $userInfo["id"],
            'LOGIN'             => "FB_".$userInfo["id"],
            'EMAIL'             => $userInfo['email'],
            'NAME'              => $userInfo["first_name"],
            'LAST_NAME'         => $userInfo["last_name"],
            'OATOKEN'           => $token,
        );
    }


    /**
     * Авторизация через Twitter
     * @param $socServiceName
     * @param $token
     * @param $token_secret
     *
     * @return array()
     */
    public static function twitter($socServiceName, $token, $token_secret)
    {
        $appKey    = trim(\CSocServAuth::GetOption('twitter_key'));
        $appSecret = trim(\CSocServAuth::GetOption('twitter_secret'));

        $connection = new \Slim\Helper\TwitterOAuth($appKey, $appSecret, $token, $token_secret);
        $content = (array)$connection->get('account/verify_credentials');

        $arName = explode(' ', $content['name']);

        # соберем массив для вставки в базу
        return $arFields= array(
            'EXTERNAL_AUTH_ID'  => $socServiceName,
            'LOGIN'             => 'TW_' . $content['screen_name'],
            'NAME'              => $arName[0],
            'LAST_NAME'         => $arName[1],
            'XML_ID'            => $content['id'],
            'OATOKEN'           => $token,
            'OASECRET'          => $token_secret
        );
    }


    /**
     * Авторизация через Одноклассники
     *
     * @param $socServiceName
     * @param $token
     * @param $refresh_token - необходим для обновления $token, т.к время его жизни 30 мин
     *
     * @return array()
     */
    public static function odnoklassniki( $socServiceName, $token, $refresh_token )
    {
        $params['method']		        = 'users.getCurrentUser';
        $params['application_key']		= trim(\CSocServAuth::GetOption('odnoklassniki_appkey'));
        $appSecret  	                = trim(\CSocServAuth::GetOption('odnoklassniki_appsecret'));

		ksort($params);

        # Получение подписи
        $sig = '';
		foreach($params as $key => $value)
			$sig .= $key . "=" . $value;

		$sig .= md5($token . $appSecret);
		$sig = md5($sig);

    	
    	$user_data = json_decode(file_get_contents("http://api.odnoklassniki.ru/fb.do?method={$params['method']}&application_key={$params['application_key']}&access_token={$token}&sig={$sig}"), true);
    	
    	return $arFields = array(
    			'EXTERNAL_AUTH_ID'	=> $socServiceName,
    			'XML_ID'			=> "OK".$user_data['uid'],
    			'LOGIN'				=> "OKuser".$user_data['uid'],
    			'NAME'				=> $user_data['first_name'],
    			'LAST_NAME'			=> $user_data['last_name'],
    			'OATOKEN'           => $token,
    	);
    }

}