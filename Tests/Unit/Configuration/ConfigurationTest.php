<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\Configuration;

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

use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ConfigurationTest extends UnitTestCase
{

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * Set up for set up the backend user, initialize the language object
     * and creating the Export instance
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeConfiguration();
    }

    /**
     * @throws \Exception
     */
    protected function initializeConfiguration()
    {
        $tsConfigPath = GeneralUtility::getFileAbsFileName(
            'EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig'
        );

        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        // load default values
        $this->configuration->overrideTsConfigByString(file_get_contents($tsConfigPath));
        $this->configuration->overrideTsConfigByString(
            'mod.brofix.linktypesConfig.external.headers.User-Agent = Mozilla/5.0 (compatible; Broken Link Checker; +https://example.org/imprint.html)'
        );
    }

    /**
     * @test
     */
    public function getMailFromEmailIsCorrectDefault()
    {
        $email = $this->configuration->getMailFromEmail();

        // expected, actual, message
        self::assertTrue(GeneralUtility::validEmail($email),
            'getMailFromEmail() should return valid email by default');
    }

    /**
     * @test
     */
    public function getMailFromEmailUsesSystemDefault()
    {
        $email = 'system@example.org';
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] = 'system@example.org';

        // expected, actual, message
        self::assertEquals($email, $this->configuration->getMailFromEmail(),
            'getMailFromEmail() should return empty string by default');
    }

    public function getMailFromEmailReturnsCorrectTsconfig()
    {
        $emailExpected = 'system@example.org';

        $this->configuration->overrideTsConfigByString('mod.brofix.mail.fromemail = ' . $emailExpected);
        $emailActual = $this->configuration->getMailFromEmail();

        self::assertEquals($emailExpected, $emailActual,
            'getMailFromEmail() should return valid email by default');
    }

    /**
     * @test
     */
    public function getMailFromNameIsCorrectDefault()
    {
        self::assertEquals('', $this->configuration->getMailFromName(),
            'getMailFromName() should return empty string by default');
    }

    public function getMailFromNameReturnsCorrectTsconfig()
    {
        $nameExpected = 'Webmaster';

        $this->configuration->overrideTsConfigByString('mod.brofix.mail.fromname = ' . $nameExpected);
        $nameActual = $this->configuration->getMailFromName();

        self::assertEquals($nameExpected, $nameActual,
            'getMailFromName() should return valid email by default');
    }

    /**
     * @test
     */
    public function getMailRecipientsIsCorrectDefault()
    {
        // expected, actual, message
        self::assertEquals([], $this->configuration->getMailRecipients(),
            'getMailRecipients() should return empty array by default');
    }

    /**
     * @test
     */
    public function getMailRecipientsReturnsCorrectValue()
    {
        $email = 'system@example.org';
        $valueExpected = [$email];

        $this->configuration->overrideTsConfigByString('mod.brofix.mail.recipients = ' . $email);

        // expected, actual, message
        self::assertEquals($valueExpected, $this->configuration->getMailRecipients(),
            'getMailRecipients() should return correct value');
    }

    /**
     * @test
     */
    public function getMailRecipientsReturnsCorrectValuesMultiple()
    {
        $email = 'system@example.org,system2@example.org';
        $valueExpected = explode(',', $email);

        $this->configuration->overrideTsConfigByString('mod.brofix.mail.recipients = ' . $email);

        // expected, actual, message
        self::assertEquals($valueExpected, $this->configuration->getMailRecipients(), 'getMailRecipients() should return correct value');
    }
}
