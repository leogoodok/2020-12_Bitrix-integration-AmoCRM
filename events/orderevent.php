<?
if (!class_exists('AmoCrmMain')) die();

/** @example Перенести в init.php */
\Bitrix\Main\EventManager::getInstance()->addEventHandler(
    'sale',
    'OnOrderSave',
    ['orderEvent', 'OnOrderSaveH'],
);

/**
 * Класс обработчика "Экспорт заказа в сделку amoCRM"
 * 
 * @warning Использовать при версии модуля "sale" до "15.5.0"
 * @note В обработчике запускается обновление значения свойства "Заказ передан в amoCRM" 
 * @note Используется защита от зацикливания
 * 
 * Передача заказа в сделку amoCRM по условию (значению свойств заказа)
 * "Отправить заказ в amoCRM" == "Y" && "Заказ передан в amoCRM" != "Y"
 * 
 * @note Должны быть созданы свойства заказа:
 * ["NAME"]=> "Отправить заказ в amoCRM"
 * ["CODE"]=> "SEND_ORDER_TO_AMOCRM"
 * ["TYPE"]=> "CHECKBOX"
 * ["VALUE"]=> "Y" - "Да" и "N" - "Нет"
 * 
 * ["NAME"]=> "Заказ передан в amoCRM"
 * ["CODE"]=> "ORDER_TRANSFERRED_AMOCRM"
 * ["TYPE"]=> "SELECT"
 * ["VALUE"]=> "Y" - "Да" и "N" - "Не задано"
 */
class orderEvent {
    /**
     * @property bool "на этом хите обработчик уже запущен?" (Защита от зацикливания)
     */
    protected static $handlerDisallow = false;

