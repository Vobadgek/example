<?php

namespace Undefined\Main\Layers\Ddd\Service\Notification;

use Bitrix\Bizproc\Workflow\Template\Entity\WorkflowTemplateTable;
use Bitrix\Crm\ContactTable;
use Bitrix\Crm\FieldMultiTable;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Undefined\Main\Layers\Ddd\Entity\Value\Phone;
use Undefined\Main\Layers\Ddd\Service\Mail\MailSender;
use Undefined\Main\Layers\Ddd\Service\Sms\SmsSender;
use Undefined\Main\Layers\Orm\ClientInteraction\ClientInteractionTable;

class ContactNotificationService
{
    public const CONTACT_CHANNEL_FIELD_CODE = 'UF_CRM_63A0176E92898';

    private int $iContactId;
    private int $iEmployeeId;
    private bool $bHasContact = false;
    private bool $bHasChannel = true;
    private string $sChannelFunction;
    private string $sEmployeeEmail;
    private ?Phone $obFirstValidPhone = null;
    private ?MailSender $obFirstMail = null;
    private bool $bIsWriteInteraction = true;

    public function __construct(int $iContactId, ?int $iEmployeeId = null)
    {
        $this->iEmployeeId = $iEmployeeId ?: CurrentUser::get()->getId();
        $this->iContactId = $iContactId;
        $aContact = ContactTable::query()
            ->setSelect([
                'ID',
                self::CONTACT_CHANNEL_FIELD_CODE,
                'CHANNEL_NAME'
            ])
            ->registerRuntimeField(
                'CHANNEL_NAME',
                [
                    'data_type' => 'STRING',
                    'expression' =>
                        [
                            '(SELECT VALUE FROM b_user_field_enum WHERE ID = %s)', self::CONTACT_CHANNEL_FIELD_CODE
                        ],
                    'join_type' => 'LEFT'
                ]
            )
            ->where('ID', $this->getContactId())
            ->fetch();

        if (!$aContact) {
            return;
        }
        $this->bHasContact = true;

        $obContactPhoneCollection = FieldMultiTable::query()->setSelect(['ELEMENT_ID', 'VALUE'])
            ->whereIn('ELEMENT_ID', $this->getContactId())
            ->where('ENTITY_ID', \CCrmOwnerType::ContactName)
            ->where('TYPE_ID', 'PHONE')
            ->fetchCollection();
        foreach ($obContactPhoneCollection as $obPhone) {
            $obPhone = new Phone($obPhone->getValue());
            if ($obPhone->isValid()) {
                $this->obFirstValidPhone = $obPhone;
                break;
            }
        }
        $obContactMail = FieldMultiTable::query()->setSelect(['ELEMENT_ID', 'VALUE'])
            ->whereIn('ELEMENT_ID', $this->getContactId())
            ->where('ENTITY_ID', \CCrmOwnerType::ContactName)
            ->where('TYPE_ID', 'EMAIL')
            ->fetchObject();
        if ($obContactMail) {
            $this->obFirstMail = new MailSender($obContactMail->getValue());
        }

        $obEmployee = UserTable::query()
            ->setSelect(['ID', 'EMAIL'])
            ->where('ID', $this->getEmployeeId())
            ->fetchObject();
        if ($obEmployee) {
            $this->sEmployeeEmail = $obEmployee->getEmail();
        }
        if (!$aContact['CHANNEL_NAME']) {
            $this->bHasChannel = false;
        }

        switch ($aContact['CHANNEL_NAME']) {
            case 'Whatsapp':
                $this->sChannelFunction = 'sendByWatsApp';
                break;
            case 'Телеграм':
                $this->sChannelFunction = 'sendByTelegram';
                break;
            case 'Viber':
                $this->sChannelFunction = 'sendByViber';
                break;
            case 'Почта':
                $this->sChannelFunction = 'sendByEmail';
                break;
            case 'sms':
                $this->sChannelFunction = 'sendBySms';
                break;
            case 'Телефон':
            default:
                $this->sChannelFunction = 'sendByPhone';
                break;
        }
    }

    /**
     * @return string
     */
    public function getEmployeeEmail(): string
    {
        return $this->sEmployeeEmail;
    }

    /**
     * @return MailSender|null
     */
    public function getFirstMail(): ?MailSender
    {
        return $this->obFirstMail;
    }

