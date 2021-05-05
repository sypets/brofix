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

use Sypets\Brofix\CheckLinks\CheckLinksStatistics;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Exceptions\MissingConfigurationException;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Send mail without Fluid (for TYPO3 9)
 */
class GenerateCheckResultPlainMail extends AbstractGenerateCheckResultMail
{
    public function generateMail(Configuration $config, CheckLinksStatistics $stats, int $pageId): bool
    {
        $mail = GeneralUtility::makeInstance(MailMessage::class);
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

        // @todo handle format html / text or both
        // @todo use template $config->getMailTemplate()
        $body =  'Number of broken links  :' . $stats->getCountBrokenLinks() . "\n";
        $body .= 'Total checked links     :' . $stats->getCountLinksChecked() . "\n";
        $body .= 'Percent broken links    :' . $stats->getPercentBrokenLinks() . "\n\n";
        $body .= 'Number of pages checked :' . $stats->getCountPages() .  "\n";
        $body .= 'Checked depth           :' . $config->getDepth() .  "\n\n";
        $body .= 'Number of links excluded:' . $stats->getCountExcludedLinks() . "\n";
        $body .= 'Percent links excluded  :' . $stats->getPercentExcludedLinks() . "\n";

        $subject = $config->getMailSubject() ?: 'Broken link report for ';
        $subject .= '"' . $stats->getPageTitle() . '" [' . $pageId . ']: ' . $stats->getCountBrokenLinks();

        $mail->setTo(...$recipients)
            ->setFrom($from)
            ->setSubject($subject)
            ->setBody($body);

        $replyTo = $config->getMailReplyTo();
        if ($replyTo !== '') {
            $mail->setReplyTo($replyTo);
        }

        $this->mailer->send($mail);

        // Is not supported in this version, use empty string
        $this->messageId = '';

        return $mail->isSent();
    }
}
