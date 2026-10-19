<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Component\Product\Normalizer\Indexing;

use Akeneo\Pim\Enrichment\Component\Product\Model\ValueInterface;
use Akeneo\Pim\Enrichment\Component\Product\Normalizer\Indexing\Value\ValueCollectionNormalizer;
use Akeneo\Pim\Structure\Component\Query\PublicApi\AttributeType\GetAttributes;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Indexes the rows of table values so that they can be filtered on the values of their columns: every row is a
 * nested document, see Resources/elasticsearch/table_mapping.yml, holding its cells as keywords and, when they are
 * numbers, as numbers too:
 *
 *     values.<attribute>-flagbit_table.<channel>.<locale>: [
 *         {keyword: {supplier: "gitana", price: "9.90"}, number: {price: 9.9}},
 *     ]
 *
 * The rows are the ones of the product as read, already brought to the current columns.
 */
final class TableValueNormalizer implements NormalizerInterface
{
    public const FIELD_SUFFIX = '-flagbit_table';
    public const KEYWORD = 'keyword';
    public const NUMBER = 'number';

    public function __construct(private GetAttributes $getAttributes)
    {
    }

    public function supportsNormalization($data, $format = null): bool
    {
        if (!$data instanceof ValueInterface || ValueCollectionNormalizer::INDEXING_FORMAT_PRODUCT_AND_MODEL_INDEX !== $format) {
            return false;
        }

        $attribute = $this->getAttributes->forCode($data->getAttributeCode());

        return null !== $attribute && TableType::FLAGBIT_CATALOG_TABLE === $attribute->type();
    }

    public function normalize($value, $format = null, array $context = []): array
    {
        $attribute = $this->getAttributes->forCode($value->getAttributeCode());
        $channel = $value->getScopeCode() ?? '<all_channels>';
        $locale = $value->getLocaleCode() ?? '<all_locales>';

        return [$attribute->code() . self::FIELD_SUFFIX => [$channel => [$locale => $this->normalizeRows($value->getData())]]];
    }

    private function normalizeRows($data): array
    {
        $rows = is_string($data) ? json_decode($data, true) : null;
        if (!is_array($rows)) {
            return [];
        }

        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $keywords = [];
            $numbers = [];
            foreach ($row as $column => $cell) {
                if (is_bool($cell)) {
                    $keywords[$column] = $cell ? 'true' : 'false';
                } elseif (is_int($cell) || is_float($cell)) {
                    $keywords[$column] = (string) $cell;
                    $numbers[$column] = (float) $cell;
                } elseif (is_string($cell) && '' !== trim($cell)) {
                    $keywords[$column] = $cell;
                    if (is_numeric(trim($cell))) {
                        $numbers[$column] = (float) trim($cell);
                    }
                }
            }

            // Objects, so that column codes made of digits stay keys
            $normalizedRow = [];
            if ([] !== $keywords) {
                $normalizedRow[self::KEYWORD] = (object) $keywords;
            }
            if ([] !== $numbers) {
                $normalizedRow[self::NUMBER] = (object) $numbers;
            }
            $normalizedRows[] = (object) $normalizedRow;
        }

        return $normalizedRows;
    }
}
