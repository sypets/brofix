<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Functional\CheckLinks\LinkTargetCache;

use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
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

        self::assertNull($result, 'Empty cache should return null');
    }

    public function testRemoveRemovesEntry(): void
    {
        $url = 'https://example.org';
        $type = 'external';

        $subject = new LinkTargetPersistentCache();
        $linkTargetResponse = LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK);

        $subject->setResult($url, $type, $linkTargetResponse);
        $subject->remove($url, $type);

        $result = $subject->hasEntryForUrl($url, $type);
        self::assertFalse($result, 'Entry should be removed');
    }

    public function testSetEntrySetsCorrectValue(): void
    {
        $url = 'https://example.org';
        $type = 'external';

        $subject = new LinkTargetPersistentCache();
        $linkTargetResponse = LinkTargetResponse::createInstanceByStatus(LinkTargetResponse::RESULT_OK);
        $subject->setResult($url, $type, $linkTargetResponse);
        $actualLinkTargetResponse = $subject->getUrlResponseForUrl($url, $type);

        self::assertNotNull($actualLinkTargetResponse);
        $actualLinkTargetResponseArray = $actualLinkTargetResponse->toArray();
        $expectedLinkTargetResponseArray = $linkTargetResponse->toArray();
        // will contain time of check, ignore here
        unset($actualLinkTargetResponseArray['lastChecked']);
        unset($expectedLinkTargetResponseArray['lastChecked']);

        self::assertEquals($expectedLinkTargetResponseArray, $actualLinkTargetResponseArray, 'generateUrlResponse returns correct result');
    }

    /**
     * Set value twice, make sure value returned is last value.
     */
    public function testSetEntrySetsCorrectValueToLastSet(): void
    {
        $errorType = 'httpStatusCode';
        $errno = 404;
        $errorMessage = '404 - Page not found';
        $exceptionMsg = 'some exception message';
        $url = 'https://example.org';
        $type = 'external';
        $expected = [
            'status' => LinkTargetResponse::RESULT_BROKEN,
            'errorType' => $errorType,
            'errno' => $errno,
            'exceptionMessage' => $exceptionMsg,
            'message' => $errorMessage,
            'custom' => []

        ];

        $subject = new LinkTargetPersistentCache();
        $linkTargetResponse = LinkTargetResponse::createInstanceByError(
            $errorType,
            $errno,
            $errorMessage,
            $exceptionMsg
        );
        $subject->setResult($url, $type, $linkTargetResponse);
        $actualUrlResponse = $subject->getUrlResponseForUrl($url, $type);
        $result = $actualUrlResponse->toArray();
        // will contain time of check, ignore here
        unset($result['lastChecked']);

        self::assertEquals($expected, $result, 'generateUrlResponse returns correct result');
    }
}
