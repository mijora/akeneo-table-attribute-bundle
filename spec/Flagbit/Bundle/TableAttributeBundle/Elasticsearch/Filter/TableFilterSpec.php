<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Elasticsearch\Filter;

use Akeneo\Pim\Enrichment\Bundle\Elasticsearch\SearchQueryBuilder;
use Akeneo\Pim\Enrichment\Component\Product\Query\Filter\AttributeFilterInterface;
use Akeneo\Pim\Enrichment\Component\Product\Validator\ElasticsearchFilterValidator;
use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyException;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Flagbit\Bundle\TableAttributeBundle\Elasticsearch\Filter\TableFilter;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;
use PhpSpec\ObjectBehavior;

class TableFilterSpec extends ObjectBehavior
{
    private const ROWS = 'values.suppliers_info-flagbit_table.<all_channels>.<all_locales>';

    function let(
        ElasticsearchFilterValidator $filterValidator,
        GetTableColumns $getTableColumns,
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $column = fn (string $code, string $type) => ['code' => $code, 'type' => $type, 'config' => [], 'labels' => []];
        $getTableColumns->forAttributeCodes(['suppliers_info'])->willReturn([
            'suppliers_info' => [
                $column('supplier', 'select_from_attribute'),
                $column('quantity', 'number'),
                $column('note', 'text'),
                $column('source', 'select_from_url'),
            ],
        ]);
        $attribute->getCode()->willReturn('suppliers_info');
        $attribute->getType()->willReturn('flagbit_catalog_table');
        $attribute->getBackendType()->willReturn('text');

        $this->beConstructedWith($filterValidator, $getTableColumns);
        $this->setQueryBuilder($searchQueryBuilder);
    }

    function it_is_a_filter_of_table_attributes(AttributeInterface $attribute)
    {
        $this->shouldHaveType(TableFilter::class);
        $this->shouldImplement(AttributeFilterInterface::class);
        $this->supportsAttribute($attribute)->shouldReturn(true);
        $this->supportsOperator('SAME ROW')->shouldReturn(true);
        $this->supportsOperator('ENDS WITH')->shouldReturn(true);
        $this->supportsOperator('BETWEEN')->shouldReturn(false);
    }

