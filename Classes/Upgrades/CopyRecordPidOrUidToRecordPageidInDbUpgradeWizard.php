<?php

declare(strict_types=1);
namespace Sypets\Brofix\Upgrades;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * @internal
 *
 * Since version 6.5.5 (where tx_brofix_broken_links.record_pageid was added)
 */
#[UpgradeWizard('brofix_copyPidToPageid')]
final class CopyRecordPidOrUidToRecordPageidInDbUpgradeWizard implements UpgradeWizardInterface
{
    private const TABLE = 'tx_brofix_broken_links';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    public function getTitle(): string
    {
        return 'Copy field record_uid or record_pid to record_pageid in tx_brofix_broken_links';
    }

    public function getDescription(): string
    {
        return '';
    }

    public function executeUpdate(): bool
    {
        // for table_name='pages', use record_uid => record_pageid
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'table_name',
                    $queryBuilder->createNamedParameter('pages', Connection::PARAM_STR)
                )
            )
            /**
             * "If a field should be set to the value of another field from the row, quoting must be turned off
             * and ->quoteIdentifier() and false have to be used:"
             * @see https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ApiOverview/Database/DoctrineDbal/QueryBuilder/Index.html
             */
            ->set('record_pageid', $queryBuilder->quoteIdentifier('record_uid'), false)
            //->set('bodytext', $queryBuilder->quoteIdentifier('header'), false)
            ->executeStatement();

        // for table_name!='pages', use record_pid => record_pageid
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->update(self::TABLE)
            ->where(
                $queryBuilder->expr()->neq(
                    'table_name',
                    $queryBuilder->createNamedParameter('pages', Connection::PARAM_STR)
                )
            )
            /**
             * "If a field should be set to the value of another field from the row, quoting must be turned off
             * and ->quoteIdentifier() and false have to be used:"
             * @https://docs.typo3.org/m/typo3/reference-coreapi/13.4/en-us/ApiOverview/Database/DoctrineDbal/QueryBuilder/Index.html
             */
            ->set('record_pageid', $queryBuilder->quoteIdentifier('record_pid'), false)
            ->executeStatement();

        return true;
    }

    /**
     * Update is necessary if there is at least one field tx_brofix_broken_lnks.record_pageid=0
     */
    public function updateNecessary(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        if ((int)$queryBuilder
            ->count('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'record_pageid',
                    $queryBuilder->createNamedParameter(
                        0,
                        Connection::PARAM_INT
                    )
                )
            )
            ->executeQuery()
            ->fetchOne()) {
            return true;
        }
        return false;
    }

    public function getPrerequisites(): array
    {
        return [];
    }
}
