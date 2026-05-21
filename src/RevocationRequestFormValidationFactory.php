<?php

declare(strict_types=1);

namespace Codebarista;

use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RevocationRequestFormValidationFactory implements DataValidationFactoryInterface
{
    public const CREATE_VALIDATION_NAME = 'codebarista_revocation_request_form.create';

    public function create(SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition(self::CREATE_VALIDATION_NAME);

        $definition->add('firstName', new Length(['max' => 255]));
        $definition->add('lastName', new Length(['max' => 255]));
        $definition->add('email', new NotBlank(), new Email(), new Length(['max' => 320]));
        $definition->add('contractNumber', new NotBlank(), new Length(['max' => 255]));
        $definition->add('comment', new Length(['max' => 4096]));

        return $definition;
    }

    public function update(SalesChannelContext $context): DataValidationDefinition
    {
        return $this->create($context);
    }
}
