<?php
declare(strict_types=1);
namespace Sypets\Brofix\Controller\Pagination;

/**
 * In contrast to core funtionality, like ArrayPaginator, this does not store the entire array for performance
 * reasons. It just stores the basic information like the current page number (starting with 0) and number of
 * items per page.
 *
 * The intention is to let the database get only the items that are needed for one page.
 *
 */
class PaginateInfo
{
    public const PAGE_NUMBER_START = 0;
    public const ITEMS_PER_PAGE = 50;

    public function __construct(protected int $numberOfItems, protected int $currentPage = self::PAGE_NUMBER_START,
        protected int $itemsPerPage = self::ITEMS_PER_PAGE)
    {}

    public function getNumberOfItems(): int
    {
        return $this->numberOfItems;
    }

    public function getCurrentPageNumber(): ?int
    {
        return $this->currentPage;
    }

    public function getCurrentItemNumber(): ?int
    {
        $currentItemNumber = ($this->currentPage - self::PAGE_NUMBER_START) * $this->getItemsPerPage();
        return $currentItemNumber;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getPreviousPageNumber(): ?int
    {
        if ($this->currentPage > 1) {
            return $this->currentPage - 1;
        }
        return null;
    }

    public function getNextPageNumber(): ?int
    {
        if ($this->currentPage * $this->itemsPerPage > $this->numberOfItems) {
            return $this->currentPage + 1;
        }
        return null;
    }

    public function getFirstPageNumber(): int
    {
        return 0;
    }

    public function getLastPageNumber(): int
    {
        return $this->getNumberOfPages();
    }

    public function getNumberOfPages(): int
    {
        return (int) ($this->numberOfItems-1) / $this->itemsPerPage;
    }

    public function getStartRecordNumber(): int
    {
        return 0;
    }

    public function getEndRecordNumber(): int
    {
        return $this->numberOfItems-1;
    }

    public function getAllPageNumbers(): array
    {
       // not implemented
        return [];
    }


}
