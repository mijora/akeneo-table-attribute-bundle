<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration;

use Akeneo\UserManagement\Bundle\Context\UserContext;
use Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration\TableColumnsFiltersConfigurator;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\PimDataGridBundle\Datagrid\Configuration\ConfiguratorInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;

class TableColumnsFiltersConfiguratorSpec extends ObjectBehavior
{
    function let(ConfiguratorInterface $filtersConfigurator, GetTableColumns $getTableColumns, UserContext $userContext)
    {
        $this->beConstructedWith($filtersConfigurator, $getTableColumns, $userContext);
    }

    function it_is_a_configurator()
    {
        $this->shouldHaveType(TableColumnsFiltersConfigurator::class);
        $this->shouldImplement(ConfiguratorInterface::class);
    }

    function it_replaces_the_filter_of_a_table_attribute_by_filters_of_its_columns(
        ConfiguratorInterface $filtersConfigurator,
        GetTableColumns $getTableColumns
    ) {
        $configuration = DatagridConfiguration::createNamed('product-grid', [
            'source' => [
                'locale_code' => 'lt_LT',
                'attributes_configuration' => [
                    'name' => ['code' => 'name', 'type' => 'pim_catalog_text', 'useableAsGridFilter' => true],
                    'suppliers_info' => ['code' => 'suppliers_info', 'type' => 'flagbit_catalog_table', 'useableAsGridFilter' => true],
                ],
            ],
            'filters' => ['columns' => [
                'name' => ['type' => 'product_value_string', 'data_name' => 'name'],
                'suppliers_info' => [
                    'type' => 'product_value_string',
                    'data_name' => 'suppliers_info',
                    'label' => 'Suppliers',
                    'enabled' => false,
                    'order' => 3,
                    'group' => 'ERP',
                    'groupOrder' => 2,
                ],
                'sku' => ['type' => 'product_value_string', 'data_name' => 'sku'],
            ]],
        ]);
        $filtersConfigurator->configure($configuration)->shouldBeCalled();

        $column = fn (string $code, ?string $type, array $labels = []) => ['code' => $code, 'type' => $type, 'config' => [], 'labels' => $labels];
        $getTableColumns->forAttributeCodes(['suppliers_info'])->willReturn(['suppliers_info' => [
            $column('supplier', 'select_from_attribute', ['lt_LT' => 'Tiekėjas']),
            ['code' => 'quantity', 'type' => 'number', 'config' => ['is_decimal' => true], 'labels' => []],
            $column('note', 'text'),
            $column('price_list', 'select'),
            $column('source', 'select_from_url'),
        ]]);

        $this->configure($configuration);

        $common = ['enabled' => false, 'order' => 3, 'group' => 'ERP', 'groupOrder' => 2];
        $choices = fn (string $column) => [
            'type' => 'flagbit_table_column_choice',
            'ftype' => 'ajax_choice',
            'options' => [
                'field_options' => ['multiple' => true, 'attr' => ['empty_choice' => true]],
                'choice_url' => 'flagbit_table_attribute_column_options',
                'choice_url_params' => ['attribute' => 'suppliers_info', 'column' => $column, 'dataLocale' => 'lt_LT'],
            ],
        ];
        $expected = [
            'name' => ['type' => 'product_value_string', 'data_name' => 'name'],
            'suppliers_info.supplier' => $choices('supplier') + ['data_name' => 'suppliers_info.supplier', 'label' => 'Suppliers › Tiekėjas'] + $common,
            'suppliers_info.quantity' => [
                'type' => 'flagbit_table_column_number',
                'ftype' => 'number',
                'options' => ['field_options' => ['attr' => ['empty_choice' => true]]],
                'decimals' => true,
                'data_name' => 'suppliers_info.quantity',
                'label' => 'Suppliers › [quantity]',
            ] + $common,
            'suppliers_info.note' => [
                'type' => 'product_value_string',
                'ftype' => 'string',
                'options' => ['field_options' => ['attr' => ['empty_choice' => true]]],
                'data_name' => 'suppliers_info.note',
                'label' => 'Suppliers › [note]',
            ] + $common,
            'suppliers_info.price_list' => $choices('price_list') + ['data_name' => 'suppliers_info.price_list', 'label' => 'Suppliers › [price_list]'] + $common,
            'sku' => ['type' => 'product_value_string', 'data_name' => 'sku'],
        ];

        $actual = $configuration->offsetGet('filters')['columns'];
        if ($expected !== $actual) {
            throw new FailureException(sprintf("Expected filters\n%s\ngot\n%s", var_export($expected, true), var_export($actual, true)));
        }
    }

    function it_leaves_the_filters_alone_without_table_attributes(
        ConfiguratorInterface $filtersConfigurator,
        GetTableColumns $getTableColumns
    ) {
        $filters = ['columns' => ['name' => ['type' => 'product_value_string', 'data_name' => 'name']]];
        $configuration = DatagridConfiguration::createNamed('product-grid', [
            'source' => ['attributes_configuration' => [
                'name' => ['code' => 'name', 'type' => 'pim_catalog_text', 'useableAsGridFilter' => true],
            ]],
            'filters' => $filters,
        ]);
        $filtersConfigurator->configure($configuration)->shouldBeCalled();
        $getTableColumns->forAttributeCodes([])->shouldNotBeCalled();

        $this->configure($configuration);

        if ($filters !== $configuration->offsetGet('filters')) {
            throw new FailureException('The filters changed.');
        }
    }
}
