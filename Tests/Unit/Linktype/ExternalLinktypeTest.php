<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\Linktype;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Linktype\ExternalLinktype;
use Sypets\Brofix\Tests\Unit\AbstractUnit;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExternalLinktypeTest extends AbstractUnit
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->inializeLanguageServiceMock();
    }

    /**
     * @test
     */
    #[Test]
    public function checkLinkWithExternalUrlNotFoundReturnsCorrectStatus(): void
    {
        $httpMethod = 'GET';
        $options = $this->getRequestHeaderOptions($httpMethod);
        $url = 'https://localhost/~not-existing-url';

        $responseMock = $this->createMock(Response::class);
        $responseMock->method('getStatusCode')->willReturn(404);
        $responseMock->method('getHeaders')->willReturn([]);

        $exceptionMock = $this->createMock(ClientException::class);
        $exceptionMock->method('hasResponse')->willReturn(true);
        $exceptionMock->method('getResponse')->willReturn($responseMock);

        $requestFactoryMock = $this->createMock(RequestFactory::class);
        $requestFactoryMock->method('request', $url, $httpMethod, $options)
            ->willThrowException($exceptionMock);
        $subject = $this->instantiateExternalLinktype($requestFactoryMock);

        $method = new \ReflectionMethod($subject, 'requestUrl');
        $method->setAccessible(true);
        /** @var LinkTargetResponse $linkTargetResponse */
        $linkTargetResponse = $method->invokeArgs($subject, [$url, $httpMethod, $options]);

        self::assertTrue($linkTargetResponse->isError());
    }

    /**
     * @test
     */
    #[Test]
    public function checkLinkWithExternalUrlNotFoundResultsNotFoundErrorType(): void
    {
        $httpMethod = 'GET';
        $options = $this->getRequestHeaderOptions($httpMethod);
        $url = 'http://localhost/~not-existing-url';

        $responseMock = $this->createMock(Response::class);
        $responseMock->method('getStatusCode')->willReturn(404);
        $responseMock->method('getHeaders')->willReturn([]);

        $exceptionMock = $this->createMock(ClientException::class);
        $exceptionMock->method('hasResponse')
            ->willReturn(true);
        $exceptionMock->method('getResponse')
            ->willReturn($responseMock);

        $requestFactoryMock = $this->createMock(RequestFactory::class);
        $requestFactoryMock->method('request', $url, $httpMethod, $options)
            ->willThrowException($exceptionMock);
        $subject = $this->instantiateExternalLinktype($requestFactoryMock);

        $method = new \ReflectionMethod($subject, 'requestUrl');
        $method->setAccessible(true);
        /** @var LinkTargetResponse $linkTargetResponse */
        $linkTargetResponse = $method->invokeArgs($subject, [$url, $httpMethod, $options]);

        self::assertTrue($linkTargetResponse->isError());
        self::assertTrue($linkTargetResponse->getErrorType() !== '');
    }

    private function instantiateExternalLinktype(MockObject $requestFactoryMock): ExternalLinktype
    {
        $excludeLinkTargetMock = $this->createMock(ExcludeLinkTarget::class);

        $linkTargetCacheMock = $this->createMock(LinkTargetPersistentCache::class);

        return new ExternalLinktype(
            $requestFactoryMock,
            $excludeLinkTargetMock,
            $linkTargetCacheMock
        );
    }

    /**
     * @param string $method
     * @return mixed[]
     */
    private function getRequestHeaderOptions(string $method): array
    {
        $options = [
            'cookies' => GeneralUtility::makeInstance(CookieJar::class),
            'allow_redirects' => ['strict' => true],
            'headers' => [
                'User-Agent' => 'Broken Link Fixer (https://example.org)',
                'Accept' => '*/*',
                'Accept-Language' => '*',
                'Accept-Encoding' => '*'
            ]
        ];

        if ($method === 'HEAD') {
            return $options;
        }
        return array_merge_recursive($options, ['headers' => ['Range' => 'bytes=0-4048']]);
    }
}
