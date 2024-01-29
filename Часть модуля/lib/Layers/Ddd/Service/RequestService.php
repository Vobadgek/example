<?php
namespace Undefined\SiteUndefinedintegration\Layers\Ddd\Service;


use Undefined\SiteUndefinedintegration\Layers\Infrostructure\Http\Request;
use Undefined\Main\Layers\Ddd\Infrostructure\Http\Response;

/**
 * Фабрика для работы с запросами на сайт
 */
class RequestService
{
    /**
     * Формат даты сайта
     */
    public const SITE_DATE_FORMAT = 'd.m.Y';
    /**
     * Ендпоинт обновление информации о дате выдачи
     */
    private const ENDPOINT_DATE_DELIVERY_PATH = 'rest/order/receiving/date/';
    /**
     * Ендпоинт изменения даты завершения брони
     */
    private const ENDPOINT_BOOKING_DATE = '';
    /**
     * Ендпоинт отправки решения РОПа о выдачи авто клиенту
     */
    private const ENDPOINT_ROP_ALLOW_CAR_DELIVERY_TO_CLIENT = 'e';
    /**
     * Ендпоинт отмены заказа
     */
    private const ENDPOINT_CANCEL_ORDER = '';
    /**
     * Отправка новой даты выдачи авто
     * @param string $sBitrixOrderGui
     * @param string $sNewExtraditionDate
     * @return array|null
     */
    public static function sendChangedExtraditionDate(string $sBitrixOrderGui, string $sNewExtraditionDate): ?Response
    {
        $obRequest = new Request();
        return $obRequest->post(self::ENDPOINT_DATE_DELIVERY_PATH, [
            'bitrixOrderId' => $sBitrixOrderGui,
            'newExtraditionDate' => $sNewExtraditionDate,
        ]);
    }

    /**
     * Отправка решения РОПа о выдаче авто клиенту
     * @param string $sBitrixOrderGui
     * @param bool $bRopApproveExtradition
     * @return Response|null
     */
    public static function sendROPAllowDeliveryCarToClient(string $sBitrixOrderGui, bool $bRopApproveExtradition): ?Response
    {
        $obRequest = new Request();
        return $obRequest->post(self::ENDPOINT_ROP_ALLOW_CAR_DELIVERY_TO_CLIENT ,
            [
                "bitrixOrderId" => $sBitrixOrderGui,
                "ropApproveExtradition" => $bRopApproveExtradition,
            ]
        );
    }
    /**
     * Отправка продления брони
     * @param string $sBitrixOrderGui
     * @param string $sNewBookingDate
     * @return Response|null
     */
    public static function sendBookingDateEnd(string $sBitrixOrderGui, string $sNewBookingDate): ?Response
    {
        $obRequest = new Request();
        return $obRequest->post( self::ENDPOINT_BOOKING_DATE,
            [
                "bitrixOrderId" => $sBitrixOrderGui,
                "newReservedDate" => $sNewBookingDate,
            ]
        );
    }

    /**
     * Отмена заказа
     * @param string $sBitrixOrderGui GUID заказа
     * @return array|Response|null
     */
    public static function sendBookingCancel(string $sBitrixOrderGui)
    {
        $obRequest = new Request();
        return $obRequest->post( self::ENDPOINT_CANCEL_ORDER,
            [
                "orderBitrixId" => $sBitrixOrderGui,
            ]
        );
    }
}