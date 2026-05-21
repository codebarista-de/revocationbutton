<?php

declare(strict_types=1);

namespace Codebarista\Migration;

use Codebarista\RevocationButton;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1747820000RevocationRequestMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1747820000;
    }

    public function update(Connection $connection): void
    {
        $merchantTypeId = $this->createMailTemplateType(
            $connection,
            RevocationButton::MAIL_TYPE_MERCHANT,
            'Revocation request (merchant)',
            'Widerruf (Händler)'
        );

        $customerTypeId = $this->createMailTemplateType(
            $connection,
            RevocationButton::MAIL_TYPE_CUSTOMER,
            'Revocation request (customer)',
            'Widerruf (Kunde)'
        );

        $this->createMailTemplate(
            $connection,
            $merchantTypeId,
            'New revocation request - contract no. {{ revocationRequestFormData.contractNumber }}',
            'Neuer Widerruf - Vertrag Nr. {{ revocationRequestFormData.contractNumber }}',
            $this->merchantHtmlEn(),
            $this->merchantPlainEn(),
            $this->merchantHtmlDe(),
            $this->merchantPlainDe()
        );

        $this->createMailTemplate(
            $connection,
            $customerTypeId,
            'We have received your revocation request',
            'Wir haben Ihren Widerruf erhalten',
            $this->customerHtmlEn(),
            $this->customerPlainEn(),
            $this->customerHtmlDe(),
            $this->customerPlainDe()
        );
    }

    public function updateDestructive(Connection $connection): void {}

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!is_string($languageId)) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }

    private function createMailTemplateType(Connection $connection, string $technicalName, string $nameEn, string $nameDe): string
    {
        $existing = $connection->fetchOne(
            'SELECT id FROM mail_template_type WHERE technical_name = :name',
            ['name' => $technicalName]
        );

        if ($existing !== false && is_string($existing)) {
            return Uuid::fromBytesToHex($existing);
        }

        $typeId = Uuid::randomHex();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template_type', [
            'id' => Uuid::fromHexToBytes($typeId),
            'technical_name' => $technicalName,
            'available_entities' => json_encode(['revocationRequestFormData' => null]),
            'created_at' => $now,
        ]);

        if (!empty($enLangId)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
                'language_id' => $enLangId,
                'name' => $nameEn,
                'created_at' => $now,
            ]);
        }

        if (!empty($deLangId)) {
            $connection->insert('mail_template_type_translation', [
                'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
                'language_id' => $deLangId,
                'name' => $nameDe,
                'created_at' => $now,
            ]);
        }

        return $typeId;
    }

    private function createMailTemplate(
        Connection $connection,
        string $typeId,
        string $subjectEn,
        string $subjectDe,
        string $htmlEn,
        string $plainEn,
        string $htmlDe,
        string $plainDe
    ): void {
        $existing = $connection->fetchOne(
            'SELECT id FROM mail_template WHERE mail_template_type_id = :id',
            ['id' => Uuid::fromHexToBytes($typeId)]
        );

        if ($existing !== false) {
            return;
        }

        $templateId = Uuid::randomHex();
        $now = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $enLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        $connection->insert('mail_template', [
            'id' => Uuid::fromHexToBytes($templateId),
            'mail_template_type_id' => Uuid::fromHexToBytes($typeId),
            'system_default' => 1,
            'created_at' => $now,
        ]);

        if (!empty($enLangId)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($templateId),
                'language_id' => $enLangId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => $subjectEn,
                'description' => '',
                'content_html' => $htmlEn,
                'content_plain' => $plainEn,
                'created_at' => $now,
            ]);
        }

        if (!empty($deLangId)) {
            $connection->insert('mail_template_translation', [
                'mail_template_id' => Uuid::fromHexToBytes($templateId),
                'language_id' => $deLangId,
                'sender_name' => '{{ salesChannel.name }}',
                'subject' => $subjectDe,
                'description' => '',
                'content_html' => $htmlDe,
                'content_plain' => $plainDe,
                'created_at' => $now,
            ]);
        }
    }

    private function merchantHtmlEn(): string
    {
        return <<<'MAIL'
<div style="font-family:arial; font-size:12px;">
    <p>A customer has submitted a revocation request via the shop.</p>
    <p>
        First name: {{ revocationRequestFormData.firstName }}<br>
        Last name: {{ revocationRequestFormData.lastName }}<br>
        Email: {{ revocationRequestFormData.email }}<br>
        Contract number: {{ revocationRequestFormData.contractNumber }}<br>
        Submitted at: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='en-GB') }}
    </p>
    {% if revocationRequestFormData.comment %}
    <p>Comment:</p>
    <p>{{ revocationRequestFormData.comment|nl2br }}</p>
    {% endif %}
