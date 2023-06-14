<?php

declare(strict_types=1);

namespace Sypets\Brofix\Tests\Functional\CheckLinks\LinkTargetCache;

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

use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\Linktype\ErrorParams;
use Sypets\Brofix\Linktype\ExternalLinktype;
use Sypets\Brofix\Tests\Functional\AbstractFunctional;

class LinkTargetPersistentCacheTest extends AbstractFunctional
{
    public function testHasEntryForUrlReturnsFalse(): void
    {
        $url = 'https://example.org';
        $type = 'external';

        $subject = new LinkTargetPersistentCache();
        $result = $subject->hasEntryForUrl($url, $type);

        self::assertFalse($result, 'Empty cache should not have entry for url');
    }

    public function testGetUrlResponseForUrlReturnsEmptyArray(): void
    {
        $url = 'https://example.org';
        $type = 'external';

        $subject = new LinkTargetPersistentCache();
        $result = $subject->getUrlResponseForUrl($url, $type);

        self::assertEquals([], $result, 'Empty cache should return empty array for url');
    }

    public function testGenerateUrlResponseReturnsCorrectValue(): void
    {
        /**
         * @var ErrorParams
         */
        $emptyErrorParams = new ErrorParams();

        $expected = [
            'valid' => true,
            'errorParams' => $emptyErrorParams->toArray()
        ];

        $subject = new LinkTargetPersistentCache();
        $result = $subject->generateUrlResponse(true, $emptyErrorParams);

        self::assertEquals($expected, $result, 'generateUrlResponse returns correct result');
    }

    public function testRemoveRemovesEntry(): void
    {
        $url = 'https://example.org';
        $type = 'external';

        $subject = new LinkTargetPersistentCache();
        $urlResponse = $subject->generateUrlResponse(true, new ErrorParams());

        $subject->setResult($url, $type, $urlResponse);
        $subject->remove($url, $type);

        $result = $subject->hasEntryForUrl($url, $type);
        self::assertFalse($result, 'Entry should be removed');
    }

    public function testSetEntrySetsCorrectValue(): void
    {
        /**
         * @var ErrorParams
         */
        $errorParams = new ErrorParams();
        $url = 'https://example.org';
        $type = 'external';
        $expected = [
            'valid' => true,
            'errorParams' => $errorParams->toArray()
        ];

        $subject = new LinkTargetPersistentCache();
        $urlResponse = $subject->generateUrlResponse(true, $errorParams);
        $subject->setResult($url, $type, $urlResponse);
        $result = $subject->getUrlResponseForUrl($url, $type);
        // will contain time of check, ignore here
        unset($result['lastChecked']);

        self::assertEquals($expected, $result, 'generateUrlResponse returns correct result');
    }

    /**
     * Set value twice, make sure value returned is last value.
     */
    public function testSetEntrySetsCorrectValueToLastSet(): void
    {
        /**
         * @var ErrorParams
         */
        $errorParams1 = new ErrorParams();
        /**
         * @var ErrorParams
         */
        $errorParams2 = new ErrorParams();
        $errorParams2->setErrorType(ExternalLinktype::ERROR_TYPE_HTTP_STATUS_CODE);
        $errorParams2->setErrno(404);
        $errorParams2->setMessage('404 - Page not found');
        $url = 'https://example.org';
        $type = 'external';
        $expected = [
            'valid' => false,
            'errorParams' => [
                'isValid' => false,
                'errorType' => 'httpStatusCode',
                'errno' => 404,
                'exceptionMsg' => '',
                'message' => '404 - Page not found',
                'custom' => []
            ]
        ];

        $subject = new LinkTargetPersistentCache();
        $urlResponse = $subject->generateUrlResponse(true, $errorParams1);
        $subject->setResult($url, $type, $urlResponse);
        $urlResponse = $subject->generateUrlResponse(false, $errorParams2);
        $subject->setResult($url, $type, $urlResponse);
        $result = $subject->getUrlResponseForUrl($url, $type);
        // will contain time of check, ignore here
        unset($result['lastChecked']);

        self::assertEquals($expected, $result, 'generateUrlResponse returns correct result');
    }
}
