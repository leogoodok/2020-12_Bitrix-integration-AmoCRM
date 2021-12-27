<?
if (!class_exists('AmoCrmMain')) die();

/** @todo Перенести в init.php */
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'OnSaleOrderBeforeSaved',
    ['AmoOrderEvent', 'saleOrderBeforeSaved']
);


/**
 * Класс обработчика "Экспорт заказа в сделку amoCRM"
 * 
 * @warning Использовать при версии модуля "sale" - "15.5.0" и выше
 * 
 * Передача заказа в сделку amoCRM по условию (значению свойств заказа)
 * "Отправить заказ в amoCRM" == "Y" && "Заказ передан в amoCRM" != "Y"
 * 
 * @note Должны быть созданы свойства заказа:
 * ["NAME"]=> "Отправить заказ в amoCRM"
 * ["CODE"]=> "SEND_ORDER_TO_AMOCRM"
 * ["TYPE"]=> "CHECKBOX"
 * ["VALUE"]=> "Y" и "N"
 * 
 * ["NAME"]=> "Заказ передан в amoCRM"
 * ["CODE"]=> "ORDER_TRANSFERRED_AMOCRM"
 * ["TYPE"]=> "SELECT"
 * ["VALUE"]=> "Y" - "Да" и "N" - "Не задано"
 */

class AmoOrderEvent {
    public static function saleOrderBeforeSaved(\Bitrix\Main\Event $event) {
        /** 
         * @var bool $isSendOrderToAmocrm - Отправить заказ в amoCRM?
         * @var bool $isOrderTransferredAmocrm - Заказ уже (ранее) передан в amoCRM?
         * @var string $propCodeSendOrderToAmocrm - код свойства: Отправить заказ в amoCRM
         * @var string $propCodeTransferredAmocrm - код свойства: Заказ уже (ранее) передан в amoCRM?
         */
        $isSendOrderToAmocrm = false;
        $isOrderTransferredAmocrm = true;
        $propCodeSendOrderToAmocrm = 'SEND_ORDER_TO_AMOCRM';
        $propCodeTransferredAmocrm = 'ORDER_TRANSFERRED_AMOCRM';

        /** 
         * @var \Bitrix\Sale\Order $order 
         */
        $order = $event->getParameter("ENTITY");

        /** 
         * @var array $orderData Массив данных заказа
         */
        $orderData = [];
        $orderData['ID'] = $order->getId();
        $orderData['PRICE'] = $order->getPrice();
        $orderData['PERSON_TYPE_ID'] = $order->getPersonTypeId();

        /** 
         * @var \Bitrix\Sale\PropertyValueCollection $propertyCollection 
         */
        $propertyCollection = $order->getPropertyCollection();

        /**
         * @var array Массив данных полей заказа
         */
        $propsData = [];

        /**
         * @var \Bitrix\Sale\PropertyValue $propertyItem
         */
        foreach ($propertyCollection as $propertyItem) {
            if (!empty($propertyItem->getField("CODE"))) {
                $propsData[$propertyItem->getField("CODE")] = [];
                $propsData[$propertyItem->getField("CODE")]['CODE'] = $propertyItem->getField("CODE");
                $propsData[$propertyItem->getField("CODE")]['ID'] = $propertyItem->getField("ID");
                $propsData[$propertyItem->getField("CODE")]['NAME'] = $propertyItem->getField("NAME");
                $propsData[$propertyItem->getField("CODE")]['VALUE'] = trim($propertyItem->getValue());
            }

            $propertyInfo = $propertyItem->getProperty();
        }

        if (isset($propsData[$propCodeSendOrderToAmocrm]['VALUE'])) {
            $isSendOrderToAmocrm = !($propsData[$propCodeSendOrderToAmocrm]['VALUE'] != 'Y');
        }

        if (isset($propsData[$propCodeTransferredAmocrm]['VALUE'])) {
            $isOrderTransferredAmocrm = !($propsData[$propCodeTransferredAmocrm]['VALUE'] != 'Y');
        }

        /**
         * Передать заказ в сделку AmoCRM
         */
        if ($isSendOrderToAmocrm && !$isOrderTransferredAmocrm) {
            if (self::TransferOrderToDealAmocrm($orderData, $propsData)) {
                /**
                 * Установка значения свойству "Заказ передан в amoCRM"
                 */
                if (isset($propsData[$propCodeTransferredAmocrm]['ID'])) {
                    $propertyValue = $propertyCollection->getItemById($propsData[$propCodeTransferredAmocrm]['ID']);
                    $res = $propertyValue->setField('VALUE', 'Y');
                    if (!$res->isSuccess()) {
                        /** @todo: Как обрабатывать ошибку ??? */
                    }

                    // Запись изменений
                    $event->addResult(
                        new \Bitrix\Main\EventResult(
                            \Bitrix\Main\EventResult::SUCCESS, $order
                        )
                    );
                }
            }
        }
    }

