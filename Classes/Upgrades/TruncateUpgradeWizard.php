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
#[UpgradeWizard('brofix_truncateUpgradeWizard')]
final class TruncateUpgradeWizard implements UpgradeWizardInterface
{
    private const TABLES = ['tx_brofix_broken_links', 'tx_brofix_link_target_cache'];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Truncate tables tx_brofix_broken_links and tx_brofix_link_target_cache';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function executeUpdate(): bool
    {
        foreach (self::TABLES as $table) {
            $this->connectionPool
                ->getConnectionForTable($table)->truncate($table);
        }
        return true;
    }

    public function updateNecessary(): bool
    {
        foreach (self::TABLES as $table) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            if ((int)$queryBuilder
                ->count('*')
                ->from($table)
                ->where(
                    $queryBuilder->expr()->notLike(
                        'url_response',
                        $queryBuilder->createNamedParameter(
                            '%' . $queryBuilder->escapeLikeWildcards('{"status":') . '%',
                            Connection::PARAM_STR
                        )
                    )
                )
                ->executeQuery()
                ->fetchOne()) {
                return true;
            }
        }
        return false;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
