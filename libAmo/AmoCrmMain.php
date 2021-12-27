<?
/**
 * Class AmoCrmMain
 * 
 * Интеграции с API amoCRM версии v4
 * 
 * @version Version 1.2 for PHP v.5.6
 * @author LeoGood 2020-12
 */
/** @todo Перенести определение константы "относительный путь к файлу токенов" */
define('AMOCRM_PATH_TOKEN_FILE', '/bitrix/php_interface/libAmo/tmp/token_info.json');

class AmoCrmMain
{
    const REQUEST_TIMEOUT = 15;
    private $TOKEN_FILE = '';
    private $http;
    private $subdomain;
    private $redirectUri;
    private $clientId;
    private $clientSecret;
    private $authorizationCode;
    private $accessToken;
    private $refreshToken;
    private $tokenType;
    private $expiresIn;

    public function __construct()
    {
        if (defined('AMOCRM_PATH_TOKEN_FILE')) {
            $this->setTokenFile(AMOCRM_PATH_TOKEN_FILE);
        }

        if ($tokenInfo = $this->getInfoTokenFromFile()) {
            $this->setHttp($tokenInfo['http']);
            $this->setSubdomain($tokenInfo['subdomain']);
            $this->setRedirectUri($tokenInfo['redirect_uri']);
            $this->setClientId($tokenInfo['client_id']);
            $this->setClientSecret($tokenInfo['client_secret']);
            $this->setAuthorizationCode($tokenInfo['code']);
            $this->setAccessToken($tokenInfo['access_token']);
            $this->setRefreshToken($tokenInfo['refresh_token']);
            $this->setTokenType($tokenInfo['token_type']);
            $this->setExpiresIn($tokenInfo['expires_in']);
        }
    }

