<?php

namespace Undefined\SiteUndefinedintegration\Layers\Infrostructure\Http;

use Undefined\Main\Layers\Ddd\Infrostructure\Http\Response;

/**
 * Низкоуровневый класс запросов на сайт
 */
class Request
{
    /**
     * @var string|null Адрес сайта
     */
    private string $sUrl;
    /**
     * @var string|null токен авторизации
     */
    private string $sAuthToken;

    /**
     * Инициализация объекта в зависимости от окружения
     */
    public function __construct(?string $sAuthToken = null)
    {
        $this->sUrl = getenv("INTEGRATION_SITE_Undefined_URL");
        $this->sAuthToken = $sAuthToken ?: getenv('INTEGRATION_SITE_UndefinedTOKEN');
    }

    /**
     * POST запрос на сайт
     * @param string $sEndpoint
     * @param array $aPostFields
     * @return array|null
     */
    public function post(string $sEndpoint, array $aPostFields): ?Response
    {
        $sPostData = json_encode ($aPostFields, JSON_UNESCAPED_UNICODE);
        $obCurl = curl_init($this->getSiteUrl() . $sEndpoint);
        curl_setopt($obCurl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($obCurl, CURLOPT_POSTFIELDS, $sPostData);
        curl_setopt($obCurl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($obCurl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization-Token: ' . $this->sAuthToken,
                'Content-Length: ' . strlen($sPostData)
            ]
        );
        $oResult = new Response(curl_exec($obCurl));
        curl_close($obCurl);
        return $oResult;
    }

    /**
     * @return string
     */
    private function getSiteUrl(): string
    {
        return $this->sUrl;
    }
}