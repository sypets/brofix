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

use Sypets\Brofix\CheckLinks\CrawlDelay;
use Sypets\Brofix\Tests\Unit\AbstractUnitTest;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CrawlDelayTest extends AbstractUnitTest
{
    /**
     * Set up for set up the backend user, initialize the language object
     * and creating the Export instance
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeConfiguration();
    }

    protected function initializeCrawlDelay(): CrawlDelay
    {
        return GeneralUtility::makeInstance(CrawlDelay::class);
    }

    /**
     * @test
     */
    public function crawlDelayDoesNotDelayForNewDomain(): void
    {
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $result = $subject->crawlDelay('example.org');
        self::assertEquals(0, $result, 'Result should be 0 (no crawl delay)');
    }

    /**
     * Make sure that the crawl delay from the second domain is
     * independant (both should not delay).
     *
     * @test
     */
    public function crawlDelayDoesNotDelayForOtherDomain(): void
    {
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $subject->crawlDelay('example.org');
        $result = $subject->crawlDelay('example.com');
        self::assertEquals(0, $result, 'Result should be 0 (no crawl delay)');
    }

    /**
     * Call crawlDelay for the same domain twice: Should delay
     * the second time.
     *
     * @test
     */
    public function crawlDelayDoesDelayForDomainTwice(): void
    {
        $domain = 'example.org';
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $expected = $this->configuration->getCrawlDelaySeconds();

        $subject->crawlDelay($domain);
        $result = $subject->crawlDelay($domain);
        self::assertEquals(
            $expected,
            $result,
            'Result should be the same value as expected delay'
        );
    }
}
