<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Component\Product\Factory\NonExistentValuesFilter;

use Akeneo\Pim\Enrichment\Component\Product\Factory\NonExistentValuesFilter\NonExistentValuesFilter;
use Akeneo\Pim\Enrichment\Component\Product\Factory\NonExistentValuesFilter\OnGoingFilteredRawValues;
use Akeneo\Pim\Structure\Component\AttributeTypes;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Flagbit\Bundle\TableAttributeBundle\Component\Product\Factory\NonExistentValuesFilter\TableColumnsValuesFilter;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumnCodes;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class TableColumnsValuesFilterSpec extends ObjectBehavior
{
    function let(GetTableColumnCodes $getTableColumnCodes)
    {
        $this->beConstructedWith($getTableColumnCodes);
    }

    function it_is_a_non_existent_values_filter()
    {
        $this->shouldHaveType(TableColumnsValuesFilter::class);
        $this->shouldImplement(NonExistentValuesFilter::class);
    }

    function it_leaves_the_values_alone_when_there_are_no_table_values(GetTableColumnCodes $getTableColumnCodes)
    {
        $onGoingFilteredRawValues = OnGoingFilteredRawValues::fromNonFilteredValuesCollectionIndexedByType(
            [AttributeTypes::TEXTAREA => ['a_description' => [$this->productValues('product_A', 'plop')]]]
        );

        $getTableColumnCodes->forAttributeCodes(Argument::any())->shouldNotBeCalled();

        $this->filter($onGoingFilteredRawValues)->shouldReturn($onGoingFilteredRawValues);
    }

    function it_brings_the_rows_to_the_current_columns(GetTableColumnCodes $getTableColumnCodes)
    {
        $onGoingFilteredRawValues = OnGoingFilteredRawValues::fromNonFilteredValuesCollectionIndexedByType(
            [
                TableType::FLAGBIT_CATALOG_TABLE => [
                    'suppliers_info' => [
                        // "price" was deleted and "note" added since it was saved
                        $this->productValues('product_A', '[{"supplier":"a","price":"9.90","quantity":2}]'),
                        // already has the columns, in another order and with escaped characters
                        $this->productValues('product_B', '[{"quantity":1,"note":"\u0117","supplier":"b"}]', 'ecommerce', 'en_US'),
                        // one row to bring to the columns, the other one already has them
                        $this->productValues(
                            'product_C',
                            '[{"supplier":"c","quantity":3,"note":null},{"SUPPLIER":"ė/1","Quantity":4.0}]'
                        ),
                    ],
                ],
                AttributeTypes::TEXTAREA => ['a_description' => [$this->productValues('product_A', 'plop')]],
            ]
        );

        $getTableColumnCodes->forAttributeCodes(['suppliers_info'])->willReturn(
            ['suppliers_info' => ['supplier', 'quantity', 'note']]
        );

        $filtered = $this->filter($onGoingFilteredRawValues);

        $filtered->filteredRawValuesCollectionIndexedByType()->shouldReturn(
            [
                TableType::FLAGBIT_CATALOG_TABLE => [
                    'suppliers_info' => [
                        $this->productValues('product_A', '[{"supplier":"a","quantity":2,"note":null}]'),
                        $this->productValues('product_B', '[{"quantity":1,"note":"\u0117","supplier":"b"}]', 'ecommerce', 'en_US'),
                        $this->productValues(
                            'product_C',
                            '[{"supplier":"c","quantity":3,"note":null},{"supplier":"ė/1","quantity":4.0,"note":null}]'
                        ),
                    ],
                ],
            ]
        );
        $filtered->nonFilteredRawValuesCollectionIndexedByType()->shouldReturn(
            [AttributeTypes::TEXTAREA => ['a_description' => [$this->productValues('product_A', 'plop')]]]
        );
    }

    function it_keeps_the_values_it_can_not_bring_to_the_columns(GetTableColumnCodes $getTableColumnCodes)
    {
        $values = [
            $this->productValues('not_json', 'not json'),
            $this->productValues('not_a_list', '{"supplier":"a"}'),
            $this->productValues('not_rows', '["a",{"supplier":"a"}]'),
            $this->productValues('no_rows', '[]'),
        ];
        $onGoingFilteredRawValues = OnGoingFilteredRawValues::fromNonFilteredValuesCollectionIndexedByType(
            [
                TableType::FLAGBIT_CATALOG_TABLE => [
                    'suppliers_info' => $values,
                    'without_columns' => [$this->productValues('product_A', '[{"supplier":"a"}]')],
                ],
            ]
        );

        $getTableColumnCodes->forAttributeCodes(['suppliers_info', 'without_columns'])->willReturn(
            ['suppliers_info' => ['supplier', 'quantity']]
        );

        $this->filter($onGoingFilteredRawValues)->filteredRawValuesCollectionIndexedByType()->shouldReturn(
            [
                TableType::FLAGBIT_CATALOG_TABLE => [
                    'suppliers_info' => $values,
                    'without_columns' => [$this->productValues('product_A', '[{"supplier":"a"}]')],
                ],
            ]
        );
    }

    function it_keeps_column_codes_made_of_digits_as_keys(GetTableColumnCodes $getTableColumnCodes)
    {
        $onGoingFilteredRawValues = OnGoingFilteredRawValues::fromNonFilteredValuesCollectionIndexedByType(
            [TableType::FLAGBIT_CATALOG_TABLE => ['sizes' => [$this->productValues('product_A', '[{"0":"s"}]')]]]
        );

        $getTableColumnCodes->forAttributeCodes(['sizes'])->willReturn(['sizes' => ['0', '1']]);

        $this->filter($onGoingFilteredRawValues)->filteredRawValuesCollectionIndexedByType()->shouldReturn(
            [TableType::FLAGBIT_CATALOG_TABLE => ['sizes' => [$this->productValues('product_A', '[{"0":"s","1":null}]')]]]
        );
    }

    private function productValues(
        string $identifier,
        string $value,
        string $channel = '<all_channels>',
        string $locale = '<all_locales>'
    ): array {
        return [
            'identifier' => $identifier,
            'values' => [$channel => [$locale => $value]],
            'properties' => [],
        ];
    }
}
