<?php

declare(strict_types=1);

namespace Codebarista;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class RevocationButton extends Plugin
{
    public const CONFIG_MAIL_RECIPIENT_ADDRESS = 'RevocationButton.config.mailRecipientAddress';
    public const CONFIG_MAIL_RECIPIENT_NAME = 'RevocationButton.config.mailRecipientName';
    public const CONFIG_MAIL_SENDER_ADDRESS = 'RevocationButton.config.mailSenderAddress';
    public const CONFIG_MAIL_SENDER_NAME = 'RevocationButton.config.mailSenderName';

    public const MAIL_TYPE_MERCHANT = 'codebarista_revocation_request.merchant';
    public const MAIL_TYPE_CUSTOMER = 'codebarista_revocation_request.customer';

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData() || $this->container == null) {
            return;
        }

        /** @var Connection */
        $connection = $this->container->get(Connection::class);

        $connection->executeStatement(
            'DELETE FROM mail_template_type WHERE technical_name IN (:names)',
            ['names' => [self::MAIL_TYPE_MERCHANT, self::MAIL_TYPE_CUSTOMER]],
            ['names' => Connection::PARAM_STR_ARRAY]
        );
    }
}
