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

use Sypets\Brofix\CheckLinks\ExcludeLinkTarget;
use Sypets\Brofix\CheckLinks\LinkTargetCache\LinkTargetPersistentCache;
use Sypets\Brofix\Linktype\ExternalLinktype;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class ExternalLinktypePreprocessUrlTest extends UnitTestCase
{
    /**
     * @return \Generator<string,string[]>
     */
    public function preprocessUrlsDataProvider(): \Generator
    {
        // regression test for issue #92230: handle incomplete or faulty URLs gracefully
        yield 'faulty URL with mailto' => [
            'mailto:http://example.org',
            'mailto:http://example.org'
        ];
        yield 'Relative URL' => [
            '/abc',
            '/abc'
        ];

        // regression tests for issues #89488, #89682
        yield 'URL with query parameter and ampersand' => [
            'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829',
            'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829'
        ];
        yield 'URL with query parameter and ampersand with HTML entities' => [
            'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&amp;cs=1A3FFBC44FAB6B2A181C9525249C3A829',
            'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829'
        ];

        // regression tests for #89378
        yield 'URL with path with dashes' => [
            'https://example.com/Unternehmen/Ausbildung-Qualifikation/Weiterbildung-in-Niedersachsen/',
            'https://example.com/Unternehmen/Ausbildung-Qualifikation/Weiterbildung-in-Niedersachsen/'
        ];
        yield 'URL with path with dashes (2)' => [
            'https://example.com/startseite/wirtschaft/wirtschaftsfoerderung/beratung-foerderung/gruenderberatung/gruenderforen.html',
            'https://example.com/startseite/wirtschaft/wirtschaftsfoerderung/beratung-foerderung/gruenderberatung/gruenderforen.html'
        ];
        yield 'URL with path with dashes (3)' => [
            'http://example.com/universitaet/die-uni-im-ueberblick/lageplan/gebaeude/building/120',
            'http://example.com/universitaet/die-uni-im-ueberblick/lageplan/gebaeude/building/120'
        ];
        yield 'URL with path and query parameters (including &, ~,; etc.)' => [
            'http://example.com/tv?bcpid=1701167454001&amp;amp;amp;bckey=AQ~~,AAAAAGL7LqU~,aXlKNnCf9d9Tmck-kOc4PGFfCgHjM5JR&amp;amp;amp;bctid=1040702768001',
            'http://example.com/tv?bcpid=1701167454001&amp;amp;bckey=AQ~~,AAAAAGL7LqU~,aXlKNnCf9d9Tmck-kOc4PGFfCgHjM5JR&amp;amp;bctid=1040702768001'
        ];

        // make sure we correctly handle URLs with query parameters and fragment etc.
        yield 'URL with query parameters, fragment, user, pass, port etc.' => [
            'http://usr:pss@example.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment',
            'http://usr:pss@example.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment'
        ];
        yield 'domain with special characters, URL with query parameters, fragment, user, pass, port etc.' => [
            'http://usr:pss@äxample.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment',
            'http://usr:pss@xn--xample-9ta.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment'
        ];

        // domains with special characters: should be converted to punycode
        yield 'domain with special characters' => [
            'https://www.grün-example.org',
            'https://www.xn--grn-example-uhb.org'
        ];
        yield 'domain with special characters and path' => [
            'https://www.grün-example.org/a/bcd-efg/sfsfsfsfsf',
            'https://www.xn--grn-example-uhb.org/a/bcd-efg/sfsfsfsfsf'
        ];
    }

    /**
     * @test
     * @dataProvider preprocessUrlsDataProvider
     */
    public function preprocessUrlReturnsCorrectString(string $inputUrl, string $expectedResult): void
    {
        $subject = $this->instantiateExternalLinktype();
        $method = new \ReflectionMethod($subject, 'preprocessUrl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($subject, [$inputUrl]);
        self::assertEquals($expectedResult, $result);
    }

    private function instantiateExternalLinktype(): ExternalLinktype
    {
        $requestFactoryProphecy = $this->prophesize(RequestFactory::class);
        $excludeLinkTargetProphecy = $this->prophesize(ExcludeLinkTarget::class);
        $linkTargetCacheProphecy = $this->prophesize(LinkTargetPersistentCache::class);

        return new ExternalLinktype(
            $requestFactoryProphecy->reveal(),
            $excludeLinkTargetProphecy->reveal(),
            $linkTargetCacheProphecy->reveal()
        );
    }
}
