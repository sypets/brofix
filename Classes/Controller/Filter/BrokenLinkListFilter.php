<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

class BrokenLinkListFilter
{
    public const VIEW_MODE_MIN = 'view_table_min';
    public const VIEW_MODE_COMPLEX = 'view_table_complex';

    /**
     * @var string
     */
    protected $uid_filtre = '';

    /** @var string */
    protected $linktype_filter = 'all';

    /**
     * @var string
     */
    protected $url_filtre = '';

    /** @var string  */
    protected $urlFilterMatch = 'partial';

    /**
     * @var string
     * @deprecated
     */
    protected $title_filter = '';

    /** @var string */
    protected $viewMode = self::VIEW_MODE_MIN;

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

    /**
     * @return string
     */
    public function getViewMode(): string
    {
        return $this->viewMode;
    }

    /**
     * @param string $viewMode
     */
    public function setViewMode(string $viewMode): void
    {
        $this->viewMode = $viewMode;
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
