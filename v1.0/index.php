<?
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 13.03.2015
 */

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
require 'Slim/Slim.php';

# функция аутентификации в роутах
function auth() { \Slim\Helper\Authentication::isAuth(); }

\Slim\Slim::registerAutoloader();
\Slim\Route::setDefaultConditions(array(
    'id'=>'\d+',
    'catId'=>'\d+',
));

$app = new \Slim\Slim();
$app->contentType('application/json');


/* группа для работы с сайтами(регионами) */
$app->group('/site', function() use ($app) {
    /* инициализация. получение данных при старте приложения */
    $app->get('/init', function() {
        $init = \Slim\Lib\Init::get();
        \Slim\Helper\Answer::json( $init, false, 'site' );
    });

    /* получение ID сайта(региона) по IP */
    $app->get('/id', function() {
        $params = \Slim\Lib\Site::getSiteIdFromIP();
        \Slim\Helper\Answer::json( $params, false, 'site' );
    });

    /* получение настроек сайта */
    $app->get('/params', function() {
        $params = \Slim\Lib\Params::getList();
        \Slim\Helper\Answer::json( $params, false, 'site' );
    });

    /* получение списка сайтов */
    $app->get('/list', function() {
        $sites = \Slim\Lib\Site::getList();
        \Slim\Helper\Answer::json( $sites, false, 'site' );
    });

    /* получение списка телефонов */
    $app->get('/phones', function() {
        $phones = \Slim\Lib\Site::getPhoneList();
        \Slim\Helper\Answer::json( $phones, false, 'site' );
    });

    /* получение алертов */
    $app->get('/alerts', function() {
        $alerts = \Slim\Lib\Site::getAlerts();
        \Slim\Helper\Answer::json( $alerts );
    });
});


/* группа для работы с каталогом */
$app->group('/catalog', function() use ($app) {
    /* получение списка меню */
    $app->get('/menu', function() {
        $menu = \Slim\Lib\Menu::getList();
        \Slim\Helper\Answer::json( $menu, false, 'catalog' );
    });

    /* получение фильтров */
    $app->get('/filters', function() {
        $filters = \Slim\Lib\Filter::getList();
        \Slim\Helper\Answer::json( $filters, false, 'catalog' );
    });

    /* получение топпингов */
    $app->get('/toppings', function() {
        $toppings = \Slim\Lib\Topping::getList();
        \Slim\Helper\Answer::json( $toppings, false, 'catalog' );
    });

    /* получение списка товаров для категории */
    $app->get('/list/:catId', function( $catId ) {
        $arGoods = \Slim\Lib\Catalog::getGoodsList( $catId );
        \Slim\Helper\Answer::json( $arGoods, false, 'catalog' );
    });

    /* получение товара(ов) */
    $app->get('/list', function() {
        $arProducts = \Slim\Lib\Catalog::getGoodsList();
        \Slim\Helper\Answer::json( $arProducts, false, 'catalog' );
    });

    /* получение рекомендованных товаров */
    $app->get('/recommendation', function() {
        $recommendations = \Slim\Lib\Catalog::getRecommendationIds(6);
        \Slim\Helper\Answer::json( $recommendations, false, 'catalog' );
    });

});


/* группа для работы с заказми */
$app->group('/order', function() use ($app) {
    /* Создание нового заказа */
    $app->post('', 'auth',  function() {
        $arOrder = \Slim\Lib\Order::create();
        \Slim\Helper\Answer::json( $arOrder, false, 'order' );
    });
    
    //Создание заказа для неавторизованного пользователя
    $app->post('/guest', function() {
        $arOrder = \Slim\Lib\Order::createForGuest();
        \Slim\Helper\Answer::json( $arOrder, false, 'order' );
    });

    /* Получение одного заказа по ID */
    $app->get('/:id', 'auth', function( $id ) {
        $arOrder = \Slim\Lib\Order::getOrderList( $id );
        \Slim\Helper\Answer::json( $arOrder, false, 'order' );
    });

    /* Получение списка заказов по ID пользователя */
    $app->get('/list', 'auth', function() {
        $arOrders = \Slim\Lib\Order::getOrderList();
        \Slim\Helper\Answer::json( $arOrders, false, 'order' );
    });
});


/* группа для работы с корзиной */
$app->group('/basket', function() use ($app) {
    /* Актуализация цен в корзине */
    $app->post('/actualize', function() {
        $basket = \Slim\Lib\Basket::check();
        \Slim\Helper\Answer::json( $basket, false, 'basket' );
    });
});


