<?php

namespace Undefined\Main\Layers\Ddd\Entity\Value;

use Bitrix\Crm\Contact;
use Bitrix\Crm\ContactTable;
use Bitrix\Crm\FieldMultiTable;
use Bitrix\Main\ORM\Fields\Relations\Reference;

class Phone
{
    public const COUNTRY_CODE_VARIANTS = ['8', '+7', '7', ''];

    private const SEARCH_PATTERN = '/^((8|\+7|7)[\- ]?)?(\(?\d{3}\)?[\- ]?)?([\d\- ]{3})?([\d\- ]{2,3})?([\d\- ]{2,3})$/';
    private const CLEAR_PATTERN = '/[^0-9]/';

    private string $sOperatorCode = '';
    private string $sFirstNumberBlock = '';
    private string $sSecondNumberBlock = '';
    private string $sLastNumberBlock = '';

    private bool $bIsValid = true;
    private ?int $iClientId = null;
    private Contact $obClient;
    private ?bool $bHasClient = null;
    private string $sOriginPhone;
    /**
     * @param $sPhone
     */
    public function __construct($sPhone)
    {
        $aMatch = [];
        $bCurrentFormat = preg_match(
            self::SEARCH_PATTERN,
            $sPhone,
            $aMatch
        );
        if (!$bCurrentFormat) {
            $this->bIsValid = false;
            return;
        }
        $this->sOriginPhone = $sPhone;
        $this->setOperatorCode(preg_replace(self::CLEAR_PATTERN, '', $aMatch[3]));
        $this->setFirstNumberBlock(preg_replace(self::CLEAR_PATTERN, '', $aMatch[4]));
        $this->setSecondNumberBlock(preg_replace(self::CLEAR_PATTERN, '', $aMatch[5]));
        $this->setLastNumberBlock(preg_replace(self::CLEAR_PATTERN, '', $aMatch[6]));
    }
    public function generateFormattedNumberVariant(): ?string
    {
        if (!$this->isValid()) {
            return null;
        }
        return "8{$this->getOperatorCode()}{$this->getFirstNumberBlock()}{$this->getSecondNumberBlock()}{$this->getLastNumberBlock()}";
    }
    /**
     * Генерация вариантов номера телефона
     * @return array
     */
    public function generatePhoneVariants(): array
    {
        $aResult = [];

        $aCountryVariantNumber = self::COUNTRY_CODE_VARIANTS;
        $aOperatorCodeVariant = [$this->getOperatorCode(), " {$this->getOperatorCode()} ", "({$this->getOperatorCode()})", " ({$this->getOperatorCode()}) "];
        $aFiresNumberBlockVariant = [$this->getFirstNumberBlock()];
        $aEndNumberBlockVariant = [
            "{$this->getSecondNumberBlock()}{$this->getLastNumberBlock()}",
            " {$this->getSecondNumberBlock()} {$this->getLastNumberBlock()}",
            "-{$this->getSecondNumberBlock()}-{$this->getLastNumberBlock()}",
        ];

        $iCountCountryVariant = count($aCountryVariantNumber);
        $iCountOperatorCodeVariant = count($aOperatorCodeVariant);
        $iCountFiresNumberBlockVariant = count($aFiresNumberBlockVariant);
        $iCountEndNumberBlockVariant = count($aEndNumberBlockVariant);
        $iMaxVariants = $iCountCountryVariant * $iCountOperatorCodeVariant * $iCountFiresNumberBlockVariant * $iCountEndNumberBlockVariant;

        $iCounter = 0;
        while ($iCounter < $iMaxVariants) {
            $iDec = $iMaxVariants / $iCountCountryVariant;

            $iCountryCodeKey = floor($iCounter / $iDec);
            $sCountryCode = $aCountryVariantNumber[$iCountryCodeKey];

            $iDec /= $iCountOperatorCodeVariant;

            $iOperatorCodeVariantKey = floor($iCounter / $iDec) % $iCountOperatorCodeVariant;
            $sOperatorCodeVariant = $aOperatorCodeVariant[$iOperatorCodeVariantKey];

            $iDec /= $iCountFiresNumberBlockVariant;

            $iFiresNumberBlockVariantKey = floor($iCounter / $iDec) % $iCountFiresNumberBlockVariant;
            $sFiresNumberBlockVariant = $aFiresNumberBlockVariant[$iFiresNumberBlockVariantKey];

            $iDec /= $iCountEndNumberBlockVariant;

            $iEndNumberBlockVariantKey = floor($iCounter / $iDec) % $iCountEndNumberBlockVariant;
            $iEndNumberBlockVariant = $aEndNumberBlockVariant[$iEndNumberBlockVariantKey];
            $sPhoneVariant = "{$sCountryCode}{$sOperatorCodeVariant}{$sFiresNumberBlockVariant}{$iEndNumberBlockVariant}";
            $aResult[] = $sPhoneVariant;
            $iCounter++;
        }

        return $aResult;
    }