</div>
MAIL;
    }

    private function merchantPlainEn(): string
    {
        return <<<'MAIL'
A customer has submitted a revocation via the shop.

First name: {{ revocationRequestFormData.firstName }}
Last name: {{ revocationRequestFormData.lastName }}
Email: {{ revocationRequestFormData.email }}
Contract number: {{ revocationRequestFormData.contractNumber }}
Submitted at: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='en-GB') }}
{% if revocationRequestFormData.comment %}

Comment:
{{ revocationRequestFormData.comment }}
{% endif %}
MAIL;
    }

    private function merchantHtmlDe(): string
    {
        return <<<'MAIL'
<div style="font-family:arial; font-size:12px;">
    <p>Ein Kunde hat über den Shop einen Widerruf eingereicht.</p>
    <p>
        Vorname: {{ revocationRequestFormData.firstName }}<br>
        Nachname: {{ revocationRequestFormData.lastName }}<br>
        E-Mail: {{ revocationRequestFormData.email }}<br>
        Vertragsnummer: {{ revocationRequestFormData.contractNumber }}<br>
        Gesendet am: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='de-DE') }}
    </p>
    {% if revocationRequestFormData.comment %}
    <p>Kommentar:</p>
    <p>{{ revocationRequestFormData.comment|nl2br }}</p>
    {% endif %}
</div>
MAIL;
    }

    private function merchantPlainDe(): string
    {
        return <<<'MAIL'
Ein Kunde hat über den Shop einen Widerruf eingereicht.

Vorname: {{ revocationRequestFormData.firstName }}
Nachname: {{ revocationRequestFormData.lastName }}
E-Mail: {{ revocationRequestFormData.email }}
Vertragsnummer: {{ revocationRequestFormData.contractNumber }}
Gesendet am: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='de-DE') }}
{% if revocationRequestFormData.comment %}

Kommentar:
{{ revocationRequestFormData.comment }}
{% endif %}
MAIL;
    }

    private function customerHtmlEn(): string
    {
        return <<<'MAIL'
<div style="font-family:arial; font-size:12px;">
    <p>{% set customerName = (revocationRequestFormData.firstName ~ ' ' ~ revocationRequestFormData.lastName)|trim %}Dear{% if customerName %} {{ customerName }}{% endif %},</p>
    <p>we have received your revocation and will process it as soon as possible.</p>
    <p>Your submitted data:</p>
    <p>
        Contract number: {{ revocationRequestFormData.contractNumber }}<br>
        Submitted at: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='en-GB') }}
    </p>
    {% if revocationRequestFormData.comment %}
    <p>Your comment:</p>
    <p>{{ revocationRequestFormData.comment|nl2br }}</p>
    {% endif %}
</div>
MAIL;
    }

    private function customerPlainEn(): string
    {
        return <<<'MAIL'
{% set customerName = (revocationRequestFormData.firstName ~ ' ' ~ revocationRequestFormData.lastName)|trim %}Dear{% if customerName %} {{ customerName }}{% endif %},

we have received your revocation and will process it as soon as possible.

Your submitted data:
Contract number: {{ revocationRequestFormData.contractNumber }}
Submitted at: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='en-GB') }}
{% if revocationRequestFormData.comment %}

Your comment:
{{ revocationRequestFormData.comment }}
{% endif %}
MAIL;
    }

    private function customerHtmlDe(): string
    {
        return <<<'MAIL'
<div style="font-family:arial; font-size:12px;">
    <p>{% set customerName = (revocationRequestFormData.firstName ~ ' ' ~ revocationRequestFormData.lastName)|trim %}Hallo{% if customerName %} {{ customerName }}{% endif %},</p>
    <p>wir haben Ihren Widerruf erhalten und werden diesen schnellstmöglich bearbeiten.</p>
    <p>Ihre Angaben:</p>
    <p>
        Vertragsnummer: {{ revocationRequestFormData.contractNumber }}<br>
        Gesendet am: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='de-DE') }}
    </p>
    {% if revocationRequestFormData.comment %}
    <p>Ihr Kommentar:</p>
    <p>{{ revocationRequestFormData.comment|nl2br }}</p>
    {% endif %}
</div>
MAIL;
    }

    private function customerPlainDe(): string
    {
        return <<<'MAIL'
{% set customerName = (revocationRequestFormData.firstName ~ ' ' ~ revocationRequestFormData.lastName)|trim %}Hallo{% if customerName %} {{ customerName }}{% endif %},

wir haben Ihren Widerruf erhalten und werden diesen schnellstmöglich bearbeiten.

Ihre Angaben:
Vertragsnummer: {{ revocationRequestFormData.contractNumber }}
Gesendet am: {{ revocationRequestFormData.submitTime|format_datetime('medium', 'short', locale='de-DE') }}
{% if revocationRequestFormData.comment %}

Ihr Kommentar:
{{ revocationRequestFormData.comment }}
{% endif %}
MAIL;
    }
}
