<?php
declare(strict_types=1);
namespace Sypets\Brofix\Util;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtil
{
    public static function getFlexformFieldsWithConfig(string $table, string $field, array $row, array $processedTca): array
    {
        $results = [];

        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        $flexFormTools->cleanFlexFormXML($table, $field, $row);
        $flexformArray = $flexFormTools->cleanFlexFormXML;

        self::traverseFlexformArray($processedTca['ds'], $results);
        foreach ($results as $field => $values) {
            $value = self::traverseFlexformArrayForValues($field, $flexformArray['data']);
            if ($value) {
                $results[$field]['value'] = $value;
            }
        }

        return $results;
    }

    public static function traverseFlexformArrayForValues(string $fieldName, array $data): string
    {
        foreach ($data as $key => $values) {
            if ($key === $fieldName) {
                return (string) $values['vDEF'];
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
