<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Flagbit\Bundle\TableAttributeBundle\Datagrid\Filter\TableColumnNumberFilter;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\FilterBundle\Grid\Extension\Configuration as FilterConfiguration;
use Oro\Bundle\PimDataGridBundle\Datagrid\Configuration\ConfiguratorInterface;
use Oro\Bundle\PimFilterBundle\Filter\ProductFilterUtility;

/**
 * Replaces the filter of a table attribute in the product grid by a filter for each of its columns, on the field
 * "<attribute>.<column>": text columns get the filter of text attributes, number columns the one of number attributes
 * adapted to columns and select columns a filter on their options. Columns of other types get no filter.
 *
 * Decorates the configurator of the filters, which also lists the filters that can be added to the grid.
 */
final class TableColumnsFiltersConfigurator implements ConfiguratorInterface
{
    public const COLUMN_CHOICE_FILTER = 'flagbit_table_column_choice';
    public const COLUMN_NUMBER_FILTER = 'flagbit_table_column_number';

    private const COLUMN_FILTERS = [
        'text' => [
            'type' => 'product_value_string',
            'ftype' => 'string',
            'options' => ['field_options' => ['attr' => ['empty_choice' => true]]],
        ],
        'number' => [
            'type' => self::COLUMN_NUMBER_FILTER,
            'ftype' => 'number',
            'options' => ['field_options' => ['attr' => ['empty_choice' => true]]],
        ],
        'select' => [
            'type' => self::COLUMN_CHOICE_FILTER,
            'ftype' => 'ajax_choice',
            'options' => ['field_options' => ['multiple' => true, 'attr' => ['empty_choice' => true]]],
        ],
    ];

    private const FILTERED_TYPES = [
        'text' => 'text',
        'number' => 'number',
        'select' => 'select',
        'select_from_attribute' => 'select',
    ];

    public function __construct(
        private ConfiguratorInterface $filtersConfigurator,
        private GetTableColumns $getTableColumns,
        private UserContext $userContext
    ) {
    }

    public function configure(DatagridConfiguration $configuration)
    {
        $this->filtersConfigurator->configure($configuration);

        $attributes = $configuration->offsetGetByPath(sprintf(self::SOURCE_PATH, self::USEABLE_ATTRIBUTES_KEY)) ?? [];
        $tableAttributes = [];
        foreach ($attributes as $attribute) {
            if (TableType::FLAGBIT_CATALOG_TABLE === ($attribute['type'] ?? null) && ($attribute['useableAsGridFilter'] ?? false)) {
                $tableAttributes[$attribute['code']] = $attribute;
            }
        }

        $filters = $configuration->offsetGet(FilterConfiguration::FILTERS_KEY);
        if ([] === $tableAttributes || empty($filters['columns'])) {
            return;
        }

        $columnsByAttribute = $this->getTableColumns->forAttributeCodes(array_keys($tableAttributes));
        $locale = $configuration->offsetGetByPath(sprintf(self::SOURCE_PATH, self::DISPLAYED_LOCALE_KEY))
            ?? $this->userContext->getCurrentLocaleCode();

        // The filters of the columns take the place of the one of their attribute, in the order of the columns
        $filterColumns = [];
        foreach ($filters['columns'] as $code => $filter) {
            if (!isset($tableAttributes[$code])) {
                $filterColumns[$code] = $filter;
                continue;
            }

            foreach ($columnsByAttribute[strtolower((string) $code)] ?? [] as $column) {
                $columnFilter = $this->getColumnFilter((string) $code, $filter, $column, $locale);
                if (null !== $columnFilter) {
                    $filterColumns[$columnFilter[ProductFilterUtility::DATA_NAME_KEY]] = $columnFilter;
                }
            }
        }

        $filters['columns'] = $filterColumns;
        $configuration->offsetSet(FilterConfiguration::FILTERS_KEY, $filters);
    }

    private function getColumnFilter(string $attributeCode, array $attributeFilter, array $column, ?string $locale): ?array
    {
        $type = self::FILTERED_TYPES[$column['type']] ?? null;
        if (null === $type) {
            return null;
        }

        $columnFilter = self::COLUMN_FILTERS[$type];
        if ('number' === $type) {
            $columnFilter[TableColumnNumberFilter::DECIMALS_KEY] = filter_var(
                $column['config']['is_decimal'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        }
        if ('select' === $type) {
            $columnFilter['options']['choice_url'] = 'flagbit_table_attribute_column_options';
            $columnFilter['options']['choice_url_params'] = [
                'attribute' => $attributeCode,
                'column' => $column['code'],
                'dataLocale' => $locale,
            ];
        }

        $columnLabel = $column['labels'][$locale] ?? sprintf('[%s]', $column['code']);

        return $columnFilter + [
            ProductFilterUtility::DATA_NAME_KEY => sprintf('%s.%s', $attributeCode, $column['code']),
            'label' => sprintf('%s › %s', $attributeFilter['label'] ?? $attributeCode, $columnLabel),
            'enabled' => $attributeFilter['enabled'] ?? false,
            'order' => $attributeFilter['order'] ?? null,
            'group' => $attributeFilter['group'] ?? null,
            'groupOrder' => $attributeFilter['groupOrder'] ?? null,
        ];
    }
}
