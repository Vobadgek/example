<?php

namespace Undefined\SiteUndefinedintegration\Controller;

use Bitrix\Crm\DealTable;
use Bitrix\Crm\ItemIdentifier;
use Bitrix\Crm\Service\Container;
use Bitrix\Main\Engine\Controller;
use CCrmOwnerType;
use Undefined\Main\Layers\BusinessLogic\Services\Crm\Deal;
use Undefined\Main\Layers\Infrastructure\Utils\Uf\Enum;


class Relation extends Controller
{
    /**
     * Возвращает массив незакрытых сделок, привязанных к указанному контакту.
     * @param array $arFields - массив параметров из $_REQUEST['fields'], используется только CONTACT_ID
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function dealsAction(array $arFields): array
    {
        $obParent = new ItemIdentifier(CCrmOwnerType::Contact, $arFields['CONTACT_ID']);
        $arChildren = Container::getInstance()->getRelationManager()->getChildElements($obParent);
        $arChildrenIds = [];
        foreach ($arChildren as $obChild) {
            if ($obChild->getEntityTypeId() == CCrmOwnerType::Deal) {
                $arChildrenIds[] = $obChild->getEntityId();
            }
        }
        $sClassification = Deal::UF['APPEAL_CLASSIFICATION'];
        $obDeals = DealTable::query()
            ->setOrder(['ID' => 'DESC'])
            ->setFilter([
                'ID' => $arChildrenIds,
                'CLOSED' => 'N',
                $sClassification => Enum::getIdByXML('CRM_DEAL', $sClassification, 'BUY_SELL')])
            ->setSelect(['ID', 'DATE_CREATE', 'TITLE'])
            ->exec();
        $arDeals = [];
        if ($arDeal = $obDeals->fetch()) {
            $arDeals[] = $arDeal;
        }
        return $arDeals;
    }
}