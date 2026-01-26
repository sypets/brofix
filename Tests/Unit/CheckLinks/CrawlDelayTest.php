<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\Configuration;

use Sypets\Brofix\CheckLinks\CrawlDelay;
use Sypets\Brofix\Tests\Unit\AbstractUnit;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CrawlDelayTest extends AbstractUnit
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
    #[Test]
    public function crawlDelayReturnsTrueForNewDomain(): void
    {
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $result = $subject->crawlDelay('example.org');
        self::assertTrue($result, 'Result should be true');
    }

    /**
     * @test
     */
    #[Test]
    public function crawlDelayNoWaitTimeForNewDomain(): void
    {
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $subject->crawlDelay('example.org');
        $result = $subject->getLastWaitSeconds();
        self::assertEquals(0, $result, 'Result should be 0');
    }

    /**
     * Make sure that the crawl delay from the second domain is
     * independant (both should not delay).
     *
     * @test
     */
    #[Test]
    public function crawlDelayDoesNotDelayForOtherDomain(): void
    {
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $subject->crawlDelay('example.org');
        $subject->crawlDelay('example.com');
        $result = $subject->getLastWaitSeconds();
        self::assertEquals(0, $result, 'Result should be 0 (no crawl delay)');
    }

    /**
     * Call crawlDelay for the same domain twice: Should delay
     * the second time.
     *
     * @test
     */
    #[Test]
    public function crawlDelayDoesDelayForDomainTwice(): void
    {
        $domain = 'example.org';
        $subject = $this->initializeCrawlDelay();
        $subject->setConfiguration($this->configuration);
        $expected = $this->configuration->getCrawlDelaySeconds();

        $subject->crawlDelay($domain);
        $subject->crawlDelay($domain);
        $result = $subject->getLastWaitSeconds();
        self::assertEquals(
            $expected,
            $result,
            'Result should be the same value as expected delay'
        );
    }
}
