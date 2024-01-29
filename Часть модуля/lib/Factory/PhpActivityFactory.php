<?php

namespace Undefined\SiteUndefinedintegration\Factory;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Application;
use Bitrix\Main\FileTable;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserTable;
use Undefined\Main\Layers\BusinessLogic\Services\Crm\Deal;
use Undefined\SiteUndefinedintegration\Layers\Ddd\Service\RequestService;
use Undefined\Main\Layers\Ddd\Service\Access\UserService;

/**
 * Фабрика для работы с интеграциями с сайтом в PHP активити в Бизнес процессах
 */
class PhpActivityFactory
{
    /**
     * Свойство в котором хранится Решение РОПа по выдаче авто клиенту
     */
    public const ROP_ALLOWED_DELIVERY = 'UF_CRM_ROP_ALLOWED_DELIVERY';


    /**
     * Проверка является ли пользователь РОП
     * @param int $iUserId
     * @return bool
     */
    public static function checkUserRop(int $iUserId): bool
    {
        return UserService::isRop($iUserId);
    }

    public static function sendCancelBookingCarForDeal(int $iDealId, string $sUserId): array
    {
        $iUserId = str_replace('user_', '', $sUserId);
        if (!UserService::isRop($iUserId)) {
            return [
                'success' => false,
                'message' => Loc::getMessage('ERROR_BOOKING_USER_IS_NOT_ROP'),
            ];
        }
        $aDealTableString = DealTable::query()
            ->setSelect([
                'ID',
                Deal::UF['SITE_ORDER_GUID'],
            ])
            ->where('ID', $iDealId)
            ->fetch();
        if (empty($aDealTableString) || !$sSiteOrderGuid = $aDealTableString[Deal::UF['SITE_ORDER_GUID']]) {
            return [
                'success' => false,
                'message' => Loc::getMessage('ERROR_BOOKING_DEAL_EMPTY'),
            ];
        }
        $obResponse = RequestService::sendBookingCancel($sSiteOrderGuid);
        if (!$obResponse->isSuccessRequest()) {
            return [
                'success' => false,
                'message' => Loc::getMessage('ERROR_DEAL_RELATION_GUID_NOT_EXIST', [
                    '#ID#' => $iDealId,
                ]),
            ];
        }
        if ($obResponse->hasError()) {
            return [
                'success' => false,
                'message' => Loc::getMessage('SITE_REQUEST_ERROR', ['#ERROR_STRING#' => $obResponse->getErrorsString()]),
            ];
        }
        return [
            'success' => true,
            'message' => Loc::getMessage('SUCCESS_CANCEL_BOOKING'),
        ];
    }

    /**
     * Отправка информации на сайт по решению РОПа о выдаче авто клиенту
     * @param int $iDealId
     * @return false[]
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function sendChangedExtraditionDate(int $iDealId): array
    {

        $aDealTableString = DealTable::query()
            ->setSelect([
                'ID',
                Deal::UF['SITE_ORDER_GUID'],
                Deal::UF['SITE_ORDER_ID'],
                self::ROP_ALLOWED_DELIVERY,
            ])
            ->where('ID', $iDealId)
            ->fetch();

        $aResult = self::checkDealForRopAllow($aDealTableString, $iDealId);
        if ($aResult['ERROR'] === true) {
            return $aResult;
        }

        $sSiteOrderId = $aDealTableString[Deal::UF['SITE_ORDER_ID']] ?: $aDealTableString[Deal::UF['SITE_ORDER_GUID']];

        $obResponse = RequestService::sendROPAllowDeliveryCarToClient(
            $sSiteOrderId,
            $aDealTableString[self::ROP_ALLOWED_DELIVERY]
        );

        if (!$obResponse->isSuccessRequest()) {
            $aResult['ERROR'] = true;
            $aResult['MESS'] = Loc::getMessage('ERROR_DEAL_RELATION_GUID_NOT_EXIST', [
                '#ID#' => $iDealId,
            ]);
            return $aResult;
        }
        if ($obResponse->hasError()) {
            $aResult['ERROR'] = true;
            $aResult['MESS'] = $obResponse->getErrorsString();
            return $aResult;
        }
        $aResult['MESS'] = Loc::getMessage('SUCCESS_REQUEST_TO_SITE', [
            '#ID#' => $iDealId,
        ]);

        return $aResult;
    }

    /**
     * Проверка сделки на наличие данных для запроса
     * @param array|null $aDealTableString Строка таблицы сделок
     * @param string $iDealId ID сделки
     * @return false[]
     */
    private static function checkDealForRopAllow(?array $aDealTableString, string $iDealId): array
    {
        $aResult = [
            'ERROR' => false
        ];
        if (!$aDealTableString) {
            $aResult['ERROR'] = true;
            $aResult['MESS'] = Loc::getMessage('ERROR_DEAL_NOT_EXIST', [
                '#ID#' => $iDealId,
            ]);
            return $aResult;
        }
        if (!$aDealTableString[self::ROP_ALLOWED_DELIVERY]) {
            $aResult['ERROR'] = true;
            $aResult['MESS'] = Loc::getMessage('ERROR_DEAL_ROP_ALLOW_NOT_EXIST', [
                '#ID#' => $iDealId,
            ]);
            return $aResult;
        }
        if (!$aDealTableString[Deal::UF['SITE_ORDER_GUID']] && !$aDealTableString[Deal::UF['SITE_ORDER_ID']]) {
            $aResult['ERROR'] = true;
            $aResult['MESS'] = Loc::getMessage('ERROR_DEAL_RELATION_NOT_EXIST', [
                '#ID#' => $iDealId,
            ]);
            return $aResult;
        }
        return $aResult;
    }

