<?php

declare(strict_types=1);
namespace Sypets\Brofix\Tests\Unit\Linktype;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
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
    public static function preprocessUrlsDataProvider(): \Generator
    {
        // regression test for issue #92230: handle incomplete or faulty URLs gracefully
        // faulty URL with mailto
        yield 'faulty URL with mailto' => [
            'inputUrl' => 'mailto:http://example.org',
            'expectedResult' => 'mailto:http://example.org',

        ];

        // Relative URL
        yield
            'Relative URL' => [
                'inputUrl' => '/abc',
                'expectedResult' => '/abc'

        ];

        // regression tests for issues #89488, #89682
        yield
            'URL with query parameter and ampersand' => [
                'inputUrl' => 'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829',
                'expectedResult' =>'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829'

        ];
        yield
            'URL with query parameter and ampersand with HTML entities' => [
                'inputUrl' => 'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&amp;cs=1A3FFBC44FAB6B2A181C9525249C3A829',
                'expectedResult' =>'https://standards.cen.eu/dyn/www/f?p=204:6:0::::FSP_ORG_ID,FSP_LANG_ID:,22&cs=1A3FFBC44FAB6B2A181C9525249C3A829'

        ];

        // regression tests for #89378
        yield
            'URL with path with dashes' => [
                'inputUrl' => 'https://example.com/Unternehmen/Ausbildung-Qualifikation/Weiterbildung-in-Niedersachsen/',
                'expectedResult' =>'https://example.com/Unternehmen/Ausbildung-Qualifikation/Weiterbildung-in-Niedersachsen/'

        ];

        yield
            'URL with path with dashes (2)' => [
                'inputUrl' => 'https://example.com/startseite/wirtschaft/wirtschaftsfoerderung/beratung-foerderung/gruenderberatung/gruenderforen.html',
                'expectedResult' =>'https://example.com/startseite/wirtschaft/wirtschaftsfoerderung/beratung-foerderung/gruenderberatung/gruenderforen.html'

        ];

        yield
            'URL with path with dashes (3)' => [
                'inputUrl' => 'http://example.com/universitaet/die-uni-im-ueberblick/lageplan/gebaeude/building/120',
                'expectedResult' =>'http://example.com/universitaet/die-uni-im-ueberblick/lageplan/gebaeude/building/120'

        ];
        yield
            'URL with path and query parameters (including &, ~,; etc.)' => [
                'inputUrl' => 'http://example.com/tv?bcpid=1701167454001&amp;amp;amp;bckey=AQ~~,AAAAAGL7LqU~,aXlKNnCf9d9Tmck-kOc4PGFfCgHjM5JR&amp;amp;amp;bctid=1040702768001',
                'expectedResult' =>'http://example.com/tv?bcpid=1701167454001&amp;amp;bckey=AQ~~,AAAAAGL7LqU~,aXlKNnCf9d9Tmck-kOc4PGFfCgHjM5JR&amp;amp;bctid=1040702768001'

        ];

        // make sure we correctly handle URLs with query parameters and fragment etc.
        yield
            'URL with query parameters, fragment, user, pass, port etc.' => [
                'inputUrl' => 'http://usr:pss@example.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment',
                'expectedResult' =>'http://usr:pss@example.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment'

        ];
        yield
            'domain with special characters, URL with query parameters, fragment, user, pass, port etc.' => [
                'inputUrl' => 'http://usr:pss@äxample.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment',
                'expectedResult' =>'http://usr:pss@xn--xample-9ta.com:81/mypath/myfile.html?a=b&b[]=2&b[]=3#myfragment'

        ];

        // domains with special characters: should be converted to punycode
        yield
            'domain with special characters' => [
                'inputUrl' => 'https://www.grün-example.org',
                'expectedResult' =>'https://www.xn--grn-example-uhb.org'

        ];

        yield
            'domain with special characters and path' => [
                'inputUrl' => 'https://www.grün-example.org/a/bcd-efg/sfsfsfsfsf',
                'expectedResult' => 'https://www.xn--grn-example-uhb.org/a/bcd-efg/sfsfsfsfsf'

        ];
    }

    #[DataProvider('preprocessUrlsDataProvider')]
    #[Test]
    public function testPreprocessUrlReturnsCorrectString(string $inputUrl, string $expectedResult): void
    {
        $subject = $this->instantiateExternalLinktype();
        $method = new \ReflectionMethod($subject, 'preprocessUrl');
        $method->setAccessible(true);
        $result = $method->invokeArgs($subject, [$inputUrl]);
        self::assertEquals($expectedResult, $result);
    }

    private function instantiateExternalLinktype(): ExternalLinktype
    {
        $requestFactoryMock = $this->createMock(RequestFactory::class);
        $excludeLinkTargetMock = $this->createMock(ExcludeLinkTarget::class);
        $linkTargetCacheMock = $this->createMock(LinkTargetPersistentCache::class);

        return new ExternalLinktype(
            $requestFactoryMock,
            $excludeLinkTargetMock,
            $linkTargetCacheMock
        );
    }
}
