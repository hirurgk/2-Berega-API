<?
/**
 * Created by y.alekseev
 */

namespace Slim\Helper;

class ErrorHandler
{
    private static $ERRORS = array();
    private static $STATUS = 200;

    /**
     * ключ => текст ошибоки
     * @var array
     */
	public static $ERROR_MESSAGES = array(
		"UNKNOWN_ERROR"						=> array('CODE' => 0, 'MESSAGE' => "Неизвестная ошибка"),
		
		"INCORRECT_REQUEST"						=> array('CODE' => 1000, 'MESSAGE' => "Некорректный запрос"),
		
		"MENU_ITEM_NOT_FOUND"				=> array('CODE' => 1100, 'MESSAGE' => "Меню не найдено, ошибочный запрос"),

        'ACCESS_ERROR'							=> array('CODE' => 1200, 'MESSAGE' => "Ошибка доступа"),
        'NOT_DATA_FOR_AUTHORIZATION'			=> array('CODE' => 1201, 'MESSAGE' => "Необходима авторизация"),
        'AUTHORIZATION_DATA_IS_NOT_VALID'		=> array('CODE' => 1202, 'MESSAGE' => "Данные для авторизации не действительны"),

        'AUTHORIZATION_LOGIN_IS_NOT_VALID'			=> array('CODE' => 1300, 'MESSAGE' => "Данные для авторизации не действительны (логин)"),
        'AUTHORIZATION_STORE_HASH_IS_NOT_VALID'		=> array('CODE' => 1301, 'MESSAGE' => "Данные для авторизации не действительны (хеш пароля)"),

        'UNKNOWN_USERNAME_OR_PASSWORD'			=> array('CODE' => 1400, 'MESSAGE' => "Не указан логин или пароль"),
        'NOT_VALID_USERNAME_OR_PASSWORD'		=> array('CODE' => 1401, 'MESSAGE' => "Неправильная пара Логин/Пароль"),
		
        "ORDER_ITEM_NOT_FOUND"				=> array('CODE' => 1500, 'MESSAGE' => "Заказ не найден"),
        "ORDER_LIST_NOT_FOUND"				=> array('CODE' => 1501, 'MESSAGE' => "Заказы не найдены"),
        "ORDER_CREATE_ERROR"				=> array('CODE' => 1502, 'MESSAGE' => "Заказ не был создан"),
        "ORDER_SAVE_ERROR"					=> array('CODE' => 1503, 'MESSAGE' => "Данные не были привязаны к заказу"),
		"ORDER_MIN_PRICE_FAIL"              => array('CODE' => 1504, 'MESSAGE' => "Стоимость закза не соответсвует минимальной стоимости"),
        "ORDER_REPEAT_ERROR"				=> array('CODE' => 1505, 'MESSAGE' => "Заказ для повтора не найден или отсутствуют товары"),
		
        "NOT_SPECIFIED_ID_GOODS"				=> array('CODE' => 1600, 'MESSAGE' => "Обязательный параметр ITEMS пустой"),
		
        "PRODUCTS_IN_CATEGORY_NOT_FOUND"    => array('CODE' => 1700, 'MESSAGE' => "Товары в категории не найдены"),
        "PRODUCTS_UPDATES_NOT_FOUND"        => array('CODE' => 1701, 'MESSAGE' => "Измененные товары не найдены"),
        "PRODUCTS_NOT_FOUND"                => array('CODE' => 1702, 'MESSAGE' => "Товары не найдены"),
        "RECOMMENDATION_PRODUCT_NOT_FOUND"  => array('CODE' => 1703, 'MESSAGE' => "Рекомендованные товары не найдены"),
		
        "USER_NOT_FOUND"						=> array('CODE' => 1800, 'MESSAGE' => "Пользователь не найден"),
        "USER_NOT_CREATE"						=> array('CODE' => 1801, 'MESSAGE' => "Пользователь не создан"),
		"USER_NOT_UPDATE"						=> array('CODE' => 1802, 'MESSAGE' => "Данные пользователя не были обновлены"),
		"USER_LOGIN_NOT_SET"					=> array('CODE' => 1803, 'MESSAGE' => "Не указан логин пользователя"),
		"USER_PASS_NOT_SET"						=> array('CODE' => 1804, 'MESSAGE' => "Не указан пароль пользователя"),
		"USER_EMAIL_NOT_SET"					=> array('CODE' => 1805, 'MESSAGE' => "Не указан E-Mail пользователя"),
		"USER_LOGIN_WHITESPACE"					=> array('CODE' => 1806, 'MESSAGE' => "Логин не может начинаться или заканчиваться пробелами"),
		"USER_MIN_LOGIN"						=> array('CODE' => 1807, 'MESSAGE' => "Логин должен быть не менее 3-х символов"),
		"USER_PASSWORD_LENGTH"					=> array('CODE' => 1808, 'MESSAGE' => "Пароль должен быть не менее 6-ти символов"),
		"USER_PASSWORD_UPPERCASE"				=> array('CODE' => 1809, 'MESSAGE' => "Пароль должен содержать латинские символы верхнего регистра (A-Z)"),
		"USER_PASSWORD_LOWERCASE"				=> array('CODE' => 1810, 'MESSAGE' => "Пароль должен содержать латинские символы нижнего регистра (a-z)"),
		"USER_PASSWORD_DIGITS"					=> array('CODE' => 1811, 'MESSAGE' => "Пароль должен содержать цифры (0-9)"),
		"USER_PASSWORD_PUNCTUATION"				=> array('CODE' => 1812, 'MESSAGE' => "Пароль должен содержать знаки пунктуации (,.<>/?;:'\"[]{}\|`~!@#$%^&*()-_+=)"),
		"USER_WRONG_EMAIL"						=> array('CODE' => 1813, 'MESSAGE' => "Неверный E-Mail"),
		"USER_WITH_EMAIL_EXIST"					=> array('CODE' => 1814, 'MESSAGE' => "Пользователь с таким E-Mail уже существует"),
		"USER_WRONG_CONFIRMATION"				=> array('CODE' => 1815, 'MESSAGE' => "Неверное подтверждение пароля"),
		"USER_WRONG_DATE_ACTIVE_FROM"			=> array('CODE' => 1816, 'MESSAGE' => "Неверная дата начала активности для группы"),
		"USER_WRONG_DATE_ACTIVE_TO"				=> array('CODE' => 1817, 'MESSAGE' => "Неверная дата окончания активности для группы"),
		"USER_PERSONAL_PHOTO"					=> array('CODE' => 1818, 'MESSAGE' => "Фотография некорректна"),
		"USER_WRONG_PERSONAL_BIRTHDAY"			=> array('CODE' => 1819, 'MESSAGE' => "Неверная дата рождения"),
		"USER_WORK_LOGO"						=> array('CODE' => 1820, 'MESSAGE' => "Рабочий логотип некорректный"),
		"USER_LOGIN_EXIST"						=> array('CODE' => 1821, 'MESSAGE' => "Пользователь с таким логином уже существует"),
		
		"RECOVERY_NOT_SENT"                 => array('CODE' => 1900, 'MESSAGE' => "Письмо с контрольной строкой не было отправлено"),
		"RECOVERY_NO_DATA"					=> array('CODE' => 1901, 'MESSAGE' => "Не указан Логин или E-Mail"),
		
		"FILTERS_NOT_FOUND"						=> array('CODE' => 2000, 'MESSAGE' => "Фильтры не найдены"),
		
		"TOPPINGS_NOT_FOUND"				=> array('CODE' => 2100, 'MESSAGE' => "Топпинги не найдены"),
		
		"SALES_NOT_FOUND"						=> array('CODE' => 2200, 'MESSAGE' => "Нет активных акций"),

        "PROMO_NOT_UNIQUE"				    => array('CODE' => 2300, 'MESSAGE' => "Не уникальный промокод"),
        "PROMO_ALREADY_ACTIVE"				=> array('CODE' => 2301, 'MESSAGE' => "Промокод уже был активирован"),
        "PROMO_ALREADY_EXIST"				=> array('CODE' => 2302, 'MESSAGE' => "Промокод уже был создан"),
        "PROMO_NOT_GEN"				        => array('CODE' => 2303, 'MESSAGE' => "Промокод не был сгенерирован"),
        "PROMO_NOT_VALID"				    => array('CODE' => 2304, 'MESSAGE' => "От 4 до 10 латинских символов и цифр"),
		"PROMO_ALREADY_USED"				=> array('CODE' => 2305, 'MESSAGE' => "Промокод уже был активирован"),
		"PROMO_NOT_FOUND"					=> array('CODE' => 2306, 'MESSAGE' => "Несуществующий промокод"),
        "PROMO_USER_REGISTER_EXPIRED"   	=> array('CODE' => 2307, 'MESSAGE' => "С момента регистрации прошло слишком много времени"),
        "PROMO_USER_ALREADY_REGISTER"   	=> array('CODE' => 2308, 'MESSAGE' => "Промокод уже был зарегистрирован"),
        "PROMO_USER_NOT_REGISTER"			=> array('CODE' => 2309, 'MESSAGE' => "Не удалось зарегистрироваться по промокоду"),
        
        "SMS_NOT_SEND"				            => array('CODE' => 2400, 'MESSAGE' => "Не удалось отправить SMS"),
        "SMS_ALREADY_SEND"				        => array('CODE' => 2401, 'MESSAGE' => "SMS уже отправлялось в разрешённый интервал"),
        "SMS_LOCK_DISABLED"				        => array('CODE' => 2402, 'MESSAGE' => "SMS-замок отключен"),
	);


