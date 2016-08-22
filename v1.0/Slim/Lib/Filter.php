<?
/**
 * Created by y.alekseev
 */

namespace Slim\Lib;

class Filter extends \Slim\Helper\Lib
{
	/**
	 * Возвращает фильтры вместе с разделами в виде дерева
     * у разделов должно быть свойство UF_TYPE
	 * @return array
	 */
	public static function getList()
	{
		global $DB;
		
		\CModule::IncludeModule('iblock');
		
		//$timestamp = self::getTimestamp();


        #  Получим свойства UF_
        $section_props = \CIBlockSection::GetList( array(),array('IBLOCK_ID'=>self::IBLOCK_FILTERS_ID ),true, array("UF_TYPE") );
        $uf_ids = array();
        while($props_array = $section_props->GetNext())
            $uf_ids[$props_array['ID']] = $props_array['UF_TYPE'];

        #  Получим свойства PROPERTY у элементов
        $elements_props = \CIBlockElement::GetList( array(),array('IBLOCK_ID'=>self::IBLOCK_FILTERS_ID ),false, false, array('ID',"PROPERTY_RESET") );
        $element_ids = array();
        while($props_element_array = $elements_props->GetNext())
            $element_ids[$props_element_array['ID']] = $props_element_array['PROPERTY_RESET_VALUE'];

        # Получим разделы с элементами
		$arFilter = array('IBLOCK_ID'=>self::IBLOCK_FILTERS_ID);
		$arFilter = ( $timestamp )
			? array_merge($arFilter, array('TIMESTAMP_X_1'=>date($DB->DateFormatToPHP(\CLang::GetDateFormat()), $timestamp)))
			: array_merge($arFilter, array('ACTIVE' => 'Y', 'DATE_ACTIVE' => 'Y'));
		
		$filters = array();
		$dbFilters = \CIBlockSection::GetMixedList(
				array('LEFT_MARGIN' => 'ASC'),
				$arFilter,
				false,
                false,
                array('ID', 'NAME', 'ACTIVE', 'CODE', 'DEPTH_LEVEL', 'SORT', 'IBLOCK_SECTION_ID')
		);

        // Пройдёмся по всем фильтрам и выберем необходимые поля
        while ($arFilter = $dbFilters->Fetch())
        {
            $essence = 'CATEGORIES';

            $filter = array();
			
			$filter['ID']       = (int)$arFilter['ID'];
			$filter['NAME']     = $arFilter['NAME'];
            $filter['SORT']     = (int)$arFilter['SORT'];
			$filter['ACTIVE']   = ($arFilter['ACTIVE'] == 'Y') ? true : false;


			if ($arFilter['CODE']) {
                $essence = 'ELEMENTS';

                $filter['CODE'] = $arFilter['CODE'];
                $filter['PARENT_IDS'][] = (int)$arFilter['IBLOCK_SECTION_ID'];

                # если у элемента установлено свойтсво RESET, то добавим его в общий массив
                if ($element_ids[$arFilter['ID']])
                    $filter['RESET'] = true;
            }
            else {
				$filter['DEPTH_LEVEL'] = (int)$arFilter['DEPTH_LEVEL'];

                # Получим значение типа для фильтра
                if ($uf_ids[$arFilter['ID']]) {
                    $rsGender = \CUserFieldEnum::GetList(array(), array('ID'=>$uf_ids[$arFilter['ID']]))->GetNext();
                    $filter['TYPE'] = $rsGender['VALUE'];
                }

				if (!$arFilter['IBLOCK_SECTION_ID'])
					$filter['PARENT_ID'] = (int)$arFilter['ID'];
				else
					$filter['PARENT_ID'] = (int)$arFilter['IBLOCK_SECTION_ID'];
			}


			$filters[$essence][] = $filter;
		}

		
		if ($timestamp && count($filters) == 0)
			\Slim\Helper\ErrorHandler::put('FILTERS_NOT_FOUND', 304);
		
		return $filters;
	}
}