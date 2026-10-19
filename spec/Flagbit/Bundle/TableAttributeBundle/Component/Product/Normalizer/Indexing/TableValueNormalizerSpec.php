<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Component\Product\Normalizer\Indexing;

use Akeneo\Pim\Enrichment\Component\Product\Normalizer\Indexing\Value\ValueCollectionNormalizer;
use Akeneo\Pim\Enrichment\Component\Product\Value\ScalarValue;
use Akeneo\Pim\Structure\Component\Query\PublicApi\AttributeType\Attribute;
use Akeneo\Pim\Structure\Component\Query\PublicApi\AttributeType\GetAttributes;
use Flagbit\Bundle\TableAttributeBundle\Component\Product\Normalizer\Indexing\TableValueNormalizer;
use PhpSpec\ObjectBehavior;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class TableValueNormalizerSpec extends ObjectBehavior
{
    private const INDEXING = ValueCollectionNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX;

    function let(GetAttributes $getAttributes)
    {
        $getAttributes->forCode('suppliers_info')->willReturn(
            new Attribute('suppliers_info', 'flagbit_catalog_table', [], false, false, null, null, null, 'text', [])
        );
        $getAttributes->forCode('name')->willReturn(
            new Attribute('name', 'pim_catalog_text', [], false, false, null, null, null, 'text', [])
        );

        $this->beConstructedWith($getAttributes);
    }

    function it_is_a_normalizer()
    {
        $this->shouldHaveType(TableValueNormalizer::class);
        $this->shouldImplement(NormalizerInterface::class);
    }

    function it_supports_the_table_values_for_the_index()
    {
        $this->supportsNormalization(ScalarValue::value('suppliers_info', '[]'), self::INDEXING)->shouldReturn(true);
        $this->supportsNormalization(ScalarValue::value('suppliers_info', '[]'), 'standard')->shouldReturn(false);
        $this->supportsNormalization(ScalarValue::value('name', 'Chair'), self::INDEXING)->shouldReturn(false);
        $this->supportsNormalization(new \stdClass(), self::INDEXING)->shouldReturn(false);
    }

    function it_indexes_the_cells_of_the_rows_as_keywords_and_numbers()
    {
        $value = ScalarValue::scopableLocalizableValue(
            'suppliers_info',
            '[{"supplier":"gitana","quantity":2,"price":"9.90","note":"","active":true,"0":"zero"},{}]',
            'ecommerce',
            'en_US'
        );

        $this->normalize($value, self::INDEXING)->shouldBeLike([
            'suppliers_info-flagbit_table' => [
                'ecommerce' => [
                    'en_US' => [
                        (object) [
                            'keyword' => (object) [
                                'supplier' => 'gitana',
                                'quantity' => '2',
                                'price' => '9.90',
                                'active' => 'true',
                                '0' => 'zero',
                            ],
                            'number' => (object) ['quantity' => 2.0, 'price' => 9.9],
                        ],
                        new \stdClass(),
                    ],
                ],
            ],
        ]);
    }

    function it_indexes_no_rows_for_data_that_are_not_rows()
    {
        $noRows = ['suppliers_info-flagbit_table' => ['<all_channels>' => ['<all_locales>' => []]]];

        $this->normalize(ScalarValue::value('suppliers_info', 'not json'), self::INDEXING)->shouldReturn($noRows);
        $this->normalize(ScalarValue::value('suppliers_info', '["a"]'), self::INDEXING)->shouldReturn($noRows);
    }
}
