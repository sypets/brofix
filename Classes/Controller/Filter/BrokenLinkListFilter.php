<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

use Sypets\Brofix\Util\Arrayable;
use TYPO3\CMS\Backend\Module\ModuleData;

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

    /** @var string */
    protected const LINK_TYPE_FILTER_DEFAULT = 'all';

    protected const URL_MATCH_DEFAULT = 'partial';

    protected const LINK_TYPE_DEFAULT = 'all';

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

    /**
     * @var string
     * @deprecated
     */
    protected $title_filter = '';

    public function __construct(
        string $uid = '',
        string $linkType = self::LINK_TYPE_DEFAULT,
        string $url = '',
        string $urlMatch = self::URL_MATCH_DEFAULT
    ) {
        $this->uid_filtre = $uid;
        $this->linktype_filter = $linkType;
        $this->url_filtre = $url;
        $this->urlFilterMatch = $urlMatch;
    }

    public static function getInstanceFromModuleData(ModuleData $moduleData): BrokenLinkListFilter
    {
        return new BrokenLinkListFilter(
            $moduleData->get('uid_searchFilter', ''),
            $moduleData->get('linktype_searchFilter', 'all'),
            $moduleData->get('url_searchFilter', ''),
            $moduleData->get('url_match_searchFilter', 'partial')
        );
    }

    public static function getInstanceFromArray(array $values): BrokenLinkListFilter
    {
        return new BrokenLinkListFilter(
            $values[self::KEY_UID] ?? '',
            $values[self::KEY_LINKTYPE] ?? self::LINK_TYPE_DEFAULT,
            $values[self::KEY_URL] ?? '',
            $values[self::KEY_URL_MATCH] ?? self::URL_MATCH_DEFAULT
        );
    }

    public function toArray(): array
    {
        return [
            self::KEY_UID => $this->getUidFilter(),
            self::KEY_LINKTYPE => $this->getLinktypeFilter(),
            self::KEY_URL => $this->getUrlFilter(),
            self::KEY_URL_MATCH => $this->getUrlFilterMatch(),
        ];
    }

    /**
     * Check if any filter is active
     *
     * - we do not include the View mode in this check since this will
     *   no affect the number of results
     *
     * @return bool
     */
    public function hasConstraintsForNumberOfResults(): bool
    {
        if ($this->getUidFilter()
            || $this->getLinktypeFilter() !== self::LINK_TYPE_FILTER_DEFAULT
            || $this->getUrlFilter()
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

    public function getUrlFilter(): string
    {
        return $this->url_filtre;
    }

    public function setUrlFilter(string $url_filter): void
    {
        $this->url_filtre = trim($url_filter);
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
}