    public function getTokenFile()
    {
        return $this->TOKEN_FILE;
    }
    public function setTokenFile($string)
    {
        $this->TOKEN_FILE = $string;
    }
    public function getHttp()
    {
        return $this->http;
    }
    public function setHttp($string)
    {
        $this->http = $string;
    }
    public function getSubdomain()
    {
        return $this->subdomain;
    }
    public function setSubdomain($string) {
        $this->subdomain = $string;
    }
    public function getRedirectUri()
    {
        return $this->redirectUri;
    }
    public function setRedirectUri($string)
    {
        $this->redirectUri = $string;
    }
    public function getClientId()
    {
        return $this->clientId;
    }
    public function setClientId($string)
    {
        $this->clientId = $string;
    }
    public function getClientSecret()
    {
        return $this->clientSecret;
    }
    public function setClientSecret($string)
    {
        $this->clientSecret = $string;
    }
    public function getAuthorizationCode()
    {
        return $this->authorizationCode;
    }
    public function setAuthorizationCode($string)
    {
        $this->authorizationCode = $string;
    }
    public function getAccessToken()
    {
        return $this->accessToken;
    }
    public function setAccessToken($string)
    {
        $this->accessToken = $string;
    }
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }
    public function setRefreshToken($string)
    {
        $this->refreshToken = $string;
    }
    public function getTokenType()
    {
        return $this->tokenType;
    }
    public function setTokenType($string)
    {
        $this->tokenType = $string;
    }
    public function getExpiresIn()
    {
        return $this->expiresIn;
    }
    public function setExpiresIn($string)
    {
        $this->expiresIn = $string;
    }

    /**
     * Получение токенов из json-файла
     * @return array|null|false
     */
    public function getInfoTokenFromFile()
    {
        if (!file_exists($_SERVER['DOCUMENT_ROOT'].$this->TOKEN_FILE)) {
            /** @todo При необходимости использовать исключение */
            // throw new ErrorException('Access token file not found', 401);
            return;
        }

        $arInfoToken = json_decode(file_get_contents($_SERVER['DOCUMENT_ROOT'].$this->TOKEN_FILE), true);
        if (isset($arInfoToken) && is_array($arInfoToken)) {
            return [
                'http' => isset($arInfoToken['http']) ? $arInfoToken['http'] : '',
                'subdomain' => isset($arInfoToken['subdomain']) ? $arInfoToken['subdomain'] : '',
                'redirect_uri' => isset($arInfoToken['redirect_uri']) ? $arInfoToken['redirect_uri'] : '',
                'client_id' => isset($arInfoToken['client_id']) ? $arInfoToken['client_id'] : '',
                'client_secret' => isset($arInfoToken['client_secret']) ? $arInfoToken['client_secret'] : '',
                'code' => isset($arInfoToken['code']) ? $arInfoToken['code'] : '',
                'access_token' => isset($arInfoToken['access_token']) ? $arInfoToken['access_token'] : '',
                'refresh_token' => isset($arInfoToken['refresh_token']) ? $arInfoToken['refresh_token'] : '',
                'token_type' => isset($arInfoToken['token_type']) ? $arInfoToken['token_type'] : '',
                'expires_in' => isset($arInfoToken['expires_in']) ? $arInfoToken['expires_in'] : '',
            ];
        } else {
            /** @todo При необходимости использовать исключение */
            // throw new ErrorException('Invalid access token ' . var_export($arInfoToken, true));
            return false;
        }
    }

    /**
     * Запись токенов в json-файл
     * @param array $arInfoToken
     * @return bool|null
     */
    public function saveInfoToken($arInfoToken)
    {
        if (is_array($arInfoToken)) {
            $data = [
                'http' => isset($arInfoToken['http']) ? $arInfoToken['http'] : '',
                'subdomain' => isset($arInfoToken['subdomain']) ? $arInfoToken['subdomain'] : '',
                'redirect_uri' => isset($arInfoToken['redirect_uri']) ? $arInfoToken['redirect_uri'] : '',
                'client_id' => isset($arInfoToken['client_id']) ? $arInfoToken['client_id'] : '',
                'client_secret' => isset($arInfoToken['client_secret']) ? $arInfoToken['client_secret'] : '',
                'code' => isset($arInfoToken['code']) ? $arInfoToken['code'] : '',
                'access_token' => isset($arInfoToken['access_token']) ? $arInfoToken['access_token'] : '',
                'refresh_token' => isset($arInfoToken['refresh_token']) ? $arInfoToken['refresh_token'] : '',
                'token_type' => isset($arInfoToken['token_type']) ? $arInfoToken['token_type'] : '',
                'expires_in' => isset($arInfoToken['expires_in']) ? $arInfoToken['expires_in'] : '',
            ];

            return (file_put_contents($_SERVER['DOCUMENT_ROOT'].$this->TOKEN_FILE, json_encode($data)) !== false);
        } else {
            /** @todo При необходимости использовать исключение */
            // throw new ErrorException('Invalid access token ' . var_export($arInfoToken, true));
            return false;
        }
    }

    /**
     * Обновление токенов в json-файле данными из свойств объекта класса
     * @return bool|null
     */
    public function updateInfoTokenToFile()
    {
        $arInfoToken = [
            'http' => $this->getHttp(),
            'subdomain' => $this->getSubdomain(),
            'redirect_uri' => $this->getRedirectUri(),
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $this->getAuthorizationCode(),
            'access_token' => $this->getAccessToken(),
            'refresh_token' => $this->getRefreshToken(),
            'token_type' => $this->getTokenType(),
            'expires_in' => $this->getExpiresIn(),
        ];

        return $this->saveInfoToken($arInfoToken);
    }

    /**
     * @param string $action
     * @return string
     */
    public function getUrlByAction($action)
    {
        $result = '';
        switch ($action) {
            case 'access_token': {
                $result = "{$this->getHttp()}://{$this->getSubdomain()}.amocrm.ru/oauth2/access_token";
            } break;
            case 'new_lead': {
                $result = "{$this->getHttp()}://{$this->getSubdomain()}.amocrm.ru/api/v4/leads";
            } break;
            case 'contacts': {
                $result = "{$this->getHttp()}://{$this->getSubdomain()}.amocrm.ru/api/v4/contacts";
            } break;
        }

        return $result;
    }

    /**
     * Отправка GET/POST-запроса в API amoCRM
     * @param array $data
     * @param string $link
     * @param string $type
     * @param array $headers
     * @return array
     */
    public function requestPost($data, $link, $type = 'POST', $headers = array())
    {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-oAuth-client/1.0');
        curl_setopt($curl,CURLOPT_URL, $link);
        curl_setopt($curl,CURLOPT_HTTPHEADER,['Content-Type:application/json']);
        curl_setopt($curl,CURLOPT_HEADER, false);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST, $type);
        curl_setopt($curl,CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST, 2);
        if ($headers) curl_setopt($curl,CURLOPT_HTTPHEADER, $headers);
        $out = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $errors = [
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not found',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
        ];

        try {
            $code = intval($code);
            // $response = json_decode($out, true);
            $response['REQUEST_CODE'] = $code;
            $response['IS_REQUEST_STATUS_OK'] = ($code >= 200 && $code <= 204);
            if (!$response['IS_REQUEST_STATUS_OK']) {
                $response['REQUEST_ERROR_CODE'] = $code;
                $response['REQUEST_ERROR'] = isset($errors[$code]) ? $errors[$code] : 'Undefined error';
            }
            $response['DATA'] = is_array($response_data = json_decode($out, true)) ? $response_data : [];
        } catch (\Exception $e) {
            $response = [];
            $response['IS_REQUEST_STATUS_OK'] = false;
            $response['REQUEST_ERROR_CODE'] = $e->getCode();
            $response['REQUEST_ERROR'] = $e->getMessage();
            $response['DATA'] = null;
        }

        return $response;
    }

    /**
     * Подготовка, отправка запроса в API amoCRM и обработка корректности ответа
     * с функционалом "обновления токенов"
     * @param array $data
     * @param string $link
     * @param string $type
     * @param array $headers
     * @return array
     * 
     * @todo Переделать - разделить на два метода
     */
    public function requestApiAmoSrm($data, $link, $type = 'POST', $headers = [])
    {
        $headers = array_merge([], $headers, ["Authorization: {$this->getTokenType()} {$this->getAccessToken()}"]);

        $result = $this->requestPost($data, $link, $type, $headers);

        //Проверка ответа - ошибка авторизации
        if (!$result['IS_REQUEST_STATUS_OK'] && $result['REQUEST_CODE'] == 401) {
            //Обновление токенов
            if($this->getAccessTokenByRefreshToken()) {
                //Повторный запрос
                $result = $this->requestPost($data, $link, $type, $headers);
            }
        }

        return $result;
    }

    /**
     * Получение Access токена по коду авторизации. Запись в свойства объекта и json-файл
     * @return bool|null
     */
    public function getAccessTokenByAuthCode()
    {
        $data = [
            "client_id" => $this->getClientId(),
            "client_secret" => $this->getClientSecret(),
            "grant_type" => "authorization_code",
            "code"=> $this->getAuthorizationCode(),
            "redirect_uri" => "{$this->getHttp()}://{$this->getRedirectUri()}/",
        ];
        $url = $this->getUrlByAction('access_token');
        $request = $this->requestPost($data, $url);
        $request_data = $request['DATA'];


        /** @todo Добавить! обработку ошибок ответа-запроса */
        if (!$request['IS_REQUEST_STATUS_OK'] || empty($request_data)) {
            return;
        }

        //Обработка наличия данных в ответе
        if (!($request_data['access_token'] && $request_data['refresh_token'] && $request_data['token_type'] && $request_data['expires_in'])) {
            return false;
        }

        //Запись в свойства
        $this->setAccessToken($request_data['access_token']);
        $this->setRefreshToken($request_data['refresh_token']);
        $this->setTokenType($request_data['token_type']);
        $this->setExpiresIn($request_data['expires_in']);

        //Обновление данных в JSON-файле
        return $this->updateInfoTokenToFile();
    }

    /**
     * Получение (Обновление) Access и Refresh токенов по Refresh токену. Запись в свойства объекта и json-файл
     * @return bool|null
     */
    public function getAccessTokenByRefreshToken()
    {
        $data = [
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->getRefreshToken(),
            "redirect_uri" => "{$this->getHttp()}://{$this->getRedirectUri()}/",
        ];
        $url = $this->getUrlByAction('access_token');
        $request = $this->requestPost($data, $url);
        $request_data = $request['DATA'];

        /** @todo Добавить! обработку ошибок ответа-запроса */
        if (!$request['IS_REQUEST_STATUS_OK'] || empty($request_data)) {
            return;
        }

        //Обработка наличия данных в ответе
        if (!($request_data['access_token'] && $request_data['refresh_token'] && $request_data['token_type'] && $request_data['expires_in'])) {
            return false;
        }

        //Запись в свойства
        $this->setAccessToken($request_data['access_token']);
        $this->setRefreshToken($request_data['refresh_token']);
        $this->setTokenType($request_data['token_type']);
        $this->setExpiresIn($request_data['expires_in']);

        //Обновление данных в JSON-файле
        return $this->updateInfoTokenToFile();
    }

    /**
     * Получение ID-контакта amoCRM по поисковому запросу (Поиск по заполненным полям сущности)
     * @param string $query
     * @return int
     */
    public function getContactIDByQuery($query)
    {
        $contactID = 0;
        $url = $this->getUrlByAction('contacts');
        $url .= '?query='.$query.'&limit=1';
        $searchContactLeadReq = $this->requestApiAmoSrm([], $url, 'GET');
        if (isset($searchContactLeadReq['DATA']['_embedded']['contacts'][0]['id'])) {
            $contactID = $searchContactLeadReq['DATA']['_embedded']['contacts'][0]['id'];
        }

        return $contactID;
    }

    /**
     * Создание контакта amoCRM
     * @param array $arrDataContacts
     * @return int
     */
    public function addContactAmoSrm($arrDataContacts)
    {
        $url = $this->getUrlByAction('contacts');
        $result = $this->requestApiAmoSrm([$arrDataContacts], $url);

        if ($result['DATA']['_embedded']['contacts'][0]['id']) {
            $contactID = $result['DATA']['_embedded']['contacts'][0]['id'];
        } else {
            $contactID = 0;
        }

        return $contactID;
    }

    /**
     * Создание сделки amoCRM
     * @param array $arrDataLead
     * @return int
     */
    public function addLeadAmoSrm($arrDataLead)
    {
        $url = $this->getUrlByAction('new_lead');
        $result = $this->requestApiAmoSrm([$arrDataLead], $url);

        if ($result['DATA']['_embedded']['leads'][0]['id']) {
            $leadID = $result['DATA']['_embedded']['leads'][0]['id'];
        } else {
            $leadID = 0;
        }

        return $leadID;
    }

    /**
     * Связывание сделки и контакта
     * @param int $leadID
     * @param int $contactID
     * @return bool
     */
    public function linkingContactToLead($leadID, $contactID)
    {
        $dataLink = [
            'to_entity_id' => $contactID,
            'to_entity_type' => "contacts",
            "metadata" => [
                "is_main" => true,
            ],
        ];
        $urlLink = $this->getUrlByAction('new_lead');
        $urlLink .= '/'.$leadID.'/link';
        $newLinkReq = $this->requestApiAmoSrm(array($dataLink), $urlLink);

        return (!empty($newLinkReq['DATA']['_embedded']['links'][0]));
    }
}
?>
