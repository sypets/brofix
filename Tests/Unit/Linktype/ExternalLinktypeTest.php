<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\Linktype;

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

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Response;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Linktype\ExternalLinktype;
use Sypets\Brofix\Tests\Unit\AbstractUnit;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ExternalLinktypeTest extends AbstractUnit
{
    use ProphecyTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inializeLanguageServiceProphecy();
    }

    /**
     * @test
     */
    public function checkLinkWithExternalUrlNotFoundReturnsCorrectStatus(): void
    {
        $httpMethod = 'GET';
        $options = $this->getRequestHeaderOptions($httpMethod);
        $url = 'https://localhost/~not-existing-url';

        $responseProphecy = $this->prophesize(Response::class);
        $responseProphecy->getStatusCode()->willReturn(404);
        $responseProphecy->getHeaders()->willReturn([]);

        $exceptionProphecy = $this->prophesize(ClientException::class);
        $exceptionProphecy->hasResponse()
            ->willReturn(true);
        $exceptionProphecy->getResponse()
            ->willReturn($responseProphecy->reveal());

        $requestFactoryProphecy = $this->prophesize(RequestFactory::class);
        $requestFactoryProphecy->request($url, $httpMethod, $options)
            ->willThrow($exceptionProphecy->reveal());
        $subject = $this->instantiateExternalLinktype($requestFactoryProphecy);

        $method = new \ReflectionMethod($subject, 'requestUrl');
        $method->setAccessible(true);
        /** @var LinkTargetResponse $linkTargetResponse */
        $linkTargetResponse = $method->invokeArgs($subject, [$url, $httpMethod, $options]);

        self::assertTrue($linkTargetResponse->isError());
    }

    /**
     * @test
     */
    public function checkLinkWithExternalUrlNotFoundResultsNotFoundErrorType(): void
    {
        $httpMethod = 'GET';
        $options = $this->getRequestHeaderOptions($httpMethod);
        $url = 'http://localhost/~not-existing-url';

        $responseProphecy = $this->prophesize(Response::class);
        $responseProphecy->getStatusCode()->willReturn(404);
        $responseProphecy->getHeaders()->willReturn([]);

        $exceptionProphecy = $this->prophesize(ClientException::class);
        $exceptionProphecy->hasResponse()
            ->willReturn(true);
        $exceptionProphecy->getResponse()
            ->willReturn($responseProphecy->reveal());

        $requestFactoryProphecy = $this->prophesize(RequestFactory::class);
        $requestFactoryProphecy->request($url, $httpMethod, $options)
            ->willThrow($exceptionProphecy->reveal());
        $subject = $this->instantiateExternalLinktype($requestFactoryProphecy);

        $method = new \ReflectionMethod($subject, 'requestUrl');
        $method->setAccessible(true);
        /** @var LinkTargetResponse $linkTargetResponse */
        $linkTargetResponse = $method->invokeArgs($subject, [$url, $httpMethod, $options]);

        self::assertTrue($linkTargetResponse->isError());
        self::assertTrue($linkTargetResponse->getErrorType() !== '');
    }

    /**
     * @param ObjectProphecy<RequestFactory>|null $requestFactoryProphecy
     * @return ExternalLinktype
     */
    private function instantiateExternalLinktype(ObjectProphecy $requestFactoryProphecy = null): ExternalLinktype
    {
        $requestFactoryProphecy = $requestFactoryProphecy ?: $this->prophesize(RequestFactory::class);

        $excludeLinkTargetProphecy = $this->prophesize(ExcludeLinkTarget::class);

        $linkTargetCacheProphycy = $this->prophesize(LinkTargetPersistentCache::class);

        return new ExternalLinktype(
            $requestFactoryProphecy->reveal(),
            $excludeLinkTargetProphecy->reveal(),
            $linkTargetCacheProphycy->reveal()
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