    /**
     * @return bool
     */
    private function isWriteInteraction(): bool
    {
        return $this->bIsWriteInteraction;
    }

    /**
     * @return void
     */
    public function disableWriteInteraction(): void
    {
        $this->bIsWriteInteraction = false;
    }

    /**
     * @return void
     */
    public function enableWriteInteraction(): void
    {
        $this->bIsWriteInteraction = true;
    }

    public function send(string $sText, string $sTheme = '')
    {
        if (!$this->isHasContact()) {
            return;
        }
        $sMethodName = $this->getChannelFunction();
        $this->$sMethodName($sText, $sText);
    }

    /**
     * @param string $sText
     * @param string $sTheme
     * @return void
     * @throws \CTaskAssertException
     * @throws \TasksException
     */
    public function sendByPhone(string $sText, string $sTheme)
    {
        Loader::includeModule('tasks');
        $newTaskItem = \CTaskItem::add([
            'TITLE' => 'Позвонить клиенту по теме: ' . $sTheme,
            'CREATED_BY' => $this->getEmployeeId(),
            'DESCRIPTION' => $sText,
            'RESPONSIBLE_ID' => $this->getEmployeeId(),
            'UF_CRM_TASK' => ['C_' . $this->getContactId()],
        ], 1);

    }

    public function sendByWatsApp(string $sText, string $sTheme)
    {
        $this->sendByTelegram($sText, $sTheme);
    }

    /**
     * @param string $sText
     * @param string $sTheme
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function sendByTelegram(string $sText, string $sTheme)
    {
        $aWorkflowTemplate = WorkflowTemplateTable::query()
            ->setSelect(['ID'])
            ->where('send_client_notification_in_chat', 'send_client_notification_in_chat')
            ->fetch();
        $iWorkflowId = \CBPDocument::startWorkflow(
            $aWorkflowTemplate['ID'],
            ['crm', 'CCrmDocumentContact', 'CONTACT_' . $this->getContactId()],
            [
                'TITLE' => $sText,
                'TEXT' => $sTheme,
                'SENDER' => 'user_' . $this->getEmployeeId(),

            ],
            $aErrorsTmp
        );
        if ($this->isWriteInteraction()) {
            $aFields = [
                ClientInteractionTable::FIELD_TYPE => 'telegramm',
                ClientInteractionTable::FIELD_TITLE => $sTheme,
                ClientInteractionTable::FIELD_DESCRIPTION => $sText,
                ClientInteractionTable::FIELD_START_TIME => new DateTime(),
                ClientInteractionTable::FIELD_EMPLOYEE_ID => $this->getEmployeeId(),
                ClientInteractionTable::FIELD_CLIENT_ID => $this->getContactId(),
            ];
            ClientInteractionTable::add($aFields);
        }
    }
    public function sendByViber(string $sText, string $sTheme)
    {
        $this->sendByTelegram($sText, $sTheme);
    }

    /**
     * @param string $sText
     * @param string $sTheme
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function sendByEmail(string $sText, string $sTheme)
    {
        $this->getFirstMail()->send($this->getEmployeeEmail(), $sText, $sTheme);
        if ($this->isWriteInteraction()) {
            $this->getFirstMail()->writeInteraction($this->getEmployeeId(), $this->getContactId());
        }
    }

    /**
     * @param string $sText
     * @param string $sTheme
     * @return void
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function sendBySms(string $sText, string $sTheme)
    {

        $obSmsSender = new SmsSender($this->getFirstValidPhone()->getOriginPhone(), $sText);
        $obSmsSender->send(false);
        if ($this->isWriteInteraction()) {
            $obSmsSender->writeInteraction($this->getEmployeeId());
        }
    }

    /**
     * @return int
     */
    public function getEmployeeId(): int
    {
        return $this->iEmployeeId;
    }

    /**
     * @return Phone
     */
    public function getFirstValidPhone(): Phone
    {
        return $this->obFirstValidPhone;
    }
    /**
     * @return int
     */
    public function getContactId(): int
    {
        return $this->iContactId;
    }

    /**
     * @return bool
     */
    public function isHasChannel(): bool
    {
        return $this->bHasChannel;
    }

    /**
     * @return bool
     */
    public function isHasContact(): bool
    {
        return $this->bHasContact;
    }

    /**
     * @return string
     */
    private function getChannelFunction(): string
    {
        return $this->sChannelFunction;
    }
}