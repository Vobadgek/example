<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Undefined\Main\Layers\Ddd\Service\Bizproc\UndefinedCodeActivityService;

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

Loader::includeModule('Undefined.main');

$aDeclaredClasses = UndefinedCodeActivityService::getDeclaredClasses();

$aClassesOptions = [];
$aMethodsOptions = [];

/** @var \Undefined\Main\Layers\Ddd\Service\Bizproc\Intarfeces\UndefinedCodeActivityInterface $sClass */
foreach ($aDeclaredClasses as $sClass) {
    //$sShortClassName = (new \ReflectionClass($sClass))->getShortName();
    $aClassesOptions[] = [
        'NAME' => $sClass::getClassDescription(),
        'VALUE' => $sClass,
    ];
    try {
        $aMethods = $sClass::declareUndefinedCodeActivityMethods();
        foreach ($aMethods as $sMethodName) {
            $aMethodsOptions[] = [
                'NAME' => $sClass::getMethodsDescriptions($sMethodName),
                'VALUE' => $sMethodName,
                'CLASS' => $sClass::getClassDescription(),
            ];
        }
    } catch (ErrorException $obException) {
    }
}

$sCurrentClassName = $arCurrentValues['toClass'] && class_exists($arCurrentValues['toClass'])
    ? (new \ReflectionClass($arCurrentValues['toClass']))->getShortName(): $aClassesOptions[0]['NAME'];

?>
<tr>
    <td align="right" width="40%">
        <span>Класс</span>
    </td>
    <td width="60%">
        <select id="toClassSwitcher" name="toClass">
            <?php foreach ($aClassesOptions as $aClassOption) { ?>
                <?php $selected = ($arCurrentValues['toClass'] == $aClassOption['VALUE']) ? 'selected' : ''; ?>
                <option data-class="<?=$aClassOption['NAME']?>" value="<?= $aClassOption['VALUE']; ?>" <?= $selected; ?>>
                    <?= $aClassOption['NAME']; ?>
                </option>
            <?php } ?>
        </select>
    </td>
</tr>
<tr>
    <td align="right" width="40%">
        <span>Метод</span>
    </td>
    <td width="60%">
        <select id="toMethodSwitcher" name="toMethod">
            <option data-class="none" value="">
                <?= Loc::getMessage('SELECT_METHOD')?>
            </option>
            <?php foreach ($aMethodsOptions as $key => $aMethodOption) { ?>
                <?php $selected = ($arCurrentValues['toMethod'] == $aMethodOption['VALUE']) ? 'selected' : ''; ?>
                <option data-class="<?=$aMethodOption['CLASS']?>" value="<?= $aMethodOption['VALUE']; ?>" <?= $selected; ?>>
                    <?= $aMethodOption['NAME']; ?>
                </option>
            <?php } ?>
        </select>
    </td>
</tr>
<script>
    {
        const changeAllowMethods = (sClassName) => {
            let obOptions = document.querySelector('#toMethodSwitcher').querySelectorAll('option');
            for (let i in obOptions) {
                let obOption = obOptions[i];
                if (
                    !obOption.dataset
                    || !obOption.dataset.class
                ) {
                    continue;
                }
                if (obOption.dataset.class === sClassName || obOption.dataset.class === 'none') {
                    obOption.style.display = 'block';
                } else {
                    obOption.style.display = 'none';
                }
            }
        }
        let sClassName = '<?=$sCurrentClassName;?>';
        changeAllowMethods(sClassName);

        document.querySelector('#toClassSwitcher').addEventListener("change", function (obEvent) {
            let sClassName = this.options[this.selectedIndex].dataset.class;
            changeAllowMethods(sClassName);
            document.querySelector('#toMethodSwitcher').selectedIndex = 0;
        });
    }
</script>
