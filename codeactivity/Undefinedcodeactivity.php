<?php

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;

class CBPUndefinedCodeActivity extends CBPActivity
{
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "Title" => "",
            "toClass" => null,
            "toMethod"=> null
        );
    }

    public function Execute()
    {
        Loader::includeModule('Undefined.main');
        $sClassName = $this->getRawProperty("toClass");
        $sMethodName = $this->getRawProperty("toMethod");

        try {
            $this->WriteToTrackingService("Execute (new {$sClassName}(\$this))->{$sMethodName}()");

            (new $sClassName($this))->$sMethodName();

            $this->WriteToTrackingService("Executed (new {$sClassName}(\$this))->{$sMethodName}()");
        } catch (
            ErrorException | ValueError | TypeError | ParseError
            $sException
        ) {
            $this->WriteToTrackingService($sException->getMessage());
            return CBPActivityExecutionStatus::Faulting;
        }

        return CBPActivityExecutionStatus::Closed;
    }

    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = ""
    ) {
        $runtime = CBPRuntime::GetRuntime();
        if (!is_array($arCurrentValues))
        {
            $arCurrentValues = [];
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            $arCurrentValues["toClass"] = $arCurrentActivity["Properties"]["toClass"];
            $arCurrentValues["toMethod"] = $arCurrentActivity["Properties"]["toMethod"];

        }

        $runtime = CBPRuntime::GetRuntime();
        return $runtime->ExecuteResourceFile(
            __FILE__,
            "properties_dialog.php",
            array(
                "arCurrentValues" => $arCurrentValues,
                "formName" => $formName,
            )
        );
    }

    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    ) {
        $arProperties = array("MapFields" => array());
        if (is_array($arCurrentValues) && count($arCurrentValues)>0)
        {
            $arProperties["toClass"] = $arCurrentValues["toClass"];
            $arProperties["toMethod"] = $arCurrentValues["toMethod"];

        }
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;
        return true;
    }
    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = [];
        if ($arTestProperties["toClass"] && !class_exists($arTestProperties["toClass"])) {
            $arErrors[] = [
                "code" => "ClassNotExist",
                "parameter" => "toClass",
                "message" => Loc::getMessage('ERROR_CLASS_NOT_EXIST', ['#CLASS#' => $arTestProperties["toClass"]])
            ];
        }
        if (
            $arTestProperties["toMethod"]
            && $arTestProperties["toClass"]
            && !method_exists($arTestProperties["toClass"], $arTestProperties["toMethod"])
        ) {
            $arErrors[] = [
                "code" => "ClassNotExist",
                "parameter" => "toMethod",
                "message" => Loc::getMessage('ERROR_METHOD_NOT_EXIST', [
                    '#CLASS#' => $arTestProperties["toClass"],
                    '#METHOD#' => $arTestProperties["toMethod"],
                ])
            ];
        }
        if (!$arTestProperties["toClass"]) {
            $arErrors[] = [
                "code" => "ClassNotExist",
                "parameter" => "toClass",
                "message" => Loc::getMessage('ERROR_CLASS_EMPTY')
            ];
        }
        if (!$arTestProperties["toMethod"]) {
            $arErrors[] = [
                "code" => "ClassNotExist",
                "parameter" => "toMethod",
                "message" => Loc::getMessage('ERROR_METHOD_EMPTY')
            ];
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }
}
