<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Component\Product\UserIntent\Factory\Value;

use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\ClearValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\SetTextValue;
use Akeneo\Pim\Enrichment\Product\API\Command\UserIntent\ValueUserIntent;
use Akeneo\Pim\Enrichment\Product\Domain\UserIntent\Factory\ValidateDataTrait;
use Akeneo\Pim\Enrichment\Product\Domain\UserIntent\Factory\ValueUserIntentFactory;
use Akeneo\Tool\Component\StorageUtils\Exception\InvalidPropertyTypeException;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;

class FlagbitTableValueUserIntentFactory implements ValueUserIntentFactory
{
    use ValidateDataTrait;

    public function getSupportedAttributeTypes(): array
    {
        return [
            TableType::FLAGBIT_CATALOG_TABLE
        ];
    }

    public function create(string $attributeType, string $attributeCode, mixed $data): ValueUserIntent
    {
        $this->validateValueStructure($attributeCode, $data);
        if (null === $data['data'] || '' === $data['data']) {
            return new ClearValue($attributeCode, $data['scope'], $data['locale']);
        }
        if (!\is_string($data['data'])) {
            throw InvalidPropertyTypeException::stringExpected($attributeCode, static::class, $data['data']);
        }

        return match ($attributeType) {
            TableType::FLAGBIT_CATALOG_TABLE => new SetTextValue($attributeCode, $data['scope'], $data['locale'], $data['data']),
            default => throw new \InvalidArgumentException('Not implemented')
        };
    }
}
