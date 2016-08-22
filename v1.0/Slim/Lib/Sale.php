<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Sale extends \Slim\Helper\Lib
{
	/**
	 * Возвращает акции по запрошенному сайту(региону)
     * Выборку можно огранисить свойством PROPERTY_IS_VIEW_MOBILE_APP
     * Данные из региона получаются их Headers
     * Данные для искомых ID передается через GET в переменной ids через запятую ../list?ids=12,105,78
     *
	 * @return array
	 */
	public static function getList()
	{
		global $DB;
		
		\CModule::IncludeModule('iblock');
		
		$siteId     = self::getSiteId();
		$timestamp  = self::getTimestamp();

		
		$arFilter = array('IBLOCK_ID'=>self::PROMOTIONS, 'ID' => self::getIds(), '!PROPERTY_IS_VIEW_MOBILE_APP' => false);
		$arFilter = ( $timestamp )
			? array_merge($arFilter, array('DATE_MODIFY_FROM'=>date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
			: array_merge($arFilter, array('ACTIVE'=>'Y', 'ACTIVE_DATE' => 'Y'));
		
		$dbSales = \CIBlockElement::GetList(array('ACTIVE_FROM'=>'DESC'), $arFilter, false, false, array());

        $sales = array();
		while ($arSale = $dbSales->GetNextElement())
        {
			$arFields   = $arSale->GetFields();
            $arProperty = $arSale->GetProperties();

			if ( !in_array( $siteId, $arProperty['TARGETING']['VALUE_XML_ID']) )
               continue;

            $sale = array();

			$sale['ID']                 = (int) $arFields['ID'];
			$sale['DATE_ACTIVE_FROM']   = $arFields['DATE_ACTIVE_FROM'];
			$sale['DATE_ACTIVE_TO']     = $arFields['DATE_ACTIVE_TO'];
			$sale['SORT']               = (int)$arFields['SORT'];
			$sale['NAME']               = $arFields['NAME'];
            $sale['TARGETING'] 		    = $arProperty['TARGETING']['VALUE_XML_ID'];

            # картинка для акции
			$preview_pic = \CFile::ResizeImageGet($arProperty['PICTURE_MOBILE_APP']['VALUE'], array('width' => 600, 'height' => 236), BX_RESIZE_IMAGE_EXACT, false);
			if ($preview_pic['src'])
                $sale['PREVIEW_PICTURE'] = self::getApp()->request->getUrl() . $preview_pic['src'];

            # список акционных товаров
            if ($arProperty['ACTION_GOODS']['VALUE'])
                foreach($arProperty['ACTION_GOODS']['VALUE'] as $val)
                    $sale['ACTION_GOODS'][] = (int) ($val+900000);

            # описание к акции
            if (strlen($arProperty['DESCRIPTION_MOBILE_APP']['VALUE']['TEXT'])>0)
                $sale['DETAIL_TEXT']     = $arProperty['DESCRIPTION_MOBILE_APP']['~VALUE']['TEXT'];

			$sales[] = $sale;
		}

		
		if ($timestamp && count($sales) == 0)
			\Slim\Helper\ErrorHandler::put('SALES_NOT_FOUND', 304);
		elseif (count($sales) == 0)
			\Slim\Helper\ErrorHandler::put('SALES_NOT_FOUND');


        return $sales;
	}
}