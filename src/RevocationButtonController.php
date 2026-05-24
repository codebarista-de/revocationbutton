<?php

declare(strict_types=1);

namespace Codebarista;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class RevocationButtonController extends StorefrontController
{
    private LoggerInterface $logger;
    private SystemConfigService $systemConfig;
    private AbstractMailService $mailService;
    private DataValidator $dataValidator;
    private RevocationRequestFormValidationFactory $validationFactory;
    private RateLimiter $rateLimiter;
    private GenericPageLoaderInterface $genericPageLoader;
    private EntityRepository $mailTemplateRepository;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfig,
        AbstractMailService $mailService,
        DataValidator $dataValidator,
        RevocationRequestFormValidationFactory $validationFactory,
        RateLimiter $rateLimiter,
        GenericPageLoaderInterface $genericPageLoader,
        EntityRepository $mailTemplateRepository
    ) {
        $this->logger = $logger;
        $this->systemConfig = $systemConfig;
        $this->mailService = $mailService;
        $this->dataValidator = $dataValidator;
        $this->validationFactory = $validationFactory;
        $this->rateLimiter = $rateLimiter;
        $this->genericPageLoader = $genericPageLoader;
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    /**
     * @Route("/revocation", name="frontend.codebarista.revocation.page", methods={"GET"})
     */
    public function page(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->genericPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@RevocationButton/storefront/page/codebarista/revocation/index.html.twig',
            ['page' => $page]
        );
    }

    /**
     * @Route(
     *     "/revocation/request",
     *     name="frontend.codebarista.revocation.request",
     *     defaults={"XmlHttpRequest"=true, "_captcha"=true},
     *     methods={"POST"}
     * )
     */
    public function request(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        try {
            $this->rateLimiter->ensureAccepted(RateLimiter::CONTACT_FORM, (string) $request->getClientIp());
        } catch (RateLimitExceededException $exception) {
            return $this->jsonAlert('danger', $this->trans('codebaristaRevocationRequest.rateLimited'));
        }

        try {
            $this->dataValidator->validate(
                $data->all(),
                $this->validationFactory->create($context)
            );
        } catch (ConstraintViolationException $violations) {
            $messages = [];
            foreach ($violations->getViolations() as $violation) {
                $messages[] = sprintf('%s: %s', trim($violation->getPropertyPath(), '/'), $violation->getMessage());
            }

            return $this->jsonAlert('danger', $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                'type' => 'danger',
                'list' => $messages,
                'content' => $this->trans('codebaristaRevocationRequest.validationError'),
            ]));
        }

        $recipientAddress = $this->systemConfig->getString(RevocationButton::CONFIG_MAIL_RECIPIENT_ADDRESS, $context->getSalesChannelId())
            ?: $this->systemConfig->getString('core.basicInformation.email', $context->getSalesChannelId());

        if ($recipientAddress === '') {
            $this->logger->error('Revocation request received but no recipient address configured.');
            return $this->jsonAlert('danger', $this->trans('codebaristaRevocationRequest.errorMessage'));
        }

        $recipientName = $this->systemConfig->getString(RevocationButton::CONFIG_MAIL_RECIPIENT_NAME, $context->getSalesChannelId()) ?: $recipientAddress;
        $senderAddress = $this->systemConfig->getString(RevocationButton::CONFIG_MAIL_SENDER_ADDRESS, $context->getSalesChannelId()) ?: $recipientAddress;
        $senderName = $this->systemConfig->getString(RevocationButton::CONFIG_MAIL_SENDER_NAME, $context->getSalesChannelId()) ?: $context->getSalesChannel()->getName();

        if ($senderName == null) {
            $this->logger->error('Revocation request received but no sender name configured.');
            return $this->jsonAlert('danger', $this->trans('codebaristaRevocationRequest.errorMessage'));
        }

        $formData = [
            'firstName' => self::asTrimmedString($data->get('firstName')),
            'lastName' => self::asTrimmedString($data->get('lastName')),
            'email' => self::asTrimmedString($data->get('email')),
            'contractNumber' => self::asTrimmedString($data->get('contractNumber')),
            'comment' => self::asTrimmedString($data->get('comment')),
            'submitTime' => new \DateTimeImmutable(),
        ];

        try {
            $this->sendMail(
                RevocationButton::MAIL_TYPE_MERCHANT,
                [$recipientAddress => $recipientName],
                $senderAddress,
                $senderName,
                $formData,
                $context
            );

            if ($formData['email'] !== '') {
                $customerName = trim($formData['firstName'] . ' ' . $formData['lastName']);
                $this->sendMail(
                    RevocationButton::MAIL_TYPE_CUSTOMER,
                    [$formData['email'] => $customerName !== '' ? $customerName : $formData['email']],
                    $senderAddress,
                    $senderName,
                    $formData,
                    $context
                );
            }
        } catch (\Throwable $ex) {
            $this->logger->error('Could not send revocation request mail.', ['exception' => $ex]);

            return $this->jsonAlert('danger', $this->trans('codebaristaRevocationRequest.errorMessage'));
        }

        return $this->jsonAlert('success', $this->trans('codebaristaRevocationRequest.successMessage'));
    }

    /**
     * @param array<string> $recipients
     * @param array<string, mixed> $formData
     */
    private function sendMail(
        string $technicalName,
        array $recipients,
        string $senderAddress,
        string $senderName,
        array $formData,
        SalesChannelContext $context
    ): void {
        $mailData = new DataBag();
        $mailData->set('recipients', $recipients);
        $mailData->set('senderName', $senderName);
        $mailData->set('senderEmail', $senderAddress);
        $mailData->set('salesChannelId', $context->getSalesChannelId());
        $mailData->set('templateId', null);
        $mailData->set('contentHtml', '');
        $mailData->set('contentPlain', '');
        $mailData->set('subject', '');
        $mailData->set('mailTemplateTypeId', null);

        $templateData = [
            'revocationRequestFormData' => $formData,
            'salesChannel' => $context->getSalesChannel(),
        ];

        $template = $this->loadTemplate($technicalName, $context);

        $mailData->set('subject', $template['subject']);
        $mailData->set('contentHtml', $template['content_html']);
        $mailData->set('contentPlain', $template['content_plain']);

        $this->mailService->send($mailData->all(), $context->getContext(), $templateData);
    }

    /**
     * @return array{subject:string, content_html:string, content_plain:string}
     */
    private function loadTemplate(string $technicalName, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', $technicalName));
        $criteria->setLimit(1);

        /** @var MailTemplateEntity|null $template */
        $template = $this->mailTemplateRepository->search($criteria, $context->getContext())->first();

        if ($template === null) {
            throw new \RuntimeException(sprintf('Mail template "%s" not found.', $technicalName));
        }

        return [
            'subject' => self::asTrimmedString($template->getSubject()),
            'content_html' => self::asTrimmedString($template->getContentHtml()),
            'content_plain' => self::asTrimmedString($template->getContentPlain()),
        ];
    }

    private static function asTrimmedString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function jsonAlert(string $type, string $content): Response
    {
        return $this->json([
            [
                'type' => $type,
                'alert' => $this->renderView('@Storefront/storefront/utilities/alert.html.twig', [
                    'type' => $type,
                    'content' => $content,
                ]),
            ],
        ]);
    }
}
