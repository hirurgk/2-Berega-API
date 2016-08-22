<?
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 16.03.2015
 * Time: 14:58
 */

namespace Slim\Lib;

/**
 * Class Catalog
 * @package Slim\Lib
 */
class Catalog extends \Slim\Helper\Lib
{
    /**
     * Получение списка товаров по ID категории или по ID товаров
     *
     * @param bool $catId
     * @param bool $goodsId
     * @param bool $noError
     *
     * @return array
     *
     */
    public static function getGoodsList( $catId = false, $goodsId = false, $noError = false )
    {
        global $DB;

        \CModule::IncludeModule('iblock');
        \CModule::IncludeModule('catalog');

        $catalogGroup   = self::getCatalogGroupId();
		//$timestamp      = self::getTimestamp();


        # TODO: замена старого ID на новый
		//if( $catId )
		//    $catId= \Slim\Helper\OldSectionsId::getNew( $catId );

        
        

        # получим данные по товарам раздеала
        # при первой загруке выбираются все активные товары ( определяется отсутствием параметра TIMESTAMP )
        $arFilter = array('IBLOCK_ID' => self::PRODUCTS);

        # Если указан ID категории, выбираются так же подкатегории
        if ($catId > 0) {
            $arFilter = array_merge($arFilter, array('SECTION_ID' => $catId, "INCLUDE_SUBSECTIONS" => "Y",));
        } else {	//...иначе ищем по ID

            # id рекомендованых товаров из метода Retailrocket->getArrayIdRetailrocket()
            # передаются в переменной $goodsId
            # в остальных случаях берутся из GET параметров
            $ids = ( $goodsId ) ? $goodsId : self::getIds();

            if (sizeof($ids))
                $arFilter = array_merge($arFilter, array('ID' => $ids));
            else
                $arFilter = array_merge($arFilter, array('ID' => -1));
        }

        $arFilter = ( $timestamp )
            ? array_merge($arFilter, array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y', '>DATE_MODIFY_FROM' => date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
            : array_merge($arFilter, array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y'));

        //Добавим к выборке с timestamp товары, у которых есть период по времени для продажи и акционной скидки.
        //Для того, чтобы проверить, менялись ли они с прошлого запроса.
        if ($timestamp) {
            $arFilter = array_merge($arFilter, array('!PROPERTY_LANCH_START' => ''));
            $arFilter = array_merge($arFilter, array('!PROPERTY_ACTION_DSC_SHED' => ''));
        }

        //Запрашиваемые поля
        $arSelect = array(
            'ID','NAME', 'SORT', 'TIMESTAMP_X', 'ACTIVE', 'PROPERTY_NAME_MOBILE', 'PROPERTY_MOBILE_PREVIEW', 'PROPERTY_MOBILE_DETAIL', 'PROPERTY_SOUS_LIST', 'PROPERTY_SOUS_DEF',
            'PROPERTY_Vid', 'PROPERTY_SALELEADER', 'PROPERTY_NEWPRODUCT', 'PROPERTY_ACTION_PERCENT', 'DETAIL_TEXT', 'PROPERTY_ACTION_PRICE', 'PROPERTY_BUY_DISABLED',
            'PROPERTY_FILTERS', 'PROPERTY_DOP', 'PROPERTY_ACTION_DSC_SHED', 'PROPERTY_LANCH_START', 'PROPERTY_LANCH_END', 'PROPERTY_ACTIVITY_CALENDAR', 'PROPERTY_FREE_CHOPSTICKS',
            'CATALOG_GROUP_'.$catalogGroup
        );
        $arRes = \CIBlockElement::GetList(array('SORT'=>'ASC'), $arFilter, false, false, $arSelect);


        $items          = array();  # Соберем массив продуктов с необходимыми данными
        $section_ids	= array();	# Соберём сюда ID'шники всех разделов товаров
        $sections		= array();	# Соберём сами разделы всех товаров
        $sauces         = array();	# Соберём все возможные соусы


        while( $res = $arRes->Fetch() )
        {
            $product_ids[] = $res['ID'];
            $product_info[] = $res;
        }


        # получим id разделов к которым привязан элемент
        $groups = \CIBlockElement::GetElementGroups( $product_ids, true );
        while($ar_group = $groups->Fetch()) {
            # TODO замена нового ID на старый
			//$res_groups[ $ar_group['IBLOCK_ELEMENT_ID'] ][] = (int)\Slim\Helper\OldSectionsId::getOld($ar_group["ID"]);
			$res_groups[ $ar_group['IBLOCK_ELEMENT_ID'] ][] = (int)$ar_group["ID"];
            $section_ids[] = (int)$ar_group["ID"];
        }
        
        
        //---Получим все разделы
        $dbSections = \CIBlockSection::GetList(array(), array('ID' => $section_ids));
        while ($arSection = $dbSections->Fetch()) {
        	$sections[$arSection['ID']] = $arSection;
        }
        //---
        
        
        //---Получим все соусы
        $dbSauces = \CIBlockPropertyEnum::GetList(Array(), Array('IBLOCK_ID' => self::PRODUCTS, 'CODE' => array('SOUS_LIST', 'SOUS_DEF')));
        while($arSauces = $dbSauces->Fetch()) {
            $sauces[$arSauces['ID']] = $arSauces;
        }
        //---


        foreach( $product_info as $res )
        {
            //Если нет основной цены у товара, значит он не для запрошенного региона
            if (!$res['CATALOG_PRICE_'.$catalogGroup])
                continue;

            # Проверим, менялись ли товары по периодам продаж или акционной цены с предыдущего запроса
            if ($timestamp && $res['PROPERTY_LANCH_START_VALUE'] && $res['PROPERTY_ACTION_DSC_SHED_VALUE'])
            {
                $isChange = false;

                $prev_can_buy   = self::isCanBuy($res, $timestamp);
                $now_can_buy    = self::isCanBuy($res, time());

                if ($prev_can_buy !== $now_can_buy)
                    $isChange = true;

                $prev_active_action_price = self::isActiveActionPrice($res, $timestamp);
                $now_active_action_price = self::isActiveActionPrice($res, time());

                if ($prev_active_action_price !== $now_active_action_price)
                    $isChange = true;

                if (!$isChange)
                    continue;
            }

            $res['GROUP_ID'] = $res_groups[ $res['ID'] ];
            $res['SECTION_NAME'] = $sections[$res_groups[ $res['ID'] ][0]]['NAME'];	//Имя основного раздела товара

            $item = self::setItem( $res, $catalogGroup, $sauces );

            # фильтры
            if( sizeof($res['PROPERTY_FILTERS_VALUE']) ){
                foreach($res['PROPERTY_FILTERS_VALUE'] as $valueFilter)
                    $item['FILTERS'][] = (int)$valueFilter;
            }
            else
                $item['FILTERS'] = array();


            # топпинги
            if( sizeof( $res['PROPERTY_DOP_VALUE']) ){
                foreach( $res['PROPERTY_DOP_VALUE'] as $valueTopping )
                    $item['TOPPINGS'][] = (int)$valueTopping;
            }
            else
                $item['TOPPINGS'] = array();

            $items[] = $item;
        }


        if (!$noError) {
            if( count($items) == 0 && $timestamp )
                \Slim\Helper\ErrorHandler::put('PRODUCTS_UPDATES_NOT_FOUND', 304);
            elseif( count($items) == 0  )
                \Slim\Helper\ErrorHandler::put('PRODUCTS_IN_CATEGORY_NOT_FOUND');
        }


        return $items;
    }


    /**
     * Получение ID рекомендованных товаров
     * Site-id получается из Headers
     * В переменной ids через запятую (,) указаны id, передается в GET
     *
     * @param int $count
     *
     * @return array
     */
    public static function getRecommendationIds( $count = 20)
    {
        $siteId     = self::getSiteId();
        $productIds = self::getIds();

        if( count($productIds) > 100 ) {
            \Slim\Helper\ErrorHandler::put('INCORRECT_REQUEST');
            return;
        }

        # Получим ID рекомендаций из API
        $obj = new \Slim\Helper\Retailrocket( $siteId, $productIds );
        $arrId = $obj->getArrayIdRetailrocket();

        # Ограничим кол-во рекомендаций, по умолчанию 20 штук
        $result = array();
        
        if( count($arrId) > $count) {
            $arrIdRand = array_rand($arrId, $count);
            foreach ($arrIdRand as $rand)
                $result[] = $arrId[$rand];
        }
        else
            $result = $arrId;

        return $result;
    }


    /**
     * Наполнение товара необходимыми свойствами
     * @param $res
     * @param $catalogGroup
     *
     * @return array
     */
    private static function setItem( $res,  $catalogGroup , $sauces = array())
    {
        $item = array();
        
        $pictureSmall = \CFile::ResizeImageGet( $res['PROPERTY_MOBILE_PREVIEW_VALUE'], array('width' => 150, 'height' => 150), BX_RESIZE_IMAGE_EXACT, false);
        $pictureBig = \CFile::ResizeImageGet( $res['PROPERTY_MOBILE_DETAIL_VALUE'], array('width' => 750, 'height' => 469), BX_RESIZE_IMAGE_EXACT, false);

        $item['ID']             = (int)$res['ID'];
        $item['GROUP_ID']       = $res['GROUP_ID'];
        $item['SECTION_NAME']   = $res['SECTION_NAME'];
        $item['NAME']           = strlen($res['PROPERTY_NAME_MOBILE_VALUE']) > 0 ? $res['PROPERTY_NAME_MOBILE_VALUE'] : $res['NAME'];
        $item['SORT']           = (int)$res['SORT'];
        $item['TIMESTAMP_X']    = $res['TIMESTAMP_X'];
        $item['ACTIVE']         = ($res['ACTIVE'] == 'Y') ? true : false;

        $item['SMALL_PICTURE']  = ( strlen($pictureSmall['src'])>0 ) ? self::getApp()->request->getUrl() . $pictureSmall['src'] : '';
        $item['BIG_PICTURE']    = ( strlen($pictureBig['src'])>0 ) ? self::getApp()->request->getUrl() . $pictureBig['src'] : '';

        $item['STRUCTURE']      = $res['DETAIL_TEXT'];

        if($res['CATALOG_WEIGHT'] > 0)
            $item['WEIGHT']     = (int)$res['CATALOG_WEIGHT'];

        $item['PRICE']          = (int)$res['CATALOG_PRICE_'.$catalogGroup];
        $item['ACTION_PRICE']   = ($res['PROPERTY_ACTION_PRICE_VALUE'])?(int)$res['PROPERTY_ACTION_PRICE_VALUE']:null;

        //Соусы
        if (sizeof($res['PROPERTY_SOUS_LIST_VALUE'])) {
            foreach ($res['PROPERTY_SOUS_LIST_VALUE'] as $key=>$sauce) {
                $item['SAUCES']['LIST'][] = array(
                    'CODE' => $sauces[$key]['XML_ID'],
                    'NAME' => $sauces[$key]['VALUE']
                );
            }
            $item['SAUCES']['SAUCE_DEFAULT'] = $sauces[$res['PROPERTY_SOUS_DEF_ENUM_ID']]['XML_ID'];
        }
        

        //---Период действия скидки
        //Используем сначала проверку на сервере
        $item['ACTIVE_ACTION_PRICE'] = self::isActiveActionPrice($res, time());
        $item['CAN_BUY'] = self::isCanBuy($res, time());

        # Отображение кнопки для покупки товара
        $item['ADD_BASKET'] = ($res['PROPERTY_BUY_DISABLED_VALUE']) ? false : true;

        //Разобьём строку со временем и днями недели
        $intervals = self::explodeIntervalActionPrice($res);

        $item['PERIOD_ACTION_PRICE'] = array(
            'FROM' => $intervals['TIME_START'] ? $intervals['TIME_START'] : '',
            'TO' => $intervals['TIME_END'] ? $intervals['TIME_END'] : '',
            'DAYS' => $intervals['WEEK_DAYS'] ? $intervals['WEEK_DAYS'] : '',
        );

        //Период для покупки
        $item['PERIOD_TO_BUY'] = array(
            'FROM' => $res['PROPERTY_LANCH_START_VALUE'] ? $res['PROPERTY_LANCH_START_VALUE'] : '',
            'TO' => $res['PROPERTY_LANCH_END_VALUE'] ? $res['PROPERTY_LANCH_END_VALUE'] : '',
        );
        //---

        $vid = array();
        foreach($res['PROPERTY_VID_VALUE'] as $valueVid){
            switch($valueVid){
                case 'Острое':
                    $vid['PEPPER'] = true;
                    break;
                case 'Средне-острое':
                    $vid['DOUBLE_PEPPER'] = true;
                    break;
            }
        };

        $item['ATTR'] = array(
            'HIT'           => $res['PROPERTY_SALELEADER_VALUE'] ?  true : false,
            'NEW'           => $res['PROPERTY_NEWPRODUCT_VALUE'] ?  true : false,
            'DISCOUNT_PERCENT' => $res['PROPERTY_ACTION_PERCENT_VALUE'] > 0 ? (int)$res['PROPERTY_ACTION_PERCENT_VALUE'] : 0,
            'FREE_CHOPSTICKS' => ($res['PROPERTY_FREE_CHOPSTICKS_VALUE']) ? true : false,
        );
        $item['ATTR'] = array_merge( $item['ATTR'], $vid);

        //Доработка скидки
        $item['ACTION_PRICE'] = $item['ACTIVE_ACTION_PRICE'] ? $item['ACTION_PRICE'] : null;
        $item['ATTR']['DISCOUNT_PERCENT'] = $item['ACTIVE_ACTION_PRICE'] ? $item['ATTR']['DISCOUNT_PERCENT'] : null;

        return $item;
    }


    /**
     * Проверяет возможность покупки товара, исходя от времени начала и окончания ланча и текущего времени в unixtime
     *
     * @param $res
     * @param unixtime $time_now
     *
     * @return boolean
     */
    private static function isCanBuy($res, $time_now)
    {
        $time_start = strtotime($res['PROPERTY_LANCH_START_VALUE']);
        $time_end = strtotime($res['PROPERTY_LANCH_END_VALUE']);

        $siteId = self::getSiteId();
        
		$AC = \ActivityCalendar::isActive($res['PROPERTY_ACTIVITY_CALENDAR_VALUE'], $siteId);
		if (!$AC['ACTIVE'])
		    return false;

        return true;

        //Если что-то не заполнено, вернём истину
		if (!$time_start || !$time_end)
		    return true;

		return self::checkTimeInterval($time_start, $time_end, $time_now);
    }


    /**
     * Проверяет активность акционной цены, исходя от периода её действия
     *
     * @param $res
     * @param unixtime $time_now
     *
     * @return boolean
     */
    private static function isActiveActionPrice($res, $time_now)
    {
        //Если нет акционной цены, то и нет смысла проверять
        if (!$res['PROPERTY_ACTION_PRICE_VALUE'])
            return false;

        //Разобьём строку со временем и днями недели
        $intervals = self::explodeIntervalActionPrice($res);

        $time_start = strtotime($intervals['TIME_START']);
        $time_end = strtotime($intervals['TIME_END']);
        $week_days = $intervals['WEEK_DAYS'] ? $intervals['WEEK_DAYS'] : false;

        //Если что-то не заполнено...
        if (!$time_start || !$time_end || !$week_days)
            return true;

        $week_day_now = date('w', $time_now);
        $week_days = explode(',', $week_days);

        //Если не совпадает день недели, значит не истина
        if (!in_array($week_day_now, $week_days))
            return false;

        return self::checkTimeInterval($time_start, $time_end, $time_now);
    }


    /**
     * Проверяет входит ли $time_now в интервал от $time_start до $time_end
     *
     * @param unixtime $time_start
     * @param unixtime $time_end
     * @param unixtime $time_now
     *
     * @return boolean
     */
    private static function checkTimeInterval($time_start, $time_end, $time_now)
    {
        if ($time_start > $time_end) {
            if ($time_now >= $time_start || $time_now < $time_end) return true;
        } else {
            if ($time_now >= $time_start && $time_now < $time_end) return true;
        }

        return false;
    }

    /**
     * Разбивает интервал периода действия акционной цены
     * @param $res
     * @return Array
     */
    private static function explodeIntervalActionPrice($res)
    {
        $action_price_period = explode('|', $res['PROPERTY_ACTION_DSC_SHED_VALUE']);
        $action_price_time = explode('-', $action_price_period[0]);

        $intervals = array();
        $intervals['TIME_START'] = $action_price_time[0];
        $intervals['TIME_END']   = $action_price_time[1];
        $intervals['WEEK_DAYS']  = $action_price_period[1];

        return $intervals;
    }
}