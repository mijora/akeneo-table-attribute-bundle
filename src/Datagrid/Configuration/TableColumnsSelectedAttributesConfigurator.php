<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration;

use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use Flagbit\Bundle\TableAttributeBundle\AttributeType\TableType;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\RequestParameters;
use Oro\Bundle\PimDataGridBundle\Datagrid\Configuration\ConfiguratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds to the attributes of the product grid the table attributes whose column filters, "<attribute>.<column>", are
 * used or chosen by the user, as the grid only configures the attributes used by its filters and columns, found by
 * their code.
 *
 * Decorates the configurator of the selected attributes.
 */
final class TableColumnsSelectedAttributesConfigurator implements ConfiguratorInterface
{
    public function __construct(
        private ConfiguratorInterface $selectedAttributesConfigurator,
        private AttributeRepositoryInterface $attributeRepository,
        private UserContext $userContext,
        private RequestParameters $requestParams,
        private RequestStack $requestStack
    ) {
    }

    public function configure(DatagridConfiguration $configuration)
    {
        $this->selectedAttributesConfigurator->configure($configuration);

        $path = sprintf(self::SOURCE_PATH, self::USEABLE_ATTRIBUTES_KEY);
        $attributes = $configuration->offsetGetByPath($path) ?? [];

        $attributeCodes = [];
        foreach ($this->getUsedFilterCodes() as $filterCode) {
            $parts = explode('.', (string) $filterCode);
            if (2 === count($parts) && '' !== $parts[0] && !isset($attributes[$parts[0]])) {
                $attributeCodes[$parts[0]] = $parts[0];
            }
        }
        if ([] === $attributeCodes) {
            return;
        }

        $attributeIds = $this->attributeRepository->getAttributeIdsUseableInGrid(array_values($attributeCodes));
        if (empty($attributeIds)) {
            return;
        }

        $locale = $configuration->offsetGetByPath(sprintf(self::SOURCE_PATH, self::DISPLAYED_LOCALE_KEY))
            ?? $this->userContext->getCurrentLocaleCode();
        foreach ($this->attributeRepository->getAttributesAsArray(true, $locale, $attributeIds) as $code => $attribute) {
            if (TableType::FLAGBIT_CATALOG_TABLE === ($attribute['type'] ?? null)) {
                $attributes[$code] = $attribute;
            }
        }

        $configuration->offsetSetByPath($path, $attributes);
    }

    /**
     * Codes of the filters used by the request, the same way as the decorated configurator, and of the filters the
     * user chose to display
     *
     * @return string[]
     */
    private function getUsedFilterCodes(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $filterValues = array_replace(
            $this->requestParams->get('_filter', []),
            null === $request ? [] : $request->get('filters', [])
        );

        return array_merge(
            array_keys(is_array($filterValues) ? $filterValues : []),
            $this->userContext->getUser()->getProductGridFilters() ?? []
        );
    }
}
