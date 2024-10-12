<?php

declare(strict_types=1);
namespace Sypets\Brofix\Upgrades;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * @internal
 */
#[UpgradeWizard('brofix_urlHashUpgradeWizard')]
final class UrlHashUpgradeWizard implements UpgradeWizardInterface
{
    private const TABLE_NAME = 'tx_brofix_broken_links';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Set url_hash in tx_brofix_broken_links (SHA1 of url)';
    }

    public function getDescription(): string
    {
        return 'For performance reasons, an index is added. Because url is too long, we add the index for the url_hash. For this reason url_hash must be populated';
    }

    public function executeUpdate(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $queryResult = $queryBuilder
            ->select('uid', 'url')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'url_hash',
                    $queryBuilder->createNamedParameter(
                        ''
                    )
                )
            )
            ->executeQuery();
        while ($row = $queryResult->fetchAssociative()) {
            $uid = $row['uid'];
            $url = $row['url'];
            $hash = sha1($url);
            $this->updateRow($uid, $hash);
        }
        return true;
    }

    protected function updateRow(int $uid, string $urlHash): void
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->update(
                self::TABLE_NAME,
                [
                    'url_hash' => $urlHash,
                ],
                ['uid' => $uid],
                //[Connection::PARAM_INT],
            );
    }

    public function updateNecessary(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);
        $count = (int)($queryBuilder
            ->count('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'url_hash',
                    $queryBuilder->createNamedParameter(
                        ''
                    )
                )
            )
            ->executeQuery()
            ->fetchOne());

        return $count > 0;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
