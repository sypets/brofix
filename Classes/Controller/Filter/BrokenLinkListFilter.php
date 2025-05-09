<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

use Sypets\Brofix\CheckLinks\LinkTargetResponse\LinkTargetResponse;
use Sypets\Brofix\Util\Arrayable;
use TYPO3\CMS\Backend\Module\ModuleData;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class BrokenLinkListFilter implements Arrayable
{
    /** @var string  */
    protected const KEY_UID = 'uid';
    /** @var string */
    protected const KEY_URL = 'url';
    /** @var string */
    protected const KEY_LINKTYPE = 'linktype';
    /** @var string */
    protected const KEY_URL_MATCH = 'urlMatch';

    protected const KEY_CHECK_STATUS = 'checkStatus';

    protected const KEY_USE_CACHE = 'useCache';

    protected const KEY_HOWTOTRAVERSE = 'howtotraverse';

    /** @var string */
    protected const LINK_TYPE_FILTER_DEFAULT = 'all';

    protected const URL_MATCH_DEFAULT = 'partial';

    protected const LINK_TYPE_DEFAULT = 'all';

    protected const CHECK_STATUS_DEFAULT = LinkTargetResponse::RESULT_BROKEN;

    /** @var int */
    public const PAGE_DEPTH_INFINITE = 999;

    /**
     * @var string
     */
    protected $uid_filtre = '';

    /** @var string */
    protected $linktype_filter = self::LINK_TYPE_DEFAULT;

    /**
     * @var string
     */
    protected $url_filtre = '';

    /** @var string  */
    protected $urlFilterMatch = self::URL_MATCH_DEFAULT;

    protected int $checkStatusFilter = self::CHECK_STATUS_DEFAULT;

    protected bool $useCache = true;

    protected bool $showUseCache = true;

    protected string $howtotraverse = 'pages';

    /**
     * @var string
     * @deprecated
     */
    protected $title_filter = '';

    public function __construct(
        string $uid = '',
        string $linkType = self::LINK_TYPE_DEFAULT,
        string $url = '',
        string $urlMatch = self::URL_MATCH_DEFAULT,
        int $checkStatus = self::CHECK_STATUS_DEFAULT,
        bool $useCache = true,
        string $howtotraverse = 'pages'
    ) {
        $this->uid_filtre = $uid;
        $this->linktype_filter = $linkType;
        $this->url_filtre = $url;
        $this->urlFilterMatch = $urlMatch;
        $this->checkStatusFilter = $checkStatus;
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
        $this->showUseCache = (bool)($extensionConfiguration->get('brofix')['useCacheForPageList'] ?? true);
        if ($this->showUseCache) {
            $this->useCache = $useCache;
        } else {
            $this->useCache = false;
        }
        $this->howtotraverse = $howtotraverse;
    }

    public static function getInstanceFromModuleData(ModuleData $moduleData): BrokenLinkListFilter
    {
        return new BrokenLinkListFilter(
            $moduleData->get('uid_searchFilter', ''),
            $moduleData->get('linktype_searchFilter', 'all'),
            $moduleData->get('url_searchFilter', ''),
            $moduleData->get('url_match_searchFilter', 'partial'),
            (int)$moduleData->get('check_status', (string)self::CHECK_STATUS_DEFAULT),
            (bool)$moduleData->get('useCache', 1),
            $moduleData->get('howtotraverse', 'pages'),
        );
    }

    public static function getInstanceFromArray(array $values): BrokenLinkListFilter
    {
        return new BrokenLinkListFilter(
            $values[self::KEY_UID] ?? '',
            $values[self::KEY_LINKTYPE] ?? self::LINK_TYPE_DEFAULT,
            $values[self::KEY_URL] ?? '',
            $values[self::KEY_URL_MATCH] ?? self::URL_MATCH_DEFAULT,
            $values[self::KEY_CHECK_STATUS] ?? self::CHECK_STATUS_DEFAULT,
            $values[self::KEY_USE_CACHE] ?? 1,
                $values[self::KEY_HOWTOTRAVERSE] ?? 'pages',
        );
    }

    public function toArray(): array
    {
        return [
            self::KEY_UID => $this->getUidFilter(),
            self::KEY_LINKTYPE => $this->getLinktypeFilter(),
            self::KEY_URL => $this->getUrlFilter(),
            self::KEY_URL_MATCH => $this->getUrlFilterMatch(),
            self::KEY_CHECK_STATUS => $this->getCheckStatusFilter(),
            self::KEY_USE_CACHE => $this->isUseCache(),
            self::KEY_HOWTOTRAVERSE => $this->getHowtotraverse(),
        ];
    }

    /**
     * Check if any filter is active
     *
     * @return bool
     */
    public function isFilter(): bool
    {
        if ($this->getUidFilter()
            || $this->getLinktypeFilter() !== self::LINK_TYPE_FILTER_DEFAULT
            || $this->getUrlFilter()
            || $this->getUrlFilterMatch() !== self::URL_MATCH_DEFAULT
        ) {
            return true;
        }
        return false;
    }

    public function getUidFilter(): string
    {
        return $this->uid_filtre;
    }

    public function setUidFilter(string $uid_filter): void
    {
        $this->uid_filtre = trim($uid_filter);
    }

    public function getLinktypeFilter(): string
    {
        return $this->linktype_filter;
    }

    public function setLinktypeFilter(string $linktype_filter): void
    {
        $this->linktype_filter = $linktype_filter;
    }

    protected function normalizeUrlFilter(string $urlFilter): string
    {
        // bugfix: previously, "all" was written to this filter as default
        if ($urlFilter === 'all') {
            $urlFilter = '';
        }
        return trim($urlFilter);
    }

    public function getUrlFilter(): string
    {
        // bugfix: previously, "all" was written to this filter as default
        $this->url_filtre = $this->normalizeUrlFilter($this->url_filtre);
        return $this->url_filtre;
    }

    public function setUrlFilter(string $url_filter): void
    {
        $this->url_filtre = $this->normalizeUrlFilter($url_filter);
    }

    /**
     * @return string
     */
    public function getUrlFilterMatch(): string
    {
        return $this->urlFilterMatch;
    }

    /**
     * @param string $urlFilterMatch
     */
    public function setUrlFilterMatch(string $urlFilterMatch): void
    {
        $this->urlFilterMatch = $urlFilterMatch;
    }

    public function getCheckStatusFilter(): int
    {
        return $this->checkStatusFilter;
    }

    public function setCheckStatusFilter(int $checkStatusFilter): void
    {
        $this->checkStatusFilter = $checkStatusFilter;
    }

    /** @deprecated */
    public function getTitleFilter(): string
    {
        return $this->title_filter;
    }

    /** @deprecated */
    public function setTitleFilter(string $title_filter): void
    {
        $this->title_filter = $title_filter;
    }

    public function isUseCache(): bool
    {
        if (!$this->showUseCache) {
            $this->useCache = false;
            return false;
        }
        return $this->useCache;
    }

    public function setUseCache(bool $useCache): void
    {
        if (!$this->showUseCache) {
            $this->useCache = false;
        } else {
            $this->useCache = $useCache;
        }
    }

    public function isShowUseCache(): bool
    {
        return $this->showUseCache;
    }

    public function setShowUseCache(bool $showUseCache): void
    {
        $this->showUseCache = $showUseCache;
    }

    public function getHowtotraverse(): string
    {
        return $this->howtotraverse;
    }

    public function setHowtotraverse(string $howtotraverse): void
    {
        $this->howtotraverse = $howtotraverse;
    }


}
