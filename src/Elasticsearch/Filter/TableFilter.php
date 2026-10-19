<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Elasticsearch\Filter;

use Akeneo\Pim\Enrichment\Bundle\Elasticsearch\Filter\Attribute\AbstractAttributeFilter;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\Operators;
use Akeneo\Pim\Enrichment\Component\Product\Validator\ElasticsearchFilterValidator;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Tool\Component\Elasticsearch\QueryString;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Flagbit\Bundle\TableAttributeBundle\Component\Product\Normalizer\Indexing\TableValueNormalizer;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;

/**
 * Filters products and product models on the cells of their table values, indexed by TableValueNormalizer.
 *
 * - On one column, the field being "<attribute>.<column>" (product grid) or the attribute with the value
 *   {"column": "<column>", "value": <value>} (API): a product matches when one of its rows matches. With "!=",
 *   "DOES NOT CONTAIN" and "NOT IN", it matches when it has values in the column and none of its rows matches the
 *   value; with "EMPTY", when none of its rows has a value in the column.
 * - On the whole table, the field being the attribute without value: "EMPTY" and "NOT EMPTY" on the rows.
 * - On several columns of the same row, with the "SAME ROW" operator and as value the list of the conditions
 *   [{"column": "<column>", "operator": "<operator>", "value": <value>}]: a product matches when one of its rows
 *   matches all the conditions, "!=", "DOES NOT CONTAIN", "NOT IN" and "EMPTY" applying to the cell of the row.
 *
 * Text cells are compared ignoring the case.
 */
final class TableFilter extends AbstractAttributeFilter
{
    public const SAME_ROW = 'SAME ROW';

    private const TEXT_OPERATORS = [
        Operators::EQUALS,
        Operators::NOT_EQUAL,
        Operators::CONTAINS,
        Operators::DOES_NOT_CONTAIN,
        Operators::STARTS_WITH,
        Operators::ENDS_WITH,
        Operators::IN_LIST,
        Operators::NOT_IN_LIST,
        Operators::IS_EMPTY,
        Operators::IS_NOT_EMPTY,
    ];

    private const NUMBER_OPERATORS = [
        Operators::EQUALS,
        Operators::NOT_EQUAL,
        Operators::LOWER_THAN,
        Operators::LOWER_OR_EQUAL_THAN,
        Operators::GREATER_THAN,
        Operators::GREATER_OR_EQUAL_THAN,
        Operators::IS_EMPTY,
        Operators::IS_NOT_EMPTY,
    ];

    private const SELECT_OPERATORS = [
        Operators::IN_LIST,
        Operators::NOT_IN_LIST,
        Operators::IS_EMPTY,
        Operators::IS_NOT_EMPTY,
    ];

    /**
     * Operators by type of the columns that can be filtered on
     */
    public const COLUMN_OPERATORS = [
        'text' => self::TEXT_OPERATORS,
        'number' => self::NUMBER_OPERATORS,
        'select' => self::SELECT_OPERATORS,
        'select_from_attribute' => self::SELECT_OPERATORS,
    ];

    /**
     * Operators that match the rows not matching their value
     */
    private const NEGATIVE_OPERATORS = [
        Operators::NOT_EQUAL,
        Operators::DOES_NOT_CONTAIN,
        Operators::NOT_IN_LIST,
    ];

    public function __construct(ElasticsearchFilterValidator $filterValidator, private GetTableColumns $getTableColumns)
    {
        $this->filterValidator = $filterValidator;
        $this->supportedAttributeTypes = [TableType::FLAGBIT_CATALOG_TABLE];
        $this->supportedOperators = array_values(array_unique(array_merge(
            self::TEXT_OPERATORS,
            self::NUMBER_OPERATORS,
            self::SELECT_OPERATORS,
            [self::SAME_ROW]
        )));
    }

    public function addAttributeFilter(
        AttributeInterface $attribute,
        $operator,
        $value,
        $locale = null,
        $channel = null,
        $options = []
    ) {
        if (null === $this->searchQueryBuilder) {
            throw new \LogicException('The search query builder is not initialized in the filter.');
        }

        $this->checkLocaleAndChannel($attribute, $locale, $channel);

        $rowsPath = $this->getRowsPath($attribute, $locale, $channel);
        $fieldColumn = $this->getFieldColumn($attribute, $options['field'] ?? null);

        if (null === $fieldColumn && self::SAME_ROW === $operator) {
            $this->searchQueryBuilder->addFilter(
                $this->nested($rowsPath, $this->sameRowQuery($attribute, $rowsPath, $value))
            );

            return $this;
        }

        if (self::SAME_ROW === $operator) {
            throw InvalidPropertyException::expected(
                sprintf('The "%s" operator is used on the attribute "%s", not on one of its columns.', $operator, $attribute->getCode()),
                static::class
            );
        }

        if (null === $fieldColumn && in_array($operator, [Operators::IS_EMPTY, Operators::IS_NOT_EMPTY]) && !is_array($value)) {
            $this->addRowsFilter($attribute, $rowsPath, $operator);

            return $this;
        }

        [$columnCode, $value] = null === $fieldColumn ? $this->getColumnAndValue($attribute, $value) : [$fieldColumn, $value];
        [$cellPath, $cellClause] = $this->getCellCondition($attribute, $rowsPath, $columnCode, $operator, $value);

        if (Operators::IS_EMPTY === $operator) {
            $this->searchQueryBuilder->addMustNot($this->nested($rowsPath, $this->exists($cellPath)));
            $this->searchQueryBuilder->addFilter($this->attributeInEntity($attribute));
        } elseif (in_array($operator, self::NEGATIVE_OPERATORS)) {
            $this->searchQueryBuilder->addMustNot($this->nested($rowsPath, $cellClause));
            $this->searchQueryBuilder->addFilter($this->nested($rowsPath, $this->exists($cellPath)));
        } else {
            $this->searchQueryBuilder->addFilter($this->nested($rowsPath, $cellClause));
        }

        return $this;
    }

