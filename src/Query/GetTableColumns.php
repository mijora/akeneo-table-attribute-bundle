<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Query;

use Doctrine\DBAL\Connection;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;

/**
 * Columns of table attributes, which are the options of these attributes, in the order of the columns, with their
 * type, the configuration of their type and their labels.
 */
class GetTableColumns
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param string[] $attributeCodes
     *
     * @return array<string, array<int, array{code: string, type: ?string, config: array, labels: array<string, string>}>>
     *         columns indexed by attribute code in lowercase, attributes without columns and attributes that are not
     *         tables left out
     */
    public function forAttributeCodes(array $attributeCodes): array
    {
        if ([] === $attributeCodes) {
            return [];
        }

        $query = <<<SQL
        SELECT
            pim_catalog_attribute.code AS attribute_code,
            pim_catalog_attribute_option.code AS column_code,
            pim_catalog_attribute_option.type AS column_type,
            pim_catalog_attribute_option.type_config AS column_config,
            pim_catalog_attribute_option_value.locale_code AS label_locale,
            pim_catalog_attribute_option_value.value AS label
        FROM pim_catalog_attribute_option
            INNER JOIN pim_catalog_attribute ON pim_catalog_attribute_option.attribute_id = pim_catalog_attribute.id
            LEFT JOIN pim_catalog_attribute_option_value ON pim_catalog_attribute_option_value.option_id = pim_catalog_attribute_option.id
        WHERE pim_catalog_attribute.code IN (:attributeCodes) AND pim_catalog_attribute.attribute_type = :tableType
        ORDER BY pim_catalog_attribute_option.sort_order, pim_catalog_attribute_option.code
SQL;

        $rows = $this->connection->executeQuery(
            $query,
            [
                'attributeCodes' => array_map('strval', $attributeCodes),
                'tableType' => TableType::FLAGBIT_CATALOG_TABLE,
            ],
            ['attributeCodes' => Connection::PARAM_STR_ARRAY]
        )->fetchAllAssociative();

        $columns = [];
        foreach ($rows as $row) {
            $attributeCode = strtolower($row['attribute_code']);
            $columnCode = (string) $row['column_code'];
            if (!isset($columns[$attributeCode][$columnCode])) {
                $config = null === $row['column_config'] ? null : json_decode($row['column_config'], true);
                $columns[$attributeCode][$columnCode] = [
                    'code' => $columnCode,
                    'type' => $row['column_type'],
                    'config' => is_array($config) ? $config : [],
                    'labels' => [],
                ];
            }
            if (null !== $row['label_locale'] && null !== $row['label'] && '' !== $row['label']) {
                $columns[$attributeCode][$columnCode]['labels'][$row['label_locale']] = $row['label'];
            }
        }

        return array_map('array_values', $columns);
    }
}