    static public function OnOrderSaveH($ID, $arFields, $orderFields, $isNew)
    {
        /**
         * Если обработчик уже запускался на этом хите, ТО прерываем выполнение обработчика (Защита от зацикливания)
         * @note При завершении с передачей "false" (return false) повторно выполняемый CSaleOrder::Update 
         * будет прерван и значения свойств не обновятся
         */
        if (self::$handlerDisallow) return;

        /* Взводим флаг запуска Обработчика */
        self::$handlerDisallow = true;

        /** @todo: Убрать из класса */
        $idsOrderProps = [
            '1' => [//PERSON_TYPE_ID == 1
                'DELIVERY_DATE' => '1',//Дата
                'DELIVERY_DATE_OUT' => '9',//Дата доставки
                'SHOP' => '5',//Адрес пункта выдачи
                'DELIVERY_ADDRESS' => '7',//Адрес доставки
                'EMAIL' => '11',//E-mail
                'FIO_CONTACTFACE' => '20',//ФИО Получателя
                'PERSONAL_PHONE' => '21',//Телефон для связи
                'ORGLEGALFORM' => '28',//Ответственный
                'SEND_ORDER_TO_AMOCRM' => '29', //Отправить заказ в amoCRM
                'ORDER_TRANSFERRED_AMOCRM' => '31', //Заказ передан в amoCRM
            ],
            '2' => [//PERSON_TYPE_ID == 2
                'DELIVERY_DATE' => '2',//Дата
                'DELIVERY_DATE_OUT' => '10',//Дата
                'SHOP' => '6',//"Магазина самовывоза"
                'DELIVERY_ADDRESS' => '8',//Адрес доставки
                'EMAIL' => '12',//E-mail
                'FIO_CONTACTFACE' => '18',//ФИО Контактного лица
                'PERSONAL_PHONE' => '19',//Контактный телефон
                'ORGLEGALFORM' => '13',//Ответственный
                'SEND_ORDER_TO_AMOCRM' => '30', //Отправить заказ в amoCRM
                'ORDER_TRANSFERRED_AMOCRM' => '32', //Заказ передан в amoCRM
            ],
        ];

        /** @todo: Убрать из класса */
        $idsAmoFields = [
            'orderID' => 393125,//Номер заказа
            'primaryOrder' => 666635,//Повторный заказ
            'orderLink' => 391107,//Ссылка на заказ
            'orderDate' => 383371,//Дата заказа
            'delivery' => 383357,//Способ доставки
            'deliveryAddress' => 392385,//Адрес доставки
        ];

        /** @todo: Убрать из класса */
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

        /** Отправить заказ в amoCRM ? */
        if (isset($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['SEND_ORDER_TO_AMOCRM']])) {
            $isSendOrderToAmocrm = !($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['SEND_ORDER_TO_AMOCRM']] != 'Y');
        }

        /** Заказ передан в amoCRM ? */
        if (isset($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['ORDER_TRANSFERRED_AMOCRM']])) {
            $isOrderTransferredAmocrm = !($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['ORDER_TRANSFERRED_AMOCRM']] != 'Y');
        }

        /**
         * Передать заказ в сделку AmoCRM
         */
        if ($isSendOrderToAmocrm && !$isOrderTransferredAmocrm) {

            //Получить ID свойства 'ORDER_TRANSFERRED_AMOCRM' текущего заказа
            $db_props = \CSaleOrderPropsValue::GetOrderProps($ID);
            while ($props = $db_props->Fetch()) {
                if ($props["CODE"] == 'ORDER_TRANSFERRED_AMOCRM') {
                    $IDpropTransferredAmocrm = $props["ID"];
                }
            }
            unset($db_props);

            /** Получить "Дату создания заказа" */
            if ($arOrder = \CSaleOrder::GetByID($ID)) {
                $dateInsertOrder = isset($arOrder['DATE_INSERT']) ? $arOrder['DATE_INSERT'] : '';
            }
            unset($arOrder);

            $amoObject = new AmoCrmMain();

            /** Почта */
            $email = !empty($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['EMAIL']]) ? $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['EMAIL']] : '';

            $contactID = $amoObject->getContactIDByQuery($email);

            /** первый или повторный заказ ? */
            $primaryOrderKey = empty($contactID) ? 1 : 0;

            /** Если контакт не найден, то создаем */
            if (!$contactID) {
                /** ФИО */
                $userName = !empty($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['FIO_CONTACTFACE']]) ? $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['FIO_CONTACTFACE']] : '';
                $arUserName = explode(' ', $userName, 3);

                /** Телефон */
                $phone = !empty($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['PERSONAL_PHONE']]) ? $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['PERSONAL_PHONE']] : '';

                $dataAddcontact = [
                    'name' => $userName,
                    'first_name' => isset($arUserName[0]) ? $arUserName[0] : $userName,
                    'last_name' => isset($arUserName[1]) ? $arUserName[1] : '',
                    'custom_fields_values' => [
                       [//Телефон
                            'field_id' => 382627,
                            // 'field_code' => 'PHONE',
                            // 'field_name' => 'Телефон',
                            'values' => [
                                [
                                    'value' => $phone,
                                    // 'enum_code' => 'WORK',
                                    // 'enum_id' => 593719,
                                ],
                            ],
                        ],
                        [//Почта
                            'field_id' => 382629,
                            // 'field_code' => 'EMAIL',
                            // 'field_name' => 'Email',
                            'values' => [
                                [
                                    'value' => $email,
                                    // 'enum_code' => 'WORK',
                                    // 'enum_id' => 593731,
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
                $addContactReq = $amoObject->requestApiAmoSrm([$dataAddcontact], $urlContacts);

                if ($addContactReq['DATA']['_embedded']['contacts'][0]['id']) {
                    $contactID = $addContactReq['DATA']['_embedded']['contacts'][0]['id'];
                } else {
                    $contactID = 0;
                }
            }

            /** Контакт найден или создан */
            if ($contactID) {
                //Дата доставки (указанная клиентом)
                // $orderDate = (!empty($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['DELIVERY_DATE']]) ? $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['DELIVERY_DATE']] : 0);
                //адрес доставки//адрес пункта выдачи
                $deliveryAddress = (!empty($orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['DELIVERY_ADDRESS']]) ? $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['DELIVERY_ADDRESS']] : $orderFields['ORDER_PROP'][$idsOrderProps[$orderFields['PERSON_TYPE_ID']]['SHOP']]);
                //Ключ способы доставки в массиве $idsAmoFieldsList['delivery']

                if ($orderFields['DELIVERY_ID'] == 1) {//Самовывоз
                    if ($deliveryAddress == 'Волгоград, шоссе Авиаторов, 9 (1-2 дня*)') {
                        $deliveryKey = 0;
                    } else if ($deliveryAddress == 'Волгоград, ул.Козловская, 34Б (1-2 дня*)') {
                        $deliveryKey = 2;
                    } else if ($deliveryAddress == ' Волгоград, ул. им. Землячки, 110Б ТРК "КомсоМОЛЛ"  (1-2 дня*) ') {
                        //???
                    } else if ($deliveryAddress == 'Волгоград, ул.8-й Воздушной Армии, 28А (1-3 дня*)') {
                        //???
                    } else if ($deliveryAddress == 'Волгоград, ул. Ополченская 11К ТЦ "Привоз" (1-3 дня*)') {
                        $deliveryKey = 1;
                    } else if ($deliveryAddress == 'Ахтубинск, ул.Волгоградская, 99А (3-10 дней*)') {
                        $deliveryKey = 9;
                    } else if ($deliveryAddress == 'Пункт самовывоза интернет-заказов. Котово, ул.Победы, 14, Маг. "Меридиан" (2-4 дня*)') {
                        //???
                    } else if ($deliveryAddress == 'Пункт самовывоза интернет-заказов. Астраханская обл., с. Никольское, ул. Московская, 54А, Маг. "Выгодный" (3-10 дней*)') {
                        //???
                    }
                } else if ($orderFields['DELIVERY_ID'] == 2) {//Курьер (Волгоград и Волжский)
                    $deliveryKey = 12;
                } else if ($orderFields['DELIVERY_ID'] == 3) {//Доставка в другие регионы
                    //??? Согласовать добавление в amoCRM
                }

                /** Данные новой сделки */
                $dataNewLead = [
                    "name" => strval($ID),//Название сделки
                    "created_by" => 0,
                    "price" => $arFields['PRICE'],//Бюджет сделки//сумма заказа
                    "custom_fields_values" => [
                        [//Номер заказа
                            "field_id" => $idsAmoFields['orderID'],
                            "values" => [
                                [
                                    "value" => strval($ID),
                                ],
                            ],
                        ],
                        [//Повторный заказ
                            "field_id" => $idsAmoFields['primaryOrder'],
                            "values" => [
                                [
                                    "enum_id" => $idsAmoFieldsList['primaryOrder'][$primaryOrderKey],
                                ],
                            ],
                        ],
                        [//ссылка на заказ
                            "field_id" => $idsAmoFields['orderLink'],
                            "values" => [
                                [
                                    "value" => ((\CMain::IsHTTPS()) ? "https://" : "http://").$_SERVER['HTTP_HOST'].'/bitrix/admin/sale_order_detail.php?ID='.$ID.'',
                                ],
                            ],
                        ],
                        [//адрес доставки//адрес пункта выдачи
                            "field_id" => $idsAmoFields['deliveryAddress'],
                            "values" => [
                                [
                                    "value" => $deliveryAddress,
                                ],
                            ],
                        ],
                    ],
                ];

                /** дата создания заказа */
                if ($dateInsertOrder) {//$orderDate
                    $dataNewLead['custom_fields_values'][] = [
                        "field_id" => $idsAmoFields['orderDate'],
                        "values" => [
                            [
                                "value" => strtotime($dateInsertOrder),//$orderDate
                            ],
                        ],
                    ];
                }

                /** способ доставки //??? Есть несоответствия ! */
                if (isset($deliveryKey, $idsAmoFieldsList['delivery'][$deliveryKey])) {
                    $dataNewLead['custom_fields_values'][] = [
                        "field_id" => $idsAmoFields['delivery'],
                        "values" => [
                            [
                                "enum_id" => $idsAmoFieldsList['delivery'][$deliveryKey],
                            ],
                        ],
                    ];
                }

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
                    $LeadID = 0;
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

                    //Обработка ответа
                    if (!empty($newLinkReq['DATA']['_embedded']['links'][0])) {
                        //Установка свойства "Заказ передан в amoCRM"
                        if (!(\CSaleOrderPropsValue::Update($IDpropTransferredAmocrm, array("VALUE"=>"Y")))) {
                            /** @todo Добавить обработку ошибок */
                        }
                    }
                }
            }
        }

        /* 
         * Снимает флаг запуска (вновь разрешаем запускать обработчик)
         */ 
        self::$handlerDisallow = false;
    }
}
?>
