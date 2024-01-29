<?php

namespace Undefined\SiteUndefinedintegration\Controller;

use Bitrix\Bizproc\Workflow\Template\Entity\WorkflowTemplateTable;
use Bitrix\Crm\DealTable;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Localization\Loc;
use Undefined\Main\Layers\Ddd\Service\Access\UserService;

class Booking extends Controller
{
    /**
     * Вывод сообщения с Подтверждением
     * @param int $iDealId
     * @return array
     */
    public static function getConfirmMessageAction(int $iDealId): array
    {
        $bIsRop = UserService::isRop();
        $aResult = [
            'isRop' => $bIsRop,
            'messages' => [
                'confirm' => Loc::getMessage('CONFIRM_YES'),
                'cancel' => Loc::getMessage('CONFIRM_CANCEL'),
            ],
        ];
        if ($bIsRop) {
            $aResult['messages']['description'] = Loc::getMessage('CONFIRM_ROP_DESCRIPTION');
        } else {
            $aResult['messages']['description'] = Loc::getMessage('CONFIRM_MANAGER_DESCRIPTION');
        }
        return $aResult;
    }

    /**
     * Запуск БП по отмене брони автомобиля
     * @param int $iDealId
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function startBookingCancelAction(int $iDealId): array
    {
        $aDeal = DealTable::query()
            ->setSelect(['ID', 'TYPE_ID'])
            ->where('ID', $iDealId)
            ->where('TYPE_ID', 'SALE')
            ->fetch();
        if (!$aDeal) {
            return [
                'success' => false,
                'message' => Loc::getMessage('WRONG_DEAL_TYPE'),
            ];
        }

        $obTemplateWorkflow = WorkflowTemplateTable::query()
            ->setSelect(['ID'])
            ->where('SYSTEM_CODE', 'site_integration_cancel_booking')
            ->where('MODULE_ID', 'crm')
            ->where('ENTITY', 'CCrmDocumentDeal')
            ->where('DOCUMENT_TYPE', 'DEAL')
            ->fetchObject();
        if (empty($obTemplateWorkflow)) {
            return [
                'success' => false,
                'message' => Loc::getMessage('BOOKING_WORKFLOW_NOT_EXIST'),
            ];
        }
        $iWorkflowId = \CBPDocument::startWorkflow(
            $obTemplateWorkflow->getId(),
            ['crm', 'CCrmDocumentDeal', 'DEAL_' . $iDealId],
            [
                'iCurrentuserId' => CurrentUser::get()->getId(),

            ],
            $aErrorsTmp
        );
        if (!empty($aErrorsTmp)) {
            return [
                'success' => false,
                'message' => var_export($aErrorsTmp, true)
            ];
        } else {
            return [
                'success' => true,
                'message' => Loc::getMessage('SUCCESS_WORKFLOW_START'),
            ];
        }
    }
}