<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional;

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

use Psr\Http\Message\ServerRequestInterface;
use Sypets\Brofix\Command\CommandUtility;
use Sypets\Brofix\Configuration\Configuration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctional extends FunctionalTestCase
{
    protected const FAKE_BACKEND_URI = 'https://localhost/typo3';

    protected const EXTENSION_CONFIGURATION_ARRAY = [
        'excludeSoftrefs' => 'url',
        'excludeSoftrefsInFields' => 'tt_content.bodytext',
        'traverseMaxNumberOfPagesInBackend' => 100,
    ];

    protected array $coreExtensionsToLoad = [
        'backend',
        'fluid',
        //'info',
        'install'
    ];
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/brofix',
    ];

    /**
     * @var Configuration
     */
    protected $configuration;

    protected ServerRequestInterface $request;

    /**
     * Set up for set up the backend user, initialize the language object
     * and creating the Export instance
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->initializeConfiguration();

        $this->request = CommandUtility::createFakeWebRequest(self::FAKE_BACKEND_URI);
    }

    /**
     * @throws \Exception
     */
    protected function initializeConfiguration(): void
    {
        $searchFields = [
            'pages' => ['media', 'url', 'canonical_link'],
            'tt_content' => ['bodytext', 'header_link', 'records']
        ];
        $linkTypes = ['db', 'file', 'external'];

        $tsConfigPath = GeneralUtility::getFileAbsFileName('EXT:brofix/Configuration/TsConfig/Page/pagetsconfig.tsconfig');
        $this->configuration = GeneralUtility::makeInstance(Configuration::class, self::EXTENSION_CONFIGURATION_ARRAY);

        // load default values
        $this->configuration->setSearchFields($searchFields);
        $this->configuration->setLinkTypes($linkTypes);
        $this->configuration->overrideTsConfigByString(file_get_contents($tsConfigPath));
        $this->configuration->overrideTsConfigByString('mod.brofix.linktypesConfig.external.headers.User-Agent = Mozilla/5.0 (compatible; Broken Link Checker; +https://example.org/imprint.html)');
    }
}
