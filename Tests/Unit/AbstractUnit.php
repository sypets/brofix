<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit;

use PHPUnit\Framework\MockObject\MockObject;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/Testing/UnitTesting/Index.html
 * @see https://docs.phpunit.de/en/10.5/test-doubles.html
 *
 * createMock()
 *  is the standard, best-practice method to create a test double where all methods are replaced by default, honoring the visibility of methods (private/protected methods cannot be called).
 * getAccessibleMock()
 * is a specialized tool (often used in TYPO3 testing) designed to mock a class while allowing the test to access and invoke its protected or private methods for testing purposes.
 */
abstract class AbstractUnit extends UnitTestCase
{
    protected const EXTENSION_CONFIGURATION_ARRAY = [
        'excludeSoftrefs' => 'url',
        'excludeSoftrefsInFields' => 'tt_content.bodytext',
        'traverseMaxNumberOfPagesInBackend' => 100,
    ];

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @throws \Exception
     */
    protected function initializeConfiguration(): void
    {
        // use defaults
        $this->configuration = GeneralUtility::makeInstance(
            Configuration::class,
            self::EXTENSION_CONFIGURATION_ARRAY
        );
        $this->configuration->overrideTsConfigByArray(
            [
                    'linktypesConfig.external.headers.User-Agent'
                        => 'Mozilla/5.0 (compatible; Broken Link Checker; +https://example.org/imprint.html)'
                ]
        );
    }

    protected function inializeLanguageServiceMock(): void
    {
        $GLOBALS['LANG'] = $this->buildLanguageServiceMock();
    }

    protected function buildLanguageServiceMock(): MockObject
    {
        $languageServiceMockObject =
            $this->createMock(LanguageService::class);
        /**
         * returnValue is deprecated:
         * Use <code>$double->willReturn()</code> instead of <code>$double->will($this->returnValue())</code>
         */
        $languageServiceMockObject->method('sL')->willReturn('translation string');
        return $languageServiceMockObject;
    }
}
