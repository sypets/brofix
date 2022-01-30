<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

class ManageExclusionsFilter
{
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
        return $this->excludeStoragePid ?: -1;
    }

    public function setExcludeStoragePid(int $excludeStoragePid): void
    {
        $this->excludeStoragePid = $excludeStoragePid;
    }
}
