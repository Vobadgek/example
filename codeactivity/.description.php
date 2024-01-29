<?php

use Bitrix\Main\Localization\Loc;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arActivityDescription = [
    "NAME" => Loc::getMessage('NAME'),
    "DESCRIPTION" => Loc::getMessage('DESCRIPTION'),
    "TYPE" => "activity",
    "CLASS" => "UndefinedCodeActivity",
    "JSCLASS" => "BizProcActivity",
    "CATEGORY" => [
        "ID" => "other",
    ],
];
