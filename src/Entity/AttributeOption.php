<?php

namespace Flagbit\Bundle\TableAttributeBundle\Entity;

use Akeneo\Pim\Structure\Component\Model\AttributeOption as BaseAttributeOption;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;

/**
 * @todo move Table specific columns into an own entity
 */
class AttributeOption extends BaseAttributeOption implements ConstraintConfigInterface
{
    /**
     * @var string
     */
    private string $type;

    /**
     * Null while the column is NULL: the json type, unlike json_array, does not
     * turn NULL into an empty array, so the getter does it.
     *
     * @var array|null
     */
    private ?array $constraints = null;

    /**
     * @var array|null
     */
    private ?array $typeConfig = null;

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type)
    {
        $this->type = $type;
    }

    /**
     * @return array
     */
    public function getConstraints(): array
    {
        return $this->constraints ?? [];
    }

    /**
     * @param array $constraints
     */
    public function setConstraints(array $constraints)
    {
        $this->constraints = $constraints;
    }

    /**
     * @return array
     */
    public function getTypeConfig(): array
    {
        return $this->typeConfig ?? [];
    }
    
    /**
     * @param array $typeConfig
     */
    public function setTypeConfig(array $typeConfig)
    {
        $this->typeConfig = $typeConfig;
    }

    public function isTableAttribute() : bool
    {
        return null !== $this->attribute && TableType::FLAGBIT_CATALOG_TABLE === $this->attribute->getType();
    }
}
