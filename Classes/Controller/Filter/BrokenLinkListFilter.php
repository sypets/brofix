<?php

declare(strict_types=1);

namespace Sypets\Brofix\Controller\Filter;

class BrokenLinkListFilter
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
}
