<?php

namespace Undefined\SiteUndefinedintegration\Layers\Ddd\Service;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Undefined\ARMUM\Layers\Ddd\Service\RequestService as ARMUMRequestService;
use Undefined\Main\Layers\BusinessLogic\Services\Crm\Deal;


/**
 * Фабрика для работы с интеграциями в событиях
 */
class EventHandlerService
{
    /**
     * Формат даты сайта
     */
    public const SITE_DATE_FORMAT = 'd.m.Y';

    /**
     * Обработка изменения даты выдачи в сделке
     * @param array $aFields Поля сделки перед изменением
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function sendIfDateDeliveryChanged(array &$aFields): void
    {
        Loader::includeModule('Undefined.armum');
        $aDealTableString = DealTable::query()
            ->setSelect([
                'ID',
                Deal::UF['ISSUE_CAR_DATE'],
                Deal::UF['SITE_ORDER_ID'],
                Deal::UF['SITE_ORDER_GUID'],
                Deal::UF['UNIVERSAL_GUID'],
            ])
            ->where('ID', $aFields['ID'])
            ->fetch();
        if (
            !$aDealTableString
            || (!$aDealTableString[Deal::UF['UNIVERSAL_GUID']] && (!$aDealTableString[Deal::UF['SITE_ORDER_ID']] && !$aDealTableString[Deal::UF['SITE_ORDER_GUID']]))
            || !$aFields[Deal::UF['ISSUE_CAR_DATE']]
        ) {
            return;
        }
        $sOldDate = '';
        if ($aDealTableString[Deal::UF['ISSUE_CAR_DATE']]) {
            $sOldDate = $aDealTableString[Deal::UF['ISSUE_CAR_DATE']]->format(self::SITE_DATE_FORMAT);
        }
        $sNewDate = (new DateTime($aFields[Deal::UF['ISSUE_CAR_DATE']]))->format(self::SITE_DATE_FORMAT);

        if ($sOldDate == '' || $sNewDate == '') {
            return;
        }
        $obNewDate = new DateTime($aFields[Deal::UF['ISSUE_CAR_DATE']]);
        $obNewDate->add('-3 hours');
        $sSiteOrderId = $aDealTableString[Deal::UF['SITE_ORDER_ID']]?: $aDealTableString[Deal::UF['SITE_ORDER_GUID']];
        try {
            if ($sSiteOrderId) {
                RequestService::sendChangedExtraditionDate($sSiteOrderId, $sNewDate);
            }
            if ($aDealTableString[Deal::UF['UNIVERSAL_GUID']]) {
                ARMUMRequestService::sendCarIssueDateEnd(
                    $aDealTableString[Deal::UF['UNIVERSAL_GUID']],
                    $obNewDate->format('Y-m-d\TH:i:s.u\Z')
                );
            }

        } catch (\ErrorException | \TypeError $exception) {
        }
    }
}
