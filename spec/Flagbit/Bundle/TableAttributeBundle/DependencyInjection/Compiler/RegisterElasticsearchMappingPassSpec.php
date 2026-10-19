<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\DependencyInjection\Compiler;

use Flagbit\Bundle\TableAttributeBundle\DependencyInjection\Compiler\RegisterElasticsearchMappingPass;
use PhpSpec\Exception\Example\FailureException;
use PhpSpec\ObjectBehavior;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class RegisterElasticsearchMappingPassSpec extends ObjectBehavior
{
    private const LOADER = 'akeneo_elasticsearch.client.product_and_product_model.index_configuration.files';

    function it_is_a_compiler_pass()
    {
        $this->shouldHaveType(RegisterElasticsearchMappingPass::class);
        $this->shouldImplement(CompilerPassInterface::class);
    }

    function it_adds_the_mapping_of_the_rows_after_the_configuration_files_of_the_pim()
    {
        $container = new ContainerBuilder();
        $container->setParameter('pim_mapping', '/pim/product_mapping.yml');
        $container->setDefinition(self::LOADER, new Definition(\stdClass::class, [['/pim/settings.yml', '%pim_mapping%']]));

        $this->process($container);

        $files = $container->getDefinition(self::LOADER)->getArgument(0);
        if (3 !== count($files)
            || ['/pim/settings.yml', '/pim/product_mapping.yml'] !== array_slice($files, 0, 2)
            || !is_readable($files[2])
            || 'table_mapping.yml' !== basename($files[2])
        ) {
            throw new FailureException(sprintf('Unexpected configuration files %s', json_encode($files)));
        }
    }

    function it_does_nothing_without_the_index_of_the_products()
    {
        $container = new ContainerBuilder();

        $this->process($container);

        if ($container->hasDefinition(self::LOADER)) {
            throw new FailureException('A loader was defined.');
        }
    }
}
