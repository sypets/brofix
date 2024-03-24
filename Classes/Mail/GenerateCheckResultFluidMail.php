<?php

declare(strict_types=1);
namespace Sypets\Brofix\Mail;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Exceptions\MissingConfigurationException;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

/**
 * Send mail with Fluid
 */
class GenerateCheckResultFluidMail
{
    /**
     * @var Mailer
     */
    protected $mailer;

    /**
     * @var string
     */
    protected $messageId;

    public function __construct(Mailer $mailer = null)
    {
        $this->mailer = $mailer ?: GeneralUtility::makeInstance(Mailer::class);
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
                'Missing configuration for email recipient (Tsconfig: mod.brofix.mail.recipients)'
            );
        }

        $from = $config->getMailFromEmail();
        if ($from === '') {
            throw new MissingConfigurationException(
                'Missing configuration for email sender (Tsconfig: mod.brofix.mail.from)'
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

        $this->mailer->send($fluidEmail);

        /**
         * @var SentMessage
         */
        $sentMessage = $this->mailer->getSentMessage();
        $this->messageId = $sentMessage->getMessageId();

        return true;
    }
}
