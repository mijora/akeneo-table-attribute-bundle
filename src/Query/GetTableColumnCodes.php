<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Query;

use Doctrine\DBAL\Connection;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;

/**
 * Codes of the columns of table attributes, which are the options of these attributes, in the order of the columns.
 */
class GetTableColumnCodes
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @param string[] $attributeCodes
     *
     * @return array<string, string[]> column codes indexed by attribute code in lowercase, attributes without
     *                                 columns and attributes that are not tables left out
     */
    public function forAttributeCodes(array $attributeCodes): array
    {
        if ([] === $attributeCodes) {
            return [];
        }

        $query = <<<SQL
        SELECT pim_catalog_attribute.code AS attribute_code, pim_catalog_attribute_option.code AS column_code
        FROM pim_catalog_attribute_option INNER JOIN pim_catalog_attribute ON pim_catalog_attribute_option.attribute_id = pim_catalog_attribute.id
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

        $columnCodes = [];
        foreach ($rows as $row) {
            $columnCodes[strtolower($row['attribute_code'])][] = $row['column_code'];
        }

        return $columnCodes;
    }
}
