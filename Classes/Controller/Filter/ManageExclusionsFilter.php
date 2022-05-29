<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

use Sypets\Brofix\Util\Arrayable;

class ManageExclusionsFilter implements Arrayable
{
    protected const KEY_LINKTYPE = 'linkType';
    protected const KEY_URL = 'url';
    protected const KEY_REASON = 'reason';
    protected const KEY_STORAGE_PID = 'storagePid';

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

    public function __construct(string $linkType = '', string $url = '', string $reason = '', int $storagePid = 0)
    {
        $this->setExcludeLinkTypeFilter($linkType);
        $this->setExcludeUrlFilter($url);
        $this->setExcludeReasonFilter($reason);
        $this->setExcludeStoragePid($storagePid);
    }

    /**
     * @param array<mixed> $values
     * @return ManageExclusionsFilter
     */
    public static function getInstanceFromArray(array $values): ManageExclusionsFilter
    {
        return new ManageExclusionsFilter(
            $values[self::KEY_LINKTYPE] ?? '',
            $values[self::KEY_URL] ?? '',
            $values[self::KEY_REASON] ?? '',
            $values[self::KEY_STORAGE_PID] ?? '',
        );
    }

    /**
     * Get the instance as an array.
     *
     * @return array<mixed>
     */
    public function toArray()
    {
        return [
            self::KEY_LINKTYPE => $this->getExcludeLinkTypeFilter(),
            self::KEY_URL => $this->getExcludeUrlFilter(),
            self::KEY_REASON => $this->getExcludeReasonFilter(),
            self::KEY_STORAGE_PID => $this->getExcludeStoragePid(),
        ];
    }

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
