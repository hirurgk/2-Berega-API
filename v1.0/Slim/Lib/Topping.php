<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Topping extends \Slim\Helper\Lib
{
	/**
	 * Возвращает массив с разделами топпингов и сами топпинги с привязкой к этим разделам
	 * @return array
	 */
	public static function getList()
	{
		global $DB;
		
		\CModule::IncludeModule('iblock');
		\CModule::IncludeModule('catalog');
		
		//$timestamp = self::getTimestamp();
		
		//---Сначала получим все разделы
		$sections = array();
		$section_ids = array();
		
		$arFilter = array('IBLOCK_ID'=>self::INGREDIENTS, '!ID' => self::SECTION_ALL_INGREDIENTS);	//Кроме раздела со всеми ингредиентами
		$arFilter = ( $timestamp )
			? array_merge($arFilter, array('>TIMESTAMP_X'=>date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
			: array_merge($arFilter, array('ACTIVE'=>'Y'));

		$dbSections = \CIBlockSection::GetList(array('LEFT_MARGIN' => 'ASC'), $arFilter, false, array('UF_LIMIT', 'UF_MOBILE_NAME') );
		while ($arSection = $dbSections->Fetch())
        {
			$section = array();
			
			$section['ID']          = (int)$arSection['ID'];
			$section['ACTIVE']      = ($arSection['ACTIVE']== 'Y')?true:false;
			$section['SORT']        = (int)$arSection['SORT'];
			$section['NAME']        = $arSection['UF_MOBILE_NAME'] ? $arSection['UF_MOBILE_NAME'] : $arSection['NAME'];
			$section['DEPTH_LEVEL'] = (int)$arSection['DEPTH_LEVEL'];
			$section['LIMIT']       = ($arSection['UF_LIMIT'])?(int)$arSection['UF_LIMIT']:null;

            $sections[] = $section;
			$section_ids[] = $arSection['ID'];
		}
		//---

        $catalogGroup = self::getCatalogGroupId();
		if (sizeof($section_ids))
        {
			//---Пройдёмся по всем элементам(ингредиентам)
			$toppings = array();
			$topping_ids = array();

            $arToppingSelect = array('ID', 'SORT', 'CODE', 'NAME', 'ACTIVE', 'CATALOG_WEIGHT', 'CATALOG_GROUP_'.$catalogGroup);
			$arToppingFilter = array('IBLOCK_ID' => self::INGREDIENTS, 'SECTION_ID' => $section_ids, 'INCLUDE_SUBSECTIONS' => 'Y');
			$arToppingFilter = ( $timestamp )
				? array_merge($arToppingFilter, array('DATE_MODIFY_FROM'=>date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
				: array_merge($arToppingFilter, array('ACTIVE'=>'Y', 'ACTIVE_DATE' => 'Y'));


			$dbToppings = \CIBlockElement::GetList( array(), $arToppingFilter, false, false, $arToppingSelect);
            while ($arTopping = $dbToppings->Fetch())
            {
				$topping = array();
					
				$topping['ID']      = (int)$arTopping['ID'];
                $topping['ACTIVE']  = ($arTopping['ACTIVE']== 'Y')?true:false;
                $topping['SORT']    = (int)$arTopping['SORT'];
                $topping['NAME']    = $arTopping['NAME'];
				$topping['CODE']    = $arTopping['CODE'];
				$topping['PRICE']   = (int)$arTopping['CATALOG_PRICE_'.$catalogGroup];
				$topping['WEIGHT']  = (int)$arTopping['CATALOG_WEIGHT'];
				
				$toppings[$arTopping['ID']] = $topping;		//Соберём нужные данные в один массив со всеми ингредиентами
				$topping_ids[] = (int)$arTopping['ID'];
			}
			//---
			
			
			//---Поскольку ингредиенты в разделах - это копии из раздела "ВСЕ ИНГРЕДИЕНТЫ", получим привязки ингредиентов к другим разделам
			$dbSections = \CIBlockElement::GetElementGroups($topping_ids);
			while ($arSection = $dbSections->Fetch())
            {
				if ($arSection['ID'] == self::SECTION_ALL_INGREDIENTS)
                    continue;

				# Допишем ингредиентам привязку к разделам
				$toppings[$arSection['IBLOCK_ELEMENT_ID']]['PARENT_IDS'][] = (int)$arSection['ID'];
			}
		}
        sort($toppings);
		
		if ($timestamp && count($sections) == 0 && count($toppings) == 0)
			\Slim\Helper\ErrorHandler::put('TOPPINGS_NOT_FOUND', 304);


        if( empty($sections) && empty($toppings) )
            return array();
        else
		    return array( 'CATEGORIES' => $sections, 'ELEMENTS' => $toppings );
	}
}