    public static function TransferOrderToDealAmocrm($orderData, $propsData) {
        /**
         * Коды свойств заказа
         * @todo: Убрать из метода
         */
        $propCodeEmail = 'EMAIL';
        $propCodeFioContactface = 'FIO_CONTACTFACE';
        $propCodePersonalPhone = 'PERSONAL_PHONE';
        $propCodeDeliveryDate = 'DELIVERY_DATE';
        $propCodeDeliveryAddress = 'DELIVERY_ADDRESS';
        $propCodeShop = 'SHOP';

        /**
         * Идентификаторы полей AmoCRM
         * @todo: Убрать из метода
         */
        $idsAmoContactFields = [
            'phone' => [
                'field_id' => 382627,
                'field_code' => 'PHONE',
                'field_name' => 'Телефон',
                'enum_code' => 'WORK',
                'enum_id' => 593719,
            ],
            'email' => [
                'field_id' => 382629,
                'field_code' => 'EMAIL',
                'field_name' => 'Email',
                'enum_code' => 'WORK',
                'enum_id' => 593731,
            ]
        ];
        $idsAmoLeadFields = [
            'orderID' => 393125,//Номер заказа
            'primaryOrder' => 666635,//Повторный заказ
            'orderLink' => 391107,//Ссылка на заказ
            'orderDate' => 383371,//Дата заказа
            'delivery' => 383357,//Способ доставки
            'deliveryAddress' => 392385,//Адрес доставки
        ];
        $idsAmoFieldsList = [
            'primaryOrder' => [//Повторный заказ
                1212061,//ДА
                1212063,//Первый заказ
                1212069,//Заказ магазина
            ],
            'delivery' => [//Способ доставки
                0 => 594573,//ш.Авиаторов 9
                1 => 594575,//ТЦ Привоз ТЗР
                2 => 594577,//Козловская 34Б
                3 => 602819,//ТЦ Идея Волжский
                4 => 602821,//ТЦ Цитрус 7Ветров
                5 => 602823,//ТРК Мармелад
                6 => 1205625,//Бахтурова 12Ж
                7 => 1271879,//ТЦ Арбуз
                8 => 1265181,//ТЦ СтройГрад ВЛЖ
                9 => 602825,//Ахтубинск
                10 => 1259255,//ЦУМ Влж
                11 => 602827,//СВ Котово
                12 => 602829,//Курьер
                13 => 610129,//СДЭК
                14 => 610131,//ПчРФ
                15 => 610133,//ТК
            ],
        ];

        $amoObject = new AmoCrmMain();

        /** Почта */
        if (!isset($propsData[$propCodeEmail]['VALUE'])) return;
        $email = $propsData[$propCodeEmail]['VALUE'];

        /** ID контакта */
        $contactID = $amoObject->getContactIDByQuery($email);

        /** первый или повторный заказ? */
        $primaryOrderKey = empty($contactID) ? 1 : 0;

        /** Если контакт не найден, то создаем */
        if (!$contactID) {
            /** ФИО */
            $userName = !empty($propsData[$propCodeFioContactface]['VALUE']) ? $propsData[$propCodeFioContactface]['VALUE'] : '';
            $arUserName = explode(' ', $userName, 3);

            /** Телефон */
            $phone = !empty($propsData[$propCodePersonalPhone]['VALUE']) ? $propsData[$propCodePersonalPhone]['VALUE'] : '';

            $dataAddcontact = [
                'name' => $userName,
                'first_name' => isset($arUserName[0]) ? $arUserName[0] : $userName,
                'last_name' => isset($arUserName[1]) ? $arUserName[1] : '',
                'custom_fields_values' => [
                   [//Телефон
                        'field_id' => $idsAmoContactFields['phone']['field_id'],
                        // 'field_code' => $idsAmoContactFields['phone']['field_code'],
                        // 'field_name' => $idsAmoContactFields['phone']['field_name'],
                        'values' => [
                            [
                                'value' => $phone,
                                // 'enum_code' => $idsAmoContactFields['phone']['enum_code'],
                                // 'enum_id' => $idsAmoContactFields['phone']['enum_id'],
                            ],
                        ],
                    ],
                    [//Почта
                        'field_id' => $idsAmoContactFields['email']['field_id'],
                        // 'field_code' => $idsAmoContactFields['email']['field_code'],
                        // 'field_name' => $idsAmoContactFields['email']['field_name'],
                        'values' => [
                            [
                                'value' => $email,
                                // 'enum_code' => $idsAmoContactFields['email']['enum_code'],
                                // 'enum_id' => $idsAmoContactFields['email']['enum_id'],
                            ],
                        ],
                    ],
                ],
            ];

            /** Преобразование кодировки, если необходимо */
            if (mb_strtolower(LANG_CHARSET) != 'utf-8') {
                $dataAddcontact = \Bitrix\Main\Text\Encoding::convertEncoding($dataAddcontact, LANG_CHARSET, 'utf-8');
            }

            $urlContacts = $amoObject->getUrlByAction('contacts');
            $addContactReq = $amoObject->requestApiAmoSrm(array($dataAddcontact), $urlContacts);

            if ($addContactReq['DATA']['_embedded']['contacts'][0]['id']) {
                $contactID = $addContactReq['DATA']['_embedded']['contacts'][0]['id'];
            } else {
                $contactID = 0;
            }
        }

        /** Контакт найден или создан */
        if (!$contactID) {
            return false;
        }

        /** Дата доставки заказа (указанная заказчиком) */
        $orderDeliveryDate = !empty($propsData[$propCodeDeliveryDate]['VALUE']) ? $propsData[$propCodeDeliveryDate]['VALUE'] : 0;

        /** адрес доставки//адрес пункта выдачи */
        $deliveryAddress = !empty($propsData[$propCodeDeliveryAddress]['VALUE']) ? $propsData[$propCodeDeliveryAddress]['VALUE'] : (!empty($propsData[$propCodeShop]['VALUE']) ? $propsData[$propCodeShop]['VALUE'] : '');

        /** Способ доставки !!! */
        // $deliveryKey = ;

        /** Данные новой сделки */
        $dataNewLead = [
            "name" => strval($orderData['ID']),//Название сделки
            "created_by" => 0,
            "price" => $orderData['PRICE'],//Бюджет сделки//сумма заказа
            "custom_fields_values" => [
                [//Номер заказа
                    "field_id" => $idsAmoLeadFields['orderID'],
                    "values" => [
                        [
                            "value" => strval($orderData['ID']),
                        ],
                    ],
                ],
                [//Повторный заказ
                    "field_id" => $idsAmoLeadFields['primaryOrder'],
                    "values" => [
                        [
                            "enum_id" => $idsAmoFieldsList['primaryOrder'][$primaryOrderKey],
                        ],
                    ],
                ],
                [//ссылка на заказ
                    "field_id" => $idsAmoLeadFields['orderLink'],
                    "values" => [
                        [
                            "value" => ((\CMain::IsHTTPS()) ? "https://" : "http://").$_SERVER['HTTP_HOST'].'/bitrix/admin/sale_order_view.php?ID='.$orderData['ID'],
                        ],
                    ],
                ],
                [//адрес доставки//адрес пункта выдачи
                    "field_id" => $idsAmoLeadFields['deliveryAddress'],
                    "values" => [
                        [
                            "value" => $deliveryAddress,
                        ],
                    ],
                ],
            ],
        ];

        /** дата заказа */
        if ($orderDeliveryDate) {
            $dataNewLead['custom_fields_values'][] = [
                "field_id" => $idsAmoLeadFields['orderDate'],
                "values" => [
                    [
                        "value" => strtotime($orderDeliveryDate),
                    ],
                ],
            ];
        }

        //способ доставки //??? Есть несоответствия
        // if (isset($deliveryKey, $idsAmoFieldsList['delivery'][$deliveryKey])) {
        //     $dataNewLead['custom_fields_values'][] = [
        //         "field_id" => $idsAmoLeadFields['delivery'],
        //         "values" => [
        //             [
        //                 "enum_id" => $idsAmoFieldsList['delivery'][$deliveryKey],
        //             ],
        //         ],
        //     ];
        // }

        /** Преобразование кодировки, если необходимо */
        if (mb_strtolower(LANG_CHARSET) != 'utf-8') {
            $dataNewLead = \Bitrix\Main\Text\Encoding::convertEncoding($dataNewLead, LANG_CHARSET, 'utf-8');
        }

        /** Создание сделки */
        $urlLead = $amoObject->getUrlByAction('new_lead');
        $newLeadReq = $amoObject->requestApiAmoSrm(array($dataNewLead), $urlLead);
        if ($newLeadReq['DATA']['_embedded']['leads'][0]['id']) {
            $LeadID = $newLeadReq['DATA']['_embedded']['leads'][0]['id'];
        } else {
            // $LeadID = 0;
            return false;
        }

        /** Связывание сделки и контакта */
        if ($LeadID) {
            $dataLink = array(
                'to_entity_id' => $contactID,
                'to_entity_type' => "contacts",
                "metadata" =>  array(
                    "is_main" => true,
                )
            );
            $urlLink = $amoObject->getUrlByAction('new_lead');
            $urlLink .= '/'.$LeadID.'/link';
            $newLinkReq = $amoObject->requestApiAmoSrm(array($dataLink), $urlLink);

            if (!empty($newLinkReq['DATA']['_embedded']['links'][0])) {
                return true;
            }
        }
    }
}
?>
