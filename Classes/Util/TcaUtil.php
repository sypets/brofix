<?php

declare(strict_types=1);
namespace Sypets\Brofix\Util;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TcaUtil
{
    public function __construct(protected TcaSchemaFactory $tcaSchemaFactory, protected Typo3Version $typo3Version)
    {
    }

    /**
     * @param string $table
     * @param string $field
     * @param array<string,mixed> $row
     * @param array<mixed> $processedTca
     * @return array<mixed>
     */
    public function getFlexformFieldsWithConfig(string $table, string $field, array $row, array $processedTca): array
    {
        if (!$row || !$processedTca) {
            return [];
        }
        $results = [];

        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        if ($this->typo3Version->getMajorVersion() >= 14) {
            if (!$this->tcaSchemaFactory->has($table)) {
                throw new \RuntimeException('No tcaSchema available for table ' . $table, 8450653819);
            }
            $schema = $this->tcaSchemaFactory->get($table);
            $cleanedFlexformString = $flexFormTools->cleanFlexFormXML($table, $field, $row, $schema);
        } else {
            $cleanedFlexformString = $flexFormTools->cleanFlexFormXML($table, $field, $row);
        }
        $flexformArray = GeneralUtility::xml2array($cleanedFlexformString);

        if (!($flexformArray['data'] ?? false)) {
            return [];
        }

        $this->traverseFlexformArray($processedTca['ds'], $results);
        foreach ($results as $flexformField => $values) {
            $value = $this->traverseFlexformArrayForValues($flexformField, $flexformArray['data']);
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
    public function traverseFlexformArrayForValues(string $fieldName, array $data): string
    {
        foreach ($data as $key => $values) {
            if ($key === $fieldName) {
                return (string)$values['vDEF'];
            }
            if ($values && is_array($values)) {
                $value = $this->traverseFlexformArrayForValues($fieldName, $values);
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
    public function traverseFlexformArray(array $flex, array &$results): void
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
            $this->traverseFlexformArray($flex[$key], $results);
        }
    }
}