    private function addRowsFilter(AttributeInterface $attribute, string $rowsPath, string $operator): void
    {
        $anyRow = $this->nested($rowsPath, ['match_all' => new \stdClass()]);

        if (Operators::IS_NOT_EMPTY === $operator) {
            $this->searchQueryBuilder->addFilter($anyRow);

            return;
        }

        $this->searchQueryBuilder->addMustNot($anyRow);
        $this->searchQueryBuilder->addFilter($this->attributeInEntity($attribute));
    }

    /**
     * Query of the rows matching all the conditions of a "SAME ROW" filter
     */
    private function sameRowQuery(AttributeInterface $attribute, string $rowsPath, $conditions): array
    {
        $expectedStructure = 'a list of conditions {"column": <column code>, "operator": <operator>, "value": <value>} is expected';
        if (!is_array($conditions) || [] === $conditions || !array_is_list($conditions)) {
            throw InvalidPropertyTypeException::validArrayStructureExpected(
                $attribute->getCode(),
                $expectedStructure,
                static::class,
                is_array($conditions) ? $conditions : [$conditions]
            );
        }

        $filter = [];
        $mustNot = [];
        foreach ($conditions as $condition) {
            if (!is_array($condition) || !isset($condition['column'], $condition['operator']) || !is_string($condition['operator'])) {
                throw InvalidPropertyTypeException::validArrayStructureExpected(
                    $attribute->getCode(),
                    $expectedStructure,
                    static::class,
                    $conditions
                );
            }

            $operator = $condition['operator'];
            [$cellPath, $cellClause] = $this->getCellCondition(
                $attribute,
                $rowsPath,
                $condition['column'],
                $operator,
                $condition['value'] ?? null
            );

            if (Operators::IS_EMPTY === $operator) {
                $mustNot[] = $this->exists($cellPath);
            } elseif (in_array($operator, self::NEGATIVE_OPERATORS)) {
                $filter[] = $this->exists($cellPath);
                $mustNot[] = $cellClause;
            } else {
                $filter[] = $cellClause;
            }
        }

        return ['bool' => array_filter(['filter' => $filter, 'must_not' => $mustNot])];
    }

    /**
     * Path of the cell of the column and query of the cells matching the value, or having a value for "EMPTY" and
     * "NOT EMPTY", with the values checked against the type of the column.
     *
     * @return array{0: string, 1: array}
     */
    private function getCellCondition(AttributeInterface $attribute, string $rowsPath, $columnCode, string $operator, $value): array
    {
        $column = $this->getColumn($attribute, $columnCode);
        $operators = self::COLUMN_OPERATORS[$column['type']] ?? [];
        if ([] === $operators) {
            throw InvalidPropertyException::validEntityCodeExpected(
                $attribute->getCode(),
                'column code',
                sprintf('The columns of type "%s" can not be filtered on', $column['type']),
                static::class,
                $column['code']
            );
        }
        if (!in_array($operator, $operators, true)) {
            throw InvalidPropertyException::expected(
                sprintf(
                    'The operator "%s" can not be used on the column "%s" of the attribute "%s", use one of: %s.',
                    $operator,
                    $column['code'],
                    $attribute->getCode(),
                    implode(', ', $operators)
                ),
                static::class
            );
        }

        $isNumber = 'number' === $column['type'];
        $cellPath = sprintf(
            '%s.%s.%s',
            $rowsPath,
            $isNumber ? TableValueNormalizer::NUMBER : TableValueNormalizer::KEYWORD,
            $column['code']
        );

        if (in_array($operator, [Operators::IS_EMPTY, Operators::IS_NOT_EMPTY])) {
            return [$cellPath, $this->exists($cellPath)];
        }

        $property = sprintf('%s.%s', $attribute->getCode(), $column['code']);
        if (in_array($operator, [Operators::IN_LIST, Operators::NOT_IN_LIST])) {
            return [$cellPath, ['terms' => [$cellPath => $this->getKeywords($property, $value)]]];
        }

        if ($isNumber) {
            if (!is_numeric($value)) {
                throw InvalidPropertyTypeException::numericExpected($property, static::class, $value);
            }

            $number = (float) $value;
            $clause = match ($operator) {
                Operators::LOWER_THAN => ['range' => [$cellPath => ['lt' => $number]]],
                Operators::LOWER_OR_EQUAL_THAN => ['range' => [$cellPath => ['lte' => $number]]],
                Operators::GREATER_THAN => ['range' => [$cellPath => ['gt' => $number]]],
                Operators::GREATER_OR_EQUAL_THAN => ['range' => [$cellPath => ['gte' => $number]]],
                default => ['term' => [$cellPath => $number]],
            };

            return [$cellPath, $clause];
        }

        $keyword = $this->getKeywords($property, [$value])[0];
        $clause = match ($operator) {
            Operators::CONTAINS, Operators::DOES_NOT_CONTAIN => $this->queryString($cellPath, '*' . QueryString::escapeValue($keyword) . '*'),
            Operators::STARTS_WITH => $this->queryString($cellPath, QueryString::escapeValue($keyword) . '*'),
            Operators::ENDS_WITH => $this->queryString($cellPath, '*' . QueryString::escapeValue($keyword)),
            default => ['term' => [$cellPath => $keyword]],
        };

        return [$cellPath, $clause];
    }

