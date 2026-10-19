<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Component\Product\Factory\NonExistentValuesFilter;

use Akeneo\Pim\Enrichment\Component\Product\Factory\NonExistentValuesFilter\NonExistentValuesFilter;
use Akeneo\Pim\Enrichment\Component\Product\Factory\NonExistentValuesFilter\OnGoingFilteredRawValues;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumnCodes;

/**
 * Brings the rows of the table values read from the database to the current columns of their attribute, the way
 * the PIM drops deleted options from select values: the keys of deleted columns are removed and the columns added
 * since the row was saved are set to null. Everything reading products (API, exports, product edit form, saving a
 * product) then gets the current structure, and a product saved for any reason stores it.
 *
 * Rows that already have the columns as keys are kept as they are, and a value with such rows only is kept as it
 * was stored, so reading a product does not change the value unless the columns changed.
 */
final class TableColumnsValuesFilter implements NonExistentValuesFilter
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

    public function __construct(private GetTableColumnCodes $getTableColumnCodes)
    {
    }

    public function filter(OnGoingFilteredRawValues $onGoingFilteredRawValues): OnGoingFilteredRawValues
    {
        $tableValues = $onGoingFilteredRawValues->notFilteredValuesOfTypes(TableType::FLAGBIT_CATALOG_TABLE);
        if (empty($tableValues)) {
            return $onGoingFilteredRawValues;
        }

        $columnCodes = $this->getTableColumnCodes->forAttributeCodes(array_map('strval', array_keys($tableValues)));

        // The values of all the products are returned, as they replace the ones read from the database.
        $filteredValues = [];
        foreach ($tableValues as $attributeCode => $productValueCollection) {
            $columns = $columnCodes[strtolower((string) $attributeCode)] ?? [];
            foreach ($productValueCollection as $productValues) {
                foreach ($productValues['values'] as $channel => $channelValues) {
                    foreach ($channelValues as $locale => $value) {
                        $productValues['values'][$channel][$locale] = $this->reshapeValue($value, $columns);
                    }
                }

                $filteredValues[TableType::FLAGBIT_CATALOG_TABLE][$attributeCode][] = $productValues;
            }
        }

        return $onGoingFilteredRawValues->addFilteredValuesIndexedByType($filteredValues);
    }

    /**
     * @param mixed    $value   rows encoded in JSON, as stored
     * @param string[] $columns column codes in their order
     *
     * @return mixed the value as it was when it is not a list of rows, or when the attribute has no columns
     */
    private function reshapeValue($value, array $columns)
    {
        if (!is_string($value) || [] === $columns) {
            return $value;
        }

        // Decoded to objects, so that column codes made of digits stay keys of objects when encoded again.
        $rows = json_decode($value);
        if (!is_array($rows)) {
            return $value;
        }

        $reshaped = false;
        foreach ($rows as $index => $row) {
            if (!$row instanceof \stdClass) {
                return $value;
            }

            $cells = get_object_vars($row);
            if ($this->hasColumnsAsKeys($cells, $columns)) {
                continue;
            }

            $rows[$index] = $this->reshapeRow($cells, $columns);
            $reshaped = true;
        }

        if (!$reshaped) {
            return $value;
        }

        $encoded = json_encode($rows, self::JSON_FLAGS);

        return false === $encoded ? $value : $encoded;
    }

    private function hasColumnsAsKeys(array $cells, array $columns): bool
    {
        if (count($cells) !== count($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            if (!array_key_exists($column, $cells)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A key in another case than its column, which the PIM does not tell apart in codes, keeps its cell.
     */
    private function reshapeRow(array $cells, array $columns): \stdClass
    {
        $cellsByLowercaseKey = [];
        foreach ($cells as $key => $cell) {
            $cellsByLowercaseKey[strtolower((string) $key)] ??= $cell;
        }

        $row = new \stdClass();
        foreach ($columns as $column) {
            $row->{$column} = array_key_exists($column, $cells)
                ? $cells[$column]
                : ($cellsByLowercaseKey[strtolower($column)] ?? null);
        }

        return $row;
    }
}