    /**
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->bIsValid;
    }

    /**
     * @return string
     */
    public function getOperatorCode(): string
    {
        return $this->sOperatorCode;
    }

    /**
     * @param string $sOperatorCode
     */
    public function setOperatorCode(string $sOperatorCode): void
    {
        $this->sOperatorCode = $sOperatorCode;
    }

    /**
     * @return string
     */
    public function getFirstNumberBlock(): string
    {
        return $this->sFirstNumberBlock;
    }

    /**
     * @param string $sFirstNumberBlock
     */
    public function setFirstNumberBlock(string $sFirstNumberBlock): void
    {
        $this->sFirstNumberBlock = $sFirstNumberBlock;
    }

    /**
     * @return string
     */
    public function getSecondNumberBlock(): string
    {
        return $this->sSecondNumberBlock;
    }

    /**
     * @param string $sSecondNumberBlock
     */
    public function setSecondNumberBlock(string $sSecondNumberBlock): void
    {
        $this->sSecondNumberBlock = $sSecondNumberBlock;
    }

    /**
     * @return string
     */
    public function getLastNumberBlock(): string
    {
        return $this->sLastNumberBlock;
    }

    /**
     * @param string $sLastNumberBlock
     */
    public function setLastNumberBlock(string $sLastNumberBlock): void
    {
        $this->sLastNumberBlock = $sLastNumberBlock;
    }

    /**
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function hasClient(): bool
    {
        if (is_null($this->bHasClient)) {
            return $this->searchClient();
        }
        return $this->bHasClient;
    }

    /**
     * @return int|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getClientId(): ?int
    {
        if (is_null($this->bHasClient)) {
            $this->searchClient();
            return $this->iClientId;
        }
        return $this->bHasClient ? null : $this->iClientId;
    }

    /**
     * @return Contact|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function getClient(): ?Contact
    {
        if (is_null($this->bHasClient)) {
            $this->searchClient();
            return $this->obClient;
        }
        return $this->bHasClient ? null : $this->obClient;
    }
    /**
     * @return int|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function searchClient(): ?bool
    {
        if ($this->iClientId > 0 || !is_null($this->bHasClient)) {
            return $this->iClientId > 0;
        }
        $aPhones = $this->generatePhoneVariants();
        $aContactBinding = FieldMultiTable::query()
            ->setSelect(['ELEMENT_ID', 'VALUE', 'CONTACT.FULL_NAME'])
            ->registerRuntimeField(
                new Reference(
                    'CONTACT',
                    ContactTable::class,
                    ['this.ELEMENT_ID' => 'ref.ID']
                )
            )
            ->whereIn('VALUE', $aPhones)
            ->where('ENTITY_ID', \CCrmOwnerType::ContactName)
            ->where('TYPE_ID', 'PHONE')
            ->fetchObject();
        if (!empty($aContactBinding)) {
            $this->iClientId = $aContactBinding->get('ELEMENT_ID');
            $this->obClientId = $aContactBinding->get('CONTACT');
            $this->bHasClient = true;
            return true;
        } else {
            $this->bHasClient = false;
            return false;
        }
    }

    /**
     * @return string
     */
    public function getOriginPhone(): string
    {
        return $this->sOriginPhone;
    }
}
