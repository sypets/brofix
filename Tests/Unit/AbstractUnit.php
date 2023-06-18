<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit;

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

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

abstract class AbstractUnit extends UnitTestCase
{
    use ProphecyTrait;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @throws \Exception
     */
    protected function initializeConfiguration(): void
    {
        /*
        $tsConfigPath = __DIR__ . '/../../Configuration/TsConfig/Page/pagetsconfig.tsconfig';

        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        // load default values
        $this->configuration->overrideTsConfigByString(file_get_contents($tsConfigPath));
        */

        // use defaults
        $this->configuration = GeneralUtility::makeInstance(Configuration::class);
        $this->configuration->overrideTsConfigByArray(
            [
                    'linktypesConfig.external.headers.User-Agent'
                        => 'Mozilla/5.0 (compatible; Broken Link Checker; +https://example.org/imprint.html)'
                ]
        );
    }

    protected function inializeLanguageServiceProphecy(): void
    {
        $GLOBALS['LANG'] = $this->buildLanguageServiceProphecy()->reveal();
    }

    /**
     * @return ObjectProphecy<LanguageService>
     */
    protected function buildLanguageServiceProphecy(): ObjectProphecy
    {
        $languageServiceProphecy = $this->prophesize(LanguageService::class);
        $languageServiceProphecy->getLL(Argument::any())->willReturn('translation string');
        return $languageServiceProphecy;
    }
}