    /**
     * Импорт или обновление логина и пароля пользователей из csv файла
     * @param $iFileId
     * @return array|string[]
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function importUsersFromCsvFile($iFileId): array
    {
        $obFileTableElement = FileTable::query()
            ->setSelect([
                'ID',
                'SUBDIR',
                'FILE_NAME',
            ])
            ->where('ID', $iFileId)
            ->fetchObject();
        if (!$obFileTableElement) {
            return [
                'IMPORT_LOGS' => Loc::getMessage('ERROR_FILE_NOT_EXIST', ['#ID#' => $iFileId]),
            ];
        }
        $obContext = Application::getInstance()->getContext();
        $sFilePath = $obContext->getServer()->getDocumentRoot()
            . '/upload/'
            . $obFileTableElement->getSubdir()
            . '/'
            . $obFileTableElement->getFileName();

        $rStreamFile = @fopen($sFilePath, "r");
        if ($rStreamFile === false) {
            return [
                'IMPORT_LOGS' => Loc::getMessage('ERROR_FILE_READ', ['#PATH#' => $sFilePath]),
            ];
        }
        $iIterator = 0;
        $sLogs = '';
        while (($data = fgetcsv($rStreamFile, 1000, ";")) !== false) {
            $iIterator++;
            if ($iIterator === 1) {
                continue;
            }
            $sPass = $data[0];
            $sEmail = $data[1];
            $arFullName = explode(' ', $data[2]);
            $sLastName = $arFullName[0];
            $sName = $arFullName[1];
            $sSecondName = $arFullName[2];
            $sPost = $data[3];
            $iDepartment = $data[9];
            $iRole = $data[10];
            $iLocation = $data[11];
            $sLogin = substr($sEmail, 0, stripos($sEmail, '@'));
            $obUserTableElement = UserTable::query()
                ->setSelect([
                    'ID',
                    'LOGIN'
                ])
                ->where('EMAIL', $sEmail)
                ->fetchObject();
            $obUser = new \CUser();
            if ($obUserTableElement) {
                $obUpdateResult = $obUser->Update($obUserTableElement->getId(), [
                    "EMAIL" => $sEmail,
                    "LOGIN" => $sLogin,
                    "PASSWORD" => $sPass,
                    "CONFIRM_PASSWORD" => $sPass,
                ]);
                if ($obUpdateResult) {
                    $sLogs .= Loc::getMessage('SUCCESS_UPDATE_USER', [
                        '#ID#' => $obUserTableElement->getId(),
                        '#LOGIN#' => $sLogin,
                        '#EMAIL#' => $sEmail,
                    ]);
                } else {
                    $sLogs .= Loc::getMessage('ERROR_UPDATE_USER', [
                        '#ID#' => $obUserTableElement->getId(),
                        '#LOGIN#' => $sLogin,
                        '#EMAIL#' => $sEmail,
                    ]);
                }
            } else {
                $iId = $obUser->Add([
                    "NAME" => $sName,
                    "LAST_NAME" => $sLastName,
                    "SECOND_NAME" => $sSecondName,
                    "EMAIL" => $sEmail,
                    "LOGIN" => $sLogin,
                    "LID" => "ru",
                    "ACTIVE" => "Y",
                    "GROUP_ID" => [3, 4, 12, $iRole],
                    "PASSWORD" => $sPass,
                    "CONFIRM_PASSWORD" => $sPass,
                    "WORK_POSITION" => $sPost,
                    "UF_DEPARTMENT" => [$iDepartment]
                ]);
                if (intval($iId) > 0){
                    $sLogs .= Loc::getMessage('SUCCESS_ADD_USER', [
                        '#ID#' => $iId,
                        '#LOGIN#' => $sLogin,
                        '#EMAIL#' => $sEmail,
                    ]);
                    \CSocNetUserToGroup::Add(
                        array(
                            "USER_ID" => $iId,
                            "GROUP_ID" => $iLocation,
                            "ROLE" => SONET_ROLES_USER,
                            "=DATE_CREATE" => $GLOBALS["DB"]->CurrentTimeFunction(),
                            "=DATE_UPDATE" => $GLOBALS["DB"]->CurrentTimeFunction(),
                            "INITIATED_BY_TYPE" => SONET_INITIATED_BY_USER,
                            "INITIATED_BY_USER_ID" => \CUser::GetID(),
                            "MESSAGE" => false,
                        )
                    );
                }
                else {
                    $sLogs .= Loc::getMessage('ERROR_WHEN_ADD_USER', ['#ERROR_STR#' => $obUser->LAST_ERROR . $sEmail]);
                }
            }
        }
        fclose($rStreamFile);
        $sLogs = Loc::getMessage('IMPORT_FILE_LOG_TITLE', [
            '#STRING_COUNT#' => $iIterator
            ]) . $sLogs;

        return [
            'IMPORT_LOGS' => $sLogs,
        ];
    }
}

