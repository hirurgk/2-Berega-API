<?
/**
 * created by y.alekseev
 */
namespace Slim\Lib;

class Feedback extends \Slim\Helper\Lib
{
    /**
     * Категории в соответствии с кодом
     */
    public static $CATEGORIES = array(
        'COMPLAINT' => 'Жалоба',
        'PROBLEM' => 'Проблема',
        'OFFER' => 'Предложение',
        'COMMENT' => 'Комментарий',
        'QUESTION' => 'Вопрос',
        'OTHER' => 'Другое'
    );
    
    /**
     * @method Получение сообщения от пользователя
     * 
     * @return array
     */
    public static function take()
    {
        global $USER;
        
        //Название текущего региона
        $siteId   = self::getSiteId();
        $siteName = \CSite::GetByID($siteId)->Fetch();
        $siteName = $siteName['SITE_NAME'];
        
        //Данные о пользователе
        \Slim\Helper\Authentication::auth();
        $userID    = $USER->GetID();
        $userLogin = $userID ? $USER->GetLogin() : '';
        $userData  = $userID ? "{$userLogin} (ID: {$userID})" : "Не авторизован";
        
        //Токен приложения
        $token = self::getAppID();
        
        //Данные с формы
        $name     = self::getApp()->request->params('NAME');
        $contact  = self::getApp()->request->params('CONTACT');
        $category = self::getApp()->request->params('CATEGORY');
        $message  = self::getApp()->request->params('MESSAGE');
        
        //Категория по коду
        if (self::$CATEGORIES[$category])
            $category = self::$CATEGORIES[$category];
        else
            $category = 'Другое';
        
        
        //Добавляем в инфоблок
        $element = new \CIBlockElement;

        $props = array();
        $props['CONTACT']  = $contact;
        $props['CATEGORY'] = $category;
        $props['MESSAGE']  = $message;
        $props['REGION']   = $siteName;
        $props['USER']     = $userData;
        $props['TOKEN']    = $token;

        $arData = Array(
            "IBLOCK_ID"       => self::IBLOCK_FEEDBACK_ID,
            "PROPERTY_VALUES" => $props,
            "NAME"            => $name,
            "ACTIVE"          => "Y"
        );

        $element->Add($arData);
        
        
        //Отправляем письмо
        \CEvent::SendImmediate('FEEDBACK_MOBILE_APP', $siteId, array(
            'NAME' => $name,
            'CATEGORY' => $category,
            'USER' => $userData,
            'CONTACT' => $contact,
            'REGION' => $siteName,
            'TOKEN' => $token,
            'MESSAGE' => $message
        ));
        
        
        return array();
    }

}