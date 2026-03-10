<?php

declare(strict_types=1);
namespace Sypets\Brofix\Mail;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Exceptions\MissingConfigurationException;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

/**
 * Send mail with Fluid
 */
class GenerateCheckResultFluidMail implements SingletonInterface
{
    protected Mailer $mailer;

    /**
     * @var string
     */
    protected $messageId;

    public function __construct(protected readonly LoggerInterface $logger)
    {
        $this->mailer = $this->instantiateMailer();
    }

    /**
     * Consider different constructor of pluswerk/mail-logger MailerExtender (which XCLASSes Mailer)
     */
    protected function instantiateMailer(): Mailer
    {
        $typo3Version = GeneralUtility::makeInstance(Typo3Version::class);
        if ($typo3Version->getMajorVersion() > 12
            && ExtensionManagementUtility::isLoaded('mail_logger')
            && isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Mailer::class]['className'])
            /**
             * even in a plain PHP file where it works, ::class doesn't require the class to exist - it just
             * resolves the name based on the current namespace and use statements, and substitutes in the resulting string
             *
             * Use string here because otherwise phpstan will complain.
             */
            && $GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][Mailer::class]['className'] === 'Pluswerk\\MailLogger\\Logging\\MailerExtender'
        ) {
            /** @phpstan-ignore-next-line  */
            $loggingTransportFactory = GeneralUtility::makeInstance('Pluswerk\\MailLogger\\Logging\\LoggingTransportFactory');
            /** @phpstan-ignore-next-line  */
            if (!$loggingTransportFactory) {
                $this->logger->error('Could not instantiate pluswerk/mail_logger: LoggingTransportFactory');
                throw new \RuntimeException('Could not instantiate pluswerk/mail_logger: LoggingTransportFactory', 4056014818);
            }
            $mailer = GeneralUtility::makeInstance(Mailer::class, $loggingTransportFactory);
        } else {
            $mailer = GeneralUtility::makeInstance(Mailer::class);
        }
        return $mailer;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * @param Configuration $config
     * @param CheckLinksStatistics $stats
     * @param int $pageId
     * @return bool
     * @throws MissingConfigurationException
     * @throws TransportExceptionInterface
     */
    public function generateMail(Configuration $config, CheckLinksStatistics $stats, int $pageId): bool
    {
        $templatePaths = new TemplatePaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']);

        /**
         * @var FluidEmail
         */
        $fluidEmail = GeneralUtility::makeInstance(FluidEmail::class, $templatePaths);
        $recipients = $config->getMailRecipients();

        if ($recipients === []) {
            throw new MissingConfigurationException(
                'Missing configuration for email recipient (Tsconfig: mod.brofix.mail.recipients)',
                8980109579
            );
        }

        $from = $config->getMailFromEmail();
        if ($from === '') {
            throw new MissingConfigurationException(
                'Missing configuration for email sender (Tsconfig: mod.brofix.mail.from)',
                7661484439
            );
        }
        // to: can be Address|string or ...array in Symfony\Component\Mime\Email
        $fluidEmail->to(...$recipients)
            // from: can be Address|string in Symfony\Component\Mime\Email
            ->from(new Address($from, $config->getMailFromName()))
            ->format($GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] ?? 'both')
            ->setTemplate($config->getMailTemplate())
            ->assign('stats', $stats)
            ->assign('depth', $config->getDepth())
            ->assign('subject', $config->getMailSubject())
            ->assign('language', $config->getMailLanguage())
            ->assign('padLength', 32)
            ->assign('pageId', $pageId);

        $replyTo = $config->getMailReplyToEmail();
        if ($replyTo !== '') {
            $fluidEmail->replyTo($replyTo);
        }
        $subject = $config->getMailSubject();
        if ($subject) {
            $fluidEmail->subject($subject);
        }

        try {
            $this->mailer->send($fluidEmail);
            $this->logger->debug(
                sprintf(
                    'Email successfully sent to=<%s> from=<%s> subject=<%s> pageId=<%d>',
                    $this->convertEmailAdressesToString($recipients),
                    $from,
                    $subject,
                    $pageId
                )
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf(
                    'Exception sending mail Exception message=<%s> to=<%s> from=<%s> subject=<%s> pageId=<%d>',
                    $e->getMessage(),
                    $this->convertEmailAdressesToString($recipients),
                    $from,
                    $subject,
                    $pageId
                )
            );
            throw $e;
        }

        /**
         * @var SentMessage
         */
        $sentMessage = $this->mailer->getSentMessage();
        $this->messageId = $sentMessage->getMessageId();

        return true;
    }

    /**
     * @param Address[] $emails
     * @return string
     */
    protected function convertEmailAdressesToString(array $emails): string
    {
        $adresses = [];
        foreach ($emails as $email) {
            $adresses[] = $email->getAddress();
        }
        if (empty($adresses)) {
            return '';
        }
        return implode(', ', $adresses);
    }
}
