<?php

declare(strict_types=1);

namespace Sypets\Brofix\Updates;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

class ErrorCodeUpdateWizard implements UpgradeWizardInterface
{
    /**
     * error types were changed in ExternalLinktype, adapt
     */
    protected const ERROR_TYPE_MAPPING = [
        'httpStatusCode' => 'http',
        'libcurlErrno' => 'curl',
        'libcurlError' => 'curl',
    ];

    protected const TABLES = [
        'tx_brofix_broken_links',
        'tx_brofix_link_target_cache',
    ];

    /**
     * Return the identifier for this wizard
     * This should be the same string as used in the ext_localconf class registration
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'brofix_errorCodeUpdateWizard';
    }

    /**
     * Return the speaking name of this wizard
     *
     * @return string
     */
    public function getTitle(): string
    {
        return 'Convert broken link records in database (adds error_code field)';
    }

    /**
     * Return the description for this wizard
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @param array<mixed> $response
     * @return string
     */
    protected function urlResponseToErrorCode(array $response): string
    {
        $errorType = $response['errorParams']['errorType'] ?? '';
        $errorCode = (int)($response['errorParams']['errno'] ?? 0);
        if (!$errorType) {
            return '';
        }
        foreach (self::ERROR_TYPE_MAPPING as $key => $value) {
            if ($errorType === $key) {
                $errorType = $value;
            }
        }
        return $errorType . '_' . $errorCode;
    }

    /**
     * @param string $table
     * @param array<mixed> $row
     * @return int
     */
    protected function updateRecord(string $table, array $row): int
    {
        $urlResponseString = $row['url_response'] ?? '';
        if (!$urlResponseString) {
            return 0;
        }
        $response = \json_decode($urlResponseString, true);
        if (!$response) {
            return 0;
        }
        $errorCode = $this->urlResponseToErrorCode($response);
        if (!$errorCode) {
            return 0;
        }
        $row['error_code'] = $errorCode;
        return (int)GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->update($table, $row, ['uid' => $row['uid']]);
    }

    /**
     * Execute the update
     *
     * Called when a wizard reports that an update is necessary
     *
     * @return bool
     */
    public function executeUpdate(): bool
    {
        $table = 'tx_brofix_broken_links';
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);

        // check if any record exists with field error_code empty
        $stmt = $queryBuilder
            ->select('uid', 'url_response', 'error_code')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $table . '.error_code',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->executeQuery();
        while ($row = $stmt->fetchAssociative()) {
            $this->updateRecord($table, $row);
        }

        $table = 'tx_brofix_link_target_cache';
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);
        $stmt = $queryBuilder
            ->select('uid', 'url_response', 'error_code')
            ->from($table)
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        $table . '.error_code',
                        $queryBuilder->createNamedParameter('')
                    ),
                    $queryBuilder->expr()->eq(
                        $table . '.check_status',
                        $queryBuilder->createNamedParameter(2, \PDO::PARAM_INT)
                    )
                )
            )
            ->executeQuery();
        while ($row = $stmt->fetchAssociative()) {
            $this->updateRecord($table, $row);
        }
        return true;
    }

    /**
     * Is an update necessary?
     *
     * Is used to determine whether a wizard needs to be run.
     * Check if data for migration exists.
     *
     * This returns true if at least record with empty error_code exists in list of broken links
     *
     * @return bool Whether an update is required (TRUE) or not (FALSE)
     */
    public function updateNecessary(): bool
    {
        $table = 'tx_brofix_broken_links';
        /**
         * @var ConnectionPool $connectionPool
         */
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);

        // check if any record exists with field error_code empty
        $count = (int)$queryBuilder
            ->count('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $table . '.error_code',
                    $queryBuilder->createNamedParameter('')
                )
            )
            ->executeQuery()
            ->fetchOne();
        if ($count > 0) {
            return true;
        }

        $table = 'tx_brofix_link_target_cache';
        $queryBuilder = $connectionPool->getQueryBuilderForTable($table);
        $count = (int)$queryBuilder
            ->count('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq(
                        $table . '.error_code',
                        $queryBuilder->createNamedParameter('')
                    ),
                    $queryBuilder->expr()->eq(
                        $table . '.check_status',
                        $queryBuilder->createNamedParameter(2, \PDO::PARAM_INT)
                    )
                )
            )
            ->executeQuery()
            ->fetchOne();
        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * Returns an array of class names of prerequisite classes
     *
     * This way a wizard can define dependencies like "database up-to-date" or
     * "reference index updated"
     *
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        // we need to make sure the new DB column was already added.
        return [
            DatabaseUpdatedPrerequisite::class,
        ];
    }
}
