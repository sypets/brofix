<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\Command;

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

use Symfony\Component\Console\Tester\CommandTester;
use Sypets\Brofix\Command\CheckLinksCommand;
use Sypets\Brofix\Configuration\Configuration;
use Sypets\Brofix\Exceptions\MissingConfigurationException;
use Sypets\Brofix\Tests\Functional\AbstractFunctionalTest;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Utility\GeneralUtility;


class CheckLinksCommandTest extends AbstractFunctionalTest
{
    /**
     * @test
     */
    public function checkLinksCommandThrowsExceptionForNotExistingStartpage()
    {
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);

        // should throw exception for not existing start page
        $this->expectException(\InvalidArgumentException::class);

        // prefix the key with two dashes when passing options (see CommandTester)
        // https://symfony.com/doc/current/console.html#testing-commands
        $tester->execute(['--start-pages' => '1'], []);
    }

    /**
     * Test for no startpages passed as arguments and no sites configured
     *
     * @test
     */
    public function checkLinksCommandReturnsCorrectResultForNoStartpages()
    {
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);
        $result = $tester->execute([], []);

        $this->assertEquals(1, $result,
            'Test should return 1 if no startpages passed as arguments and no sites configured');
    }

    /**
     * @test
     *
     */
    public function checkLinksCommandChecksLinksMissingEmailException()
    {
        $parameters = [
            '--start-pages' => '1'
        ];

        $this->importDataSet(__DIR__ . '/Fixtures/input_content_with_broken_link_external.xml');
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);

        // should throw exception because of missing email recipient
        $this->expectException(MissingConfigurationException::class);

        $tester->execute($parameters, []);
    }

    /**
     * @test
     */
    public function checkLinksCommandChecksLinksWithNoSendEmailReturnsOk()
    {
        $parameters = [
            '--start-pages' => '1',
            '--send-email' => '0'
        ];

        $this->importDataSet(__DIR__ . '/Fixtures/input_content_with_broken_link_external.xml');
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);
        $result = $tester->execute($parameters, []);

        $this->assertEquals(0, $result, 'Console command should return 0 if no errors.');
    }

    /**
     * @test
     */
    public function checkLinksCommandStatsNumberOfPages()
    {
        $parameters = [
            '--start-pages' => '1',
            '--send-email' => '0'
        ];

        $this->importDataSet(__DIR__ . '/Fixtures/input_content_with_broken_link_external.xml');
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);
        $tester->execute($parameters, []);
        $stats = $command->getStatistics(1);

        $this->assertEquals($stats->getCountPages(), 1,
            'The number of pages checked should be 1.');
    }

    /**
     * @test
     */
    public function checkLinksCommandStatsNumberOfBrokenLinks()
    {
        $parameters = [
            '--start-pages' => '1',
            '--send-email' => '0'
        ];

        $this->importDataSet(__DIR__ . '/Fixtures/input_content_with_broken_link_external.xml');
        $command = GeneralUtility::makeInstance(CheckLinksCommand::class);
        $tester = new CommandTester($command);
        $tester->execute($parameters, []);
        $stats = $command->getStatistics(1);

        $this->assertEquals($stats->getCountBrokenLinks(), 1);
    }

}
