<?php

declare(strict_types=1);

namespace Sypets\Brofix\Filter;

class Filter
{
    /**
     * @var string
     */
    protected $url_filtre = '';

    /**
     * @var string
     */
    protected $uid_filtre = '';

    /**
     * @var string
     */
    protected $title_filter = '';

    /**
     * @var string
     */
    protected $excludeLinkType_filter = '';

    /**
     * @var string
     */
    protected $excludeUrl_filter = '';

    /**
     * @var string
     */
    protected $excludeReason_filter = '';

    /**
     * @var int
     */
    protected $excludeStoragePid;

    // Getters and Setters

    public function getUrlFilter(): string
    {
        return $this->url_filtre;
    }
    public function getUidFilter(): string
    {
        return $this->uid_filtre;
    }

    public function getTitleFilter(): string
    {
        return $this->title_filter;
    }

    public function getExcludeLinkTypeFilter(): string
    {
        return $this->excludeLinkType_filter;
    }

    public function getExcludeUrlFilter(): string
    {
        return $this->excludeUrl_filter;
    }

    public function getExcludeReasonFilter(): string
    {
        return $this->excludeReason_filter;
    }

    public function setUrlFilter(string $url_filter): void
    {
        $this->url_filtre = $url_filter;
    }

    public function setUidFilter(string $uid_filter): void
    {
        $this->uid_filtre = $uid_filter;
    }

    public function setTitleFilter(string $title_filter): void
    {
        $this->title_filter = $title_filter;
    }

    public function setExcludeLinkTypeFilter(string $excludeLinkType_filter): void
    {
        $this->excludeLinkType_filter = $excludeLinkType_filter;
    }

    public function setExcludeUrlFilter(string $excludeUrl_filter): void
    {
        $this->excludeUrl_filter = $excludeUrl_filter;
    }

    public function setExcludeReasonFilter(string $excludeReason_filter): void
    {
        $this->excludeReason_filter = $excludeReason_filter;
    }

    public function getExcludeStoragePid(): int
    {
        return $this->excludeStoragePid;
    }

    public function setExcludeStoragePid(int $excludeStoragePid): void
    {
        $this->excludeStoragePid = $excludeStoragePid;
    }
}