    function it_filters_on_a_column_given_by_the_field_of_the_product_grid(
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $searchQueryBuilder->addFilter($this->nested(['terms' => [self::ROWS . '.keyword.supplier' => ['gitana']]]))
            ->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'IN', ['Gitana'], null, null, ['field' => 'suppliers_info.supplier']);
    }

    function it_filters_on_a_column_given_by_the_value_of_the_api(
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $searchQueryBuilder->addFilter($this->nested(['range' => [self::ROWS . '.number.quantity' => ['gt' => 0.0]]]))
            ->shouldBeCalled();

        $this->addAttributeFilter($attribute, '>', ['column' => 'quantity', 'value' => '0'], null, null, ['field' => 'suppliers_info']);
    }

    function it_filters_the_products_whose_rows_do_not_match_a_negative_operator(
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $cell = self::ROWS . '.keyword.note';
        $searchQueryBuilder->addMustNot($this->nested(['query_string' => ['default_field' => $cell, 'query' => '*slow*']]))
            ->shouldBeCalled();
        $searchQueryBuilder->addFilter($this->nested(['exists' => ['field' => $cell]]))->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'DOES NOT CONTAIN', 'Slow', null, null, ['field' => 'suppliers_info.note']);
    }

    function it_filters_the_products_without_values_in_a_column(
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $searchQueryBuilder->addMustNot($this->nested(['exists' => ['field' => self::ROWS . '.keyword.note']]))
            ->shouldBeCalled();
        $searchQueryBuilder->addFilter($this->attributeInEntity())->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'EMPTY', '', null, null, ['field' => 'suppliers_info.note']);
    }

    function it_filters_the_products_without_rows(SearchQueryBuilder $searchQueryBuilder, AttributeInterface $attribute)
    {
        $searchQueryBuilder->addMustNot($this->nested(['match_all' => new \stdClass()]))->shouldBeCalled();
        $searchQueryBuilder->addFilter($this->attributeInEntity())->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'EMPTY', null, null, null, ['field' => 'suppliers_info']);
    }

    function it_filters_on_several_columns_of_the_same_row(
        SearchQueryBuilder $searchQueryBuilder,
        AttributeInterface $attribute
    ) {
        $searchQueryBuilder->addFilter($this->nested([
            'bool' => [
                'filter' => [
                    ['terms' => [self::ROWS . '.keyword.supplier' => ['gitana']]],
                    ['range' => [self::ROWS . '.number.quantity' => ['gte' => 1.0]]],
                    ['exists' => ['field' => self::ROWS . '.keyword.note']],
                ],
                'must_not' => [
                    ['term' => [self::ROWS . '.keyword.note' => 'old']],
                ],
            ],
        ]))->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'SAME ROW', [
            ['column' => 'supplier', 'operator' => 'IN', 'value' => ['gitana']],
            ['column' => 'quantity', 'operator' => '>=', 'value' => 1],
            ['column' => 'note', 'operator' => '!=', 'value' => 'old'],
        ], null, null, ['field' => 'suppliers_info']);
    }

    function it_uses_the_channel_and_locale_of_the_value(SearchQueryBuilder $searchQueryBuilder, AttributeInterface $attribute)
    {
        $rows = 'values.suppliers_info-flagbit_table.ecommerce.en_US';
        $searchQueryBuilder->addFilter([
            'nested' => ['path' => $rows, 'query' => ['exists' => ['field' => $rows . '.number.quantity']], 'ignore_unmapped' => true],
        ])->shouldBeCalled();

        $this->addAttributeFilter($attribute, 'NOT EMPTY', null, 'en_US', 'ecommerce', ['field' => 'suppliers_info.quantity']);
    }

    function it_rejects_the_conditions_it_can_not_filter_on(AttributeInterface $attribute)
    {
        $field = fn (string $column) => ['field' => 'suppliers_info.' . $column];

        $this->shouldThrow(InvalidPropertyException::class)
            ->during('addAttributeFilter', [$attribute, '=', 'x', null, null, $field('color')]);
        $this->shouldThrow(InvalidPropertyException::class)
            ->during('addAttributeFilter', [$attribute, 'IN', ['x'], null, null, $field('source')]);
        $this->shouldThrow(InvalidPropertyException::class)
            ->during('addAttributeFilter', [$attribute, 'CONTAINS', 'x', null, null, $field('quantity')]);
        $this->shouldThrow(InvalidPropertyTypeException::class)
            ->during('addAttributeFilter', [$attribute, '>', 'many', null, null, $field('quantity')]);
        $this->shouldThrow(InvalidPropertyTypeException::class)
            ->during('addAttributeFilter', [$attribute, 'IN', 'gitana', null, null, $field('supplier')]);
        $this->shouldThrow(InvalidPropertyTypeException::class)
            ->during('addAttributeFilter', [$attribute, '=', 'gitana', null, null, ['field' => 'suppliers_info']]);
        $this->shouldThrow(InvalidPropertyTypeException::class)
            ->during('addAttributeFilter', [$attribute, 'SAME ROW', ['supplier' => 'gitana'], null, null, ['field' => 'suppliers_info']]);
        $this->shouldThrow(InvalidPropertyException::class)
            ->during('addAttributeFilter', [$attribute, 'SAME ROW', [], null, null, $field('supplier')]);
    }

    private function nested(array $query): array
    {
        return ['nested' => ['path' => self::ROWS, 'query' => $query, 'ignore_unmapped' => true]];
    }

    private function attributeInEntity(): array
    {
        return [
            'bool' => [
                'should' => [
                    ['terms' => ['attributes_for_this_level' => ['suppliers_info']]],
                    ['terms' => ['attributes_of_ancestors' => ['suppliers_info']]],
                ],
                'minimum_should_match' => 1,
            ],
        ];
    }
}
