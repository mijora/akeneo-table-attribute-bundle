<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration;

use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\UserManagement\Bundle\Context\UserContext;
use Akeneo\UserManagement\Component\Model\UserInterface;
use Flagbit\Bundle\TableAttributeBundle\Datagrid\Configuration\TableColumnsSelectedAttributesConfigurator;
use Oro\Bundle\DataGridBundle\Datagrid\Common\DatagridConfiguration;
use Oro\Bundle\DataGridBundle\Datagrid\RequestParameters;
use Oro\Bundle\PimDataGridBundle\Datagrid\Configuration\ConfiguratorInterface;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TableColumnsSelectedAttributesConfiguratorSpec extends ObjectBehavior
{
    function let(
        ConfiguratorInterface $selectedAttributesConfigurator,
        AttributeRepositoryInterface $attributeRepository,
        UserContext $userContext,
        RequestParameters $requestParams,
        RequestStack $requestStack,
        UserInterface $user
    ) {
        $userContext->getUser()->willReturn($user);
        $this->beConstructedWith($selectedAttributesConfigurator, $attributeRepository, $userContext, $requestParams, $requestStack);
    }

    function it_is_a_configurator()
    {
        $this->shouldHaveType(TableColumnsSelectedAttributesConfigurator::class);
        $this->shouldImplement(ConfiguratorInterface::class);
    }

    function it_adds_the_table_attributes_of_the_column_filters_in_use(
        ConfiguratorInterface $selectedAttributesConfigurator,
        AttributeRepositoryInterface $attributeRepository,
        RequestParameters $requestParams,
        RequestStack $requestStack,
        UserInterface $user
    ) {
        $name = ['code' => 'name', 'type' => 'pim_catalog_text'];
        $configuration = DatagridConfiguration::createNamed('product-grid', [
            'source' => ['locale_code' => 'lt_LT', 'attributes_configuration' => ['name' => $name]],
        ]);
        $selectedAttributesConfigurator->configure($configuration)->shouldBeCalled();
        $requestParams->get('_filter', [])->willReturn(['suppliers_info.supplier' => ['value' => ['gitana']], 'name' => []]);
        $requestStack->getCurrentRequest()->willReturn(new Request(['filters' => ['dimensions.width' => []]]));
        $user->getProductGridFilters()->willReturn(['name', 'suppliers_info.quantity', 'name.not_a_table']);

        $attributeRepository->getAttributeIdsUseableInGrid(['suppliers_info', 'dimensions'])->willReturn([4, 7]);
        $suppliersInfo = ['code' => 'suppliers_info', 'type' => 'flagbit_catalog_table', 'label' => 'Suppliers'];
        $attributeRepository->getAttributesAsArray(true, 'lt_LT', [4, 7])->willReturn([
            'suppliers_info' => $suppliersInfo,
            'dimensions' => ['code' => 'dimensions', 'type' => 'pim_catalog_text'],
        ]);

        $this->configure($configuration);

        $expected = ['name' => $name, 'suppliers_info' => $suppliersInfo];
        $actual = $configuration->offsetGetByPath('[source][attributes_configuration]');
        if ($expected !== $actual) {
            throw new FailureException(sprintf("Expected attributes\n%s\ngot\n%s", var_export($expected, true), var_export($actual, true)));
        }
    }

    function it_does_nothing_without_column_filters(
        ConfiguratorInterface $selectedAttributesConfigurator,
        AttributeRepositoryInterface $attributeRepository,
        RequestParameters $requestParams,
        RequestStack $requestStack,
        UserInterface $user
    ) {
        $configuration = DatagridConfiguration::createNamed('product-grid', ['source' => ['attributes_configuration' => []]]);
        $selectedAttributesConfigurator->configure($configuration)->shouldBeCalled();
        $requestParams->get('_filter', [])->willReturn(['name' => []]);
        $requestStack->getCurrentRequest()->willReturn(new Request());
        $user->getProductGridFilters()->willReturn([]);
        $attributeRepository->getAttributeIdsUseableInGrid([])->shouldNotBeCalled();

        $this->configure($configuration);
    }
}
