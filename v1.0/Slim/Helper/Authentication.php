<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 19.03.2015
 * Time: 17:03
 */
namespace Slim\Helper;

class Authentication
{
    /**
     * Login - передается в base64
     * @throws \Slim\Exception\Stop
     */
    public static function isAuth()
	{
        $app = \Slim\Slim::getInstance();

		$login = base64_decode($app->request->headers('Login'));
        $token = $app->request->headers('Token');

        /* если есть Token и Login пытаемся авторизовать */
        if( $login != null && $token != null )
        {
            global $USER;
            $arUser = \CUser::GetByLogin($login)->Fetch();

            if (!$arUser)
            {
            	\Slim\Helper\ErrorHandler::put('AUTHORIZATION_LOGIN_IS_NOT_VALID', 401);
            	\Slim\Helper\Answer::json();
            	$app->stop();
            }
            
            if (!\CUser::CheckStoredHash($arUser['ID'], $token))
            {
            	\Slim\Helper\ErrorHandler::put('AUTHORIZATION_STORE_HASH_IS_NOT_VALID', 401);
            	\Slim\Helper\Answer::json();
            	$app->stop();
            }
            
            $USER->Authorize($arUser['ID'], false, false);
        }
        else
        {
            \Slim\Helper\ErrorHandler::put('NOT_DATA_FOR_AUTHORIZATION', 403);
            \Slim\Helper\Answer::json();
            $app->stop();
        }
    }


    /**
     * Авторизация пользователя
     * если происходят ошибки, работа приложения не останавливается
     */
    public static function auth()
    {
        $app = \Slim\Slim::getInstance();

        $login = base64_decode($app->request->headers('Login'));
        $token = $app->request->headers('Token');

        global $USER;

        /* если есть Token и Login пытаемся авторизовать */
        if( $login != null && $token != null )
        {

            $arUser = \CUser::GetByLogin($login)->Fetch();

            if ( $arUser && \CUser::CheckStoredHash($arUser['ID'], $token)) {
                $USER->Authorize($arUser['ID'], false, false);
            } else
                $USER->Logout();
        }
        else
            $USER->Logout();
    }

}