/* группа работы с пользователем, необходима аутентификация */
$app->group('/user', function() use ($app) {
    /* Получение данных пользователя */
    $app->get('', 'auth', function() {
        $arUser = \Slim\Lib\User::get();
        \Slim\Helper\Answer::json( $arUser, true, 'user' );
    });

    /* Отправка контрольной строки для восстановления пароля  */
    $app->get('/checkword', function() {
        $checkword = \Slim\Lib\User::checkword();
        \Slim\Helper\Answer::json( $checkword, false, 'user' );
    });

    /* Авторизация */
    $app->post('/login', function() {
        $arLoginInfo = \Slim\Lib\User::login();
        \Slim\Helper\Answer::json( $arLoginInfo, true, 'user' );
    });

    /* Авторизация через соц. сети */
    $app->post('/socserviceauth', function() {
        $arLoginSocServiceAuth = \Slim\Lib\User::loginSocServiceAuth();
        \Slim\Helper\Answer::json( $arLoginSocServiceAuth, true, 'user' );
    });

    /* Изменение данных пользователя */
    $app->put('', 'auth', function() {
        $arUser = \Slim\Lib\User::update();
        \Slim\Helper\Answer::json( $arUser, true, 'user'  );
    });

    /* Регистрация */
    $app->put('/register', function() {
        $register = \Slim\Lib\User::register();
        \Slim\Helper\Answer::json( $register, true, 'user' );
    });
});


/* группа для работы с акциями */
$app->group('/sale', function() use ($app) {
    /* Получение списка акций */
    $app->get('/list', function() {
        $sale = \Slim\Lib\Sale::getList();
        \Slim\Helper\Answer::json( $sale, false, 'sale' );
    });
});


/* группа для работы с баннерами */
$app->group('/banners', function() use ($app) {
    /* Получение списка баннеров */
    $app->get('', function() {
        $banners = \Slim\Lib\Banners::getList();
        \Slim\Helper\Answer::json( $banners, false, 'banners' );
    });
    
    /* Получение списка баннеров по ID региона/сайта */
    $app->get('/site', function() {
        $banners = \Slim\Lib\Banners::getListFromSiteId();
        \Slim\Helper\Answer::json( $banners, false, 'banners' );
    });
});


/* группа для работы с доставкой */
$app->group('/delivery', function() use ($app) {
    /* получение списка улиц и их кодов */
    $app->get('/street/:name', function( $name ) {
        $arStreet = \Slim\Lib\Cladr::getStreet( $name );
        \Slim\Helper\Answer::json( $arStreet, true, 'banners' );
    });

    /* получение номеров домов для улицы */
    $app->get('/house/:codeStreet', function( $codeStreet ) {

        $arHouse = \Slim\Lib\Cladr::getNumbersHouseForStreet( $codeStreet );
        \Slim\Helper\Answer::json( $arHouse, true, 'banners' );
    });

    /* получение времени доставки заказа */
    $app->get('/time', function() use ($app) {
        # Код улицы из кладр
        $street = $app->request()->params('street');
        # Номер дома
        $house = $app->request()->params('house');
        # Сумма заказа
        $order_sum = $app->request()->params('order_sum');
        # Флаг нового адреса
        $new_address = ($app->request()->params('new_address') == 1 || $app->request()->params('new_address') == 'true') ? true : false ;
        # Отложенная доставка
        $delayed_delivery = ($app->request()->params('delayed_delivery')) ? $app->request()->params('delayed_delivery') : '';


        $arParams = \Slim\Lib\Delivery::GetTime( $street, $house, $order_sum, $delayed_delivery, $new_address );
        \Slim\Helper\Answer::json( $arParams, false, 'delivery' );
    });
});


/* группа для работы с промокодами */
$app->group('/promo', function() use ($app) {
    /* Возвращает промокод юзера или генерирует новый */
    $app->get('', 'auth', function() {
        $promo = \Slim\Lib\Promo::get();
        \Slim\Helper\Answer::json( $promo, false, 'promo' );
    });
    
    /* Подтверждение промокода юзера */
    $app->get('/confirm', function() {
        $promo = \Slim\Lib\Promo::confirmCodeFrom1C();
    });
    
    /* Очистка текущего подарка юзера */
    $app->get('/clearpresent', function() {
        $promo = \Slim\Lib\Promo::clearPromoPresentFrom1C();
    });
    
    /* Принимает купоны от 1С (Не используется) */
    /*$app->post('/setcoupons', function() {
        $promo = \Slim\Lib\Promo::setCouponsFrom1C();
    });*/
    
    /* Устанавливает пользовательский промокод */
    $app->put('', 'auth', function() {
        $promo = \Slim\Lib\Promo::set();
        \Slim\Helper\Answer::json( $promo, false, 'promo' );
    });
    
    /* Возвращает список купонов пользователя */
    $app->get('/coupons', 'auth', function() {
        $coupons = \Slim\Lib\Promo::getCoupons();
        \Slim\Helper\Answer::json( $coupons, false, 'promo' );
    });
    
    /* Активация разового промокода */
    $app->get('/activate', 'auth', function() {
        $promo = \Slim\Lib\Promo::activateSingleCode();
        \Slim\Helper\Answer::json( $promo, false, 'promo' );
    });
});


/* группа для работы с SMS-замком */
$app->group('/sms', function() use ($app) {
    /* Отправляет клиенту сгенерированный код по SMS */
    $app->get('/sendsmslock', function() {
        $sms = \Slim\Lib\SMS::sendSMSLock();
        \Slim\Helper\Answer::json( $sms, false, 'sms' );
    });
});


GLOBAL $DB;
$DB -> StartUsingMasterOnly();

# запускаем приложение
$app->run();

$DB -> StopUsingMasterOnly();