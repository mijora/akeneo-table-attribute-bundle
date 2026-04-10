<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Component\Product\Connector\ArrayConverter\FlatToStandard\ValueConverter;

use Akeneo\Pim\Enrichment\Component\Product\Connector\ArrayConverter\FlatToStandard\ValueConverter\TextConverter;

class TableTextConverter extends TextConverter
{
    public function convert(array $attributeFieldInfo, $data)
    {
        if (\is_string($data)) {
            // Normalize malformed decimal fragments before the default converter handles the value.
            $data = (string) preg_replace('/:(\s*\d+),(?=\d+,)/', ':$1.', $data);
        }

        return parent::convert($attributeFieldInfo, $data);
    }
}