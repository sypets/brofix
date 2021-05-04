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

use Symfony\Component\Mailer\SentMessage;
use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Exceptions\MissingConfigurationException;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\TemplatePaths;

class GenerateCheckResultMail
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

    public function generateMail(Configuration $config, CheckLinksStatistics $stats, int $pageId)
    {
        $templatePaths = new TemplatePaths($GLOBALS['TYPO3_CONF_VARS']['MAIL']);
        $fluidEmail = GeneralUtility::makeInstance(FluidEmail::class, $templatePaths);
        $recipients = $config->getMailRecipients();

        if ($recipients === []) {
            throw new MissingConfigurationException(
                'Missing configuration for email recipient (Tsconfig: mod.brofix.mail.recipients)'
            );
        }

        $from = $config->getMailFrom();
        if ($from === '') {
            throw new MissingConfigurationException(
                'Missing configuration for email sender (Tsconfig: mod.brofix.mail.from)'
            );
        }

        $fluidEmail->to(...$recipients)
            ->from($from)
            ->format($GLOBALS['TYPO3_CONF_VARS']['MAIL']['format'] ?? 'both')
            ->setTemplate($config->getMailTemplate())
            ->assign('stats', $stats)
            ->assign('depth', $config->getDepth())
            ->assign('subject', $config->getMailSubject())
            ->assign('pageId', $pageId);

        $replyTo = $config->getMailReplyTo();
        if ($replyTo !== '') {
            $fluidEmail->setReplyTo($replyTo);
        }
        $subject = $config->getMailSubject();
        if ($subject) {
            $fluidEmail->subject($subject);
        }

        $this->mailer->send($fluidEmail);

        /**
         * @var SentMessage
         */
        $this->messageId = $this->mailer->getSentMessage()->getMessageId();
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }
}