    /**
     * Добавляет Описание и Код ошибки
     * @param $error_id
     * @param $status
     */
	public static function put( $error_id, $status=404)
	{
        self::$ERRORS[] = $error_id;
        self::$STATUS = $status;
	}
	
	
	/**
	 * Добавляет свой текст ошибки с кодом и добавляет в буфер вывода
	 * @param $error_id
	 * @param $text
	 * @param $status
	 */
	public static function addMessageAndPut($error_id, $text, $status=404)
	{
		self::$ERROR_MESSAGES[$error_id] = $text;
		self::put($error_id, $status);
	}


    /**
     * Проверяет на наличие ошибок
     * @return bool
     */
	public static function isError()
	{
		if ( sizeof(self::$ERRORS) )
            return true;
		else
            return false;
	}


    /**
     * Получает массив ошибок
     * @return array
     */
	public static function getList()
	{
		$errors = array();

        /* запишем ошибки */
		foreach (self::$ERRORS as $error)
        {
            $errors['err'][] = self::$ERROR_MESSAGES[$error];
        }

        /* запишем статус */
        $errors['status'] = self::$STATUS;
		
		return $errors;
	}


    /**
     * сброс ошибок и их кодов
     */
    public static function inZero($ar=array(), $st=200)
    {
        self::$ERRORS = $ar;
        self::$STATUS = $st;
    }
    
    
    /**
     * Формирование массива с ошибками от битрикса
     * @param array $err
     * @return array
     */
    public static function arBxError( $err=array() )
    {
    	return array_filter(explode("<br>", $err), function($element) {
    		return !empty($element);
    	});
    }

}
?>