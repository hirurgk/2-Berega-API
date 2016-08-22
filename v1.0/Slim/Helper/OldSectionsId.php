<?php
/**
 * Created by PhpStorm.
 * User: v.kravtsov
 * Date: 24.08.2015
 * Time: 18:33
 */

namespace Slim\Helper;


class OldSectionsId {

    /**
     * Замена нового Id на старый
     * @param $newId
     *
     * @return mixed
     */
    public static function getOld( $newId )
    {
        $arOldIds = self::matchIds();

        if( $arOldIds[$newId] )
            return $arOldIds[$newId];

        return $newId;
    }


    /**
     * Замена старого Id на новый
     * @param $oldId
     *
     * @return mixed
     */
    public static function  getNew( $oldId )
    {
        $arOldIds = self::matchIds();
        if( $key = array_search($oldId, $arOldIds) )
            return $key;

        return $oldId;
    }

    /**
     * Метод соответсвия старых котов с новыми
     * @return mixed
     */
    private static function matchIds()
    {
        $arSections = array(
        # 1
        1 => 53, # Пицца
            3 => 68, # Традиционная
            4 => 69, # На тонком тесте
            5 => 72, # С двойной начинкой

        #2
        2 => 67 , # Роллы
            17 => 62, # Классические
            18 => 64, # Запечённые

        #3
        53 => 61, # Сеты

        #4
        43 => 92, # WOK

        #5
        55 => 56 , # Салаты и закуски
            56 => 117, # Закуски
            58 => 229, # Салаты

        #6
        39 => 66, # Супы

        #7
        38 => 57, # Десерты

        #8
        42 => 71, # Детские блюда

        #9
        52 => 115, # Русские пироги

        #10
        31 => 93, # Напитки

        #11
        60 => 101, # Промо наборы
            61 => 106, # Вок-Тайм
            62 => 104, # Кулинарные блокбастеры
            63 => 183 # Ночные предложения
        );

        return $arSections;
    }
} 