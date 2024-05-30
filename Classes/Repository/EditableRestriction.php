<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Sypets\Brofix\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

class EditableRestriction implements QueryRestrictionInterface
{
    protected const TABLE = 'tx_brofix_broken_links';

    /**
     * Specify which database fields the current user is allowed to edit
     *
     * @var array<string,array<string,bool>>
     */
    protected $allowedFields = [];

    /**
     * Specify which languages the current user is allowed to edit
     *
     * @var array<int,int>
     */
    protected $allowedLanguages = [];

    /**
     * Explicitly allow these types based on authMode (and explicitADmode for tt_content)
     *
     * Example:
     *
     * [
     *     'tt_content' => [
     *          'CType' => [
     *              'textmedia',
     *              ...
     *          ]
     *      ]
     * ]
     *
     * @var array<string,array<string,array<string>>>
     */
    protected $allowByFieldBasedOnAuthMode = [];

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @param array<string, array<string>> $searchFields array of 'table' => 'field1, field2'
     *   in which linkvalidator searches for broken links.
     * @param QueryBuilder $queryBuilder
     */
    public function __construct(array $searchFields, QueryBuilder $queryBuilder)
    {
        $this->allowedFields = $this->getAllowedFieldsForCurrentUser($searchFields);
        $this->allowedLanguages = $this->getAllowedLanguagesForCurrentUser();
        foreach ($searchFields as $table => $fields) {
            /** @todo We should look at behaviour of other tables besides tt_content. For tt_content.CType, if the
             *  value has not been activated for the BE group (or was explicitly denied), it is not possible to edit
             *  the record at all. However, if we try this with a non-tt_content record, the behaviour is different.
             *  Needs further research.
             */
            if ($table !== 'pages' && ($GLOBALS['TCA'][$table]['ctrl']['type'] ?? false)) {
                $type = $GLOBALS['TCA'][$table]['ctrl']['type'];

                // the value in the type field can depend on the value of a related record. We do not handle this
                // at the moment, we only ignore these kind of fields.
                if (strpos($type, ':') !== false) {
                    continue;
                }

                $values = $this->getAllowByFieldBasedOnAuthModeForCurrentUser($table, $type);
                if ($values !== null) {
                    $this->allowByFieldBasedOnAuthMode[$table][$type] = $values;
                }
            }
        }
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Gets all allowed language ids for current backend user
     *
     * @return array<int,int>
     */
    protected function getAllowedLanguagesForCurrentUser(): array
    {
        /**
         * Is string of comma-separated languages, e.g. "0,1"
         *
         * @var string $allowedLanguages
         */
        $allowedLanguages = (string)($GLOBALS['BE_USER']->groupData['allowed_languages'] ?? '');
        if ($allowedLanguages === '') {
            return [];
        }

        return array_map('intval', explode(',', $allowedLanguages));
    }

    /**
     * Based on authMode / explicitADmode.
     *
     * If a table contains a 'type' field, it is possible to explicitly allow or deny certain types. The behaviour
     * depends on the value of $GLOBALS['TCA'][$table]['columns'][$field]['config']['authMode'] (e.g. explicitAllow).
     * For tt_content tables, the behaviour depends on the value of $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'].
     *
     *
     * @param string $table
     * @param string $field
     * @return array<string>|null If null is passed, no auth checking for this $table / $field
     */
    protected function getAllowByFieldBasedOnAuthModeForCurrentUser(string $table, string $field): ?array
    {
        $allowDenyOptions = [];
        $fieldConfig = $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [];
        if (!$fieldConfig) {
            return null;
        }

        $authMode = $this->getAuthMode($table, $field);
        if (!$authMode) {
            return null;
        }

        // Check for items
        if ($fieldConfig['type'] === 'select' && is_array($fieldConfig['items'] ?? false)) {
            foreach ($fieldConfig['items'] as $iVal) {
                $itemIdentifier = (string)$iVal['value'];
                if ($itemIdentifier === '--div--') {
                    continue;
                }
                if ($GLOBALS['BE_USER']->checkAuthMode($table, $field, $itemIdentifier)) {
                    $allowDenyOptions[] = $itemIdentifier;
                }
            }
        }
        return $allowDenyOptions;
    }

    /**
     * @todo in v12, this changes
     * @return string Return empty string, if no authMode
     */
    protected function getAuthMode(string $table, string $type): string
    {
        if ($type === 'CType') {
            /**
             * from documentation about explicitADmode:
             * "since v12: The handling of $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] has been changed and is
             * now set using explicitAllow. Extensions should not assume this global array key is set anymore as of
             * TYPO3 Core v12. Extensions that need to stay compatible with v11 and v12 should instead use:
             * $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] ?? 'explicitAllow'."
             */
            return $GLOBALS['TYPO3_CONF_VARS']['BE']['explicitADmode'] ?? 'explicitAllow';
        }

        $authMode = $GLOBALS['TCA'][$table]['columns'][$type]['config']['authMode'] ?? '';
        if ($authMode && $authMode != 'explicitAllow') {
            /** since TYPO3 v12, only explicitAllow is supported
             * from documentation:
             * "The only valid value for TCA config option authMode is now explicitAllow. The values explicitDeny and
             * individual are obsolete and no longer evaluated."
             */
            $authMode = '';
        }
        return $authMode;
    }

    /**
     * Get allowed table / fieldnames for current backend user.
     * Only consider table / fields in $searchFields
     *
     * @param array<string,array<string>> $searchFields array of 'table' => ['field1, field2', ....]
     *   in which linkvalidator searches for broken links
     * @return array<string,array<string,bool>>
     */
    protected function getAllowedFieldsForCurrentUser(array $searchFields = []): array
    {
        if (!$searchFields) {
            return [];
        }

        $allowedFields = [];

        foreach ($searchFields as $table => $fieldList) {
            if (!$GLOBALS['BE_USER']->isAdmin() && !$GLOBALS['BE_USER']->check('tables_modify', $table)) {
                // table not allowed
                continue;
            }
            foreach ($fieldList as $field) {
                $isExcludeField = $GLOBALS['TCA'][$table]['columns'][$field]['exclude'] ?? false;
                if (!$GLOBALS['BE_USER']->isAdmin()
                    && $isExcludeField
                    && !$GLOBALS['BE_USER']->check('non_exclude_fields', $table . ':' . $field)) {
                    continue;
                }
                $allowedFields[$table][$field] = true;
            }
        }
        return $allowedFields;
    }

    /**
     * @param array<mixed> $queriedTables
     * @param ExpressionBuilder $expressionBuilder
     * @return CompositeExpression
     */
    public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
    {
        $constraints = [];

        if ($this->allowedFields) {
            $constraints = [
                $expressionBuilder->or(
                    // broken link is in page and page is editable
                    $expressionBuilder->and(
                        $expressionBuilder->eq(
                            self::TABLE . '.table_name',
                            $this->queryBuilder->createNamedParameter('pages')
                        ),
                        QueryHelper::stripLogicalOperatorPrefix($GLOBALS['BE_USER']->getPagePermsClause(Permission::PAGE_EDIT))
                    ),
                    // OR broken link is in content and content is editable
                    $expressionBuilder->and(
                        $expressionBuilder->neq(
                            self::TABLE . '.table_name',
                            $this->queryBuilder->createNamedParameter('pages')
                        ),
                        QueryHelper::stripLogicalOperatorPrefix($GLOBALS['BE_USER']->getPagePermsClause(Permission::CONTENT_EDIT))
                    )
                )
            ];

            // check if fields are editable
            $additionalWhere = [];
            foreach ($this->allowedFields as $table => $fields) {
                foreach ($fields as $field => $value) {
                    $additionalWhere[] = $expressionBuilder->and(
                        $expressionBuilder->eq(
                            self::TABLE . '.table_name',
                            $this->queryBuilder->createNamedParameter($table)
                        ),
                        $expressionBuilder->eq(
                            self::TABLE . '.field',
                            $this->queryBuilder->createNamedParameter($field)
                        )
                    );
                }
            }
            if ($additionalWhere) {
                $constraints[] = $expressionBuilder->or(...$additionalWhere);
            }
        } else {
            // add a constraint that will always return zero records because there are NO allowed fields
            $constraints[] = $expressionBuilder->isNull(self::TABLE . '.table_name');
        }

        foreach ($this->allowByFieldBasedOnAuthMode as $table => $field) {
            $additionalWhere = [];
            $additionalWhere[] = $expressionBuilder->and(
                $expressionBuilder->eq(
                    self::TABLE . '.table_name',
                    $this->queryBuilder->createNamedParameter($table)
                ),
                $expressionBuilder->in(
                    self::TABLE . '.element_type',
                    $this->queryBuilder->createNamedParameter(
                        array_unique(current($field)),
                        Connection::PARAM_STR_ARRAY
                    )
                )
            );
            $additionalWhere[] = $expressionBuilder->eq(
                self::TABLE . '.element_type',
                $this->queryBuilder->createNamedParameter('')
            );
            $additionalWhere[] = $expressionBuilder->neq(
                self::TABLE . '.table_name',
                $this->queryBuilder->createNamedParameter($table)
            );
            $constraints[] = $expressionBuilder->or(...$additionalWhere);
        }

        if ($this->allowedLanguages) {
            $additionalWhere = [];
            foreach ($this->allowedLanguages as $langId) {
                $additionalWhere[] = $expressionBuilder->or(
                    $expressionBuilder->eq(
                        self::TABLE . '.language',
                        $this->queryBuilder->createNamedParameter($langId, \PDO::PARAM_INT)
                    ),
                    $expressionBuilder->eq(
                        self::TABLE . '.language',
                        $this->queryBuilder->createNamedParameter(-1, \PDO::PARAM_INT)
                    )
                );
            }
            $constraints[] = $expressionBuilder->or(...$additionalWhere);
        }
        // If allowed languages is empty: all languages are allowed, so no constraint in this case

        return $expressionBuilder->and(...$constraints);
    }
}