    /**
     * @return array{code: string, type: ?string}
     */
    private function getColumn(AttributeInterface $attribute, $columnCode): array
    {
        if (!is_string($columnCode) && !is_int($columnCode)) {
            throw InvalidPropertyTypeException::stringExpected($attribute->getCode(), static::class, $columnCode);
        }

        $columns = $this->getTableColumns->forAttributeCodes([$attribute->getCode()])[strtolower($attribute->getCode())] ?? [];
        $columnCode = (string) $columnCode;
        $sameCaseInsensitive = null;
        foreach ($columns as $column) {
            if ($column['code'] === $columnCode) {
                return $column;
            }
            if (null === $sameCaseInsensitive && strtolower($column['code']) === strtolower($columnCode)) {
                $sameCaseInsensitive = $column;
            }
        }

        if (null !== $sameCaseInsensitive) {
            return $sameCaseInsensitive;
        }

        throw InvalidPropertyException::validEntityCodeExpected(
            $attribute->getCode(),
            'column code',
            'The column does not exist',
            static::class,
            $columnCode
        );
    }

    /**
     * Column of the field "<attribute>.<column>" of the product grid, null for the field of the attribute
     */
    private function getFieldColumn(AttributeInterface $attribute, $field): ?string
    {
        if (!is_string($field) || strtolower($field) === strtolower($attribute->getCode())) {
            return null;
        }

        $parts = explode('.', $field);
        if (2 !== count($parts) || '' === $parts[1]) {
            throw InvalidPropertyException::expected(
                sprintf('The field "%s" is expected to be "%s.<column code>".', $field, $attribute->getCode()),
                static::class
            );
        }

        return $parts[1];
    }

    /**
     * @return array{0: mixed, 1: mixed} column and value of the value {"column": <column>, "value": <value>}
     */
    private function getColumnAndValue(AttributeInterface $attribute, $value): array
    {
        if (!is_array($value) || !isset($value['column'])) {
            throw InvalidPropertyTypeException::validArrayStructureExpected(
                $attribute->getCode(),
                '{"column": <column code>, "value": <value>} is expected',
                static::class,
                is_array($value) ? $value : [$value]
            );
        }

        return [$value['column'], $value['value'] ?? null];
    }

    /**
     * @return string[] the values, in lowercase as the indexed keywords
     */
    private function getKeywords(string $property, $values): array
    {
        if (!is_array($values) || [] === $values) {
            throw InvalidPropertyTypeException::arrayExpected($property, static::class, $values);
        }

        $keywords = [];
        foreach ($values as $value) {
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw InvalidPropertyTypeException::stringExpected($property, static::class, $value);
            }
            $keywords[] = mb_strtolower((string) $value);
        }

        return $keywords;
    }

    private function getRowsPath(AttributeInterface $attribute, ?string $locale, ?string $channel): string
    {
        return sprintf(
            'values.%s%s.%s.%s',
            $attribute->getCode(),
            TableValueNormalizer::FIELD_SUFFIX,
            $channel ?? '<all_channels>',
            $locale ?? '<all_locales>'
        );
    }

    private function nested(string $rowsPath, array $query): array
    {
        return ['nested' => ['path' => $rowsPath, 'query' => $query, 'ignore_unmapped' => true]];
    }

    private function exists(string $path): array
    {
        return ['exists' => ['field' => $path]];
    }

    private function queryString(string $path, string $query): array
    {
        return ['query_string' => ['default_field' => $path, 'query' => $query]];
    }

    /**
     * The entity has the attribute, at its level or the one of its ancestors, as for the other attribute filters
     */
    private function attributeInEntity(AttributeInterface $attribute): array
    {
        return [
            'bool' => [
                'should' => [
                    ['terms' => [self::ATTRIBUTES_FOR_THIS_LEVEL_ES_ID => [$attribute->getCode()]]],
                    ['terms' => [self::ATTRIBUTES_OF_ANCESTORS_ES_ID => [$attribute->getCode()]]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
