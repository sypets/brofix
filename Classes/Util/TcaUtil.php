<?php

declare(strict_types=1);
namespace Sypets\Brofix\Util;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtil
{
    /**
     * @param string $table
     * @param string $field
     * @param array<string,mixed> $row
     * @param array<mixed> $processedTca
     * @return array<mixed>
     */
    public static function getFlexformFieldsWithConfig(string $table, string $field, array $row, array $processedTca): array
    {
        if (!$row || !$processedTca) {
            return [];
        }
        $results = [];

        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $cleanedFlexformString = $flexFormTools->cleanFlexFormXML($table, $field, $row);
        $flexformArray = GeneralUtility::xml2array($cleanedFlexformString);

        if (!($flexformArray['data'] ?? false)) {
            return [];
        }

        self::traverseFlexformArray($processedTca['ds'], $results);
        foreach ($results as $flexformField => $values) {
            $value = self::traverseFlexformArrayForValues($flexformField, $flexformArray['data']);
            if ($value) {
                $results[$flexformField]['value'] = $value;
            }
        }

        return $results;
    }

    /**
     * @param string $fieldName
     * @param array<mixed> $data
     * @return string
     */
    public static function traverseFlexformArrayForValues(string $fieldName, array $data): string
    {
        foreach ($data as $key => $values) {
            if ($key === $fieldName) {
                return (string)$values['vDEF'];
            }
            if ($values && is_array($values)) {
                $value = self::traverseFlexformArrayForValues($fieldName, $values);
                if ($value) {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * @param array<mixed> $flex
     * @param array<mixed> $results
     */
    public static function traverseFlexformArray(array $flex, array &$results): void
    {
        if (!$flex) {
            return;
        }
        foreach ($flex as $key => $value) {
            if ($key === 'meta') {
                continue;
            }
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['config']['type'])) {
                $results[$key] = $value;
                continue;
            }
            self::traverseFlexformArray($flex[$key], $results);
        }
    }
}
