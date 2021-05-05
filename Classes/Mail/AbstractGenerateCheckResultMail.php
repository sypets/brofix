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

use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractGenerateCheckResultMail implements GenerateCheckResultMailInterface
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
}
