<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds the mapping of the rows of table values to the configuration files of the product and product model index,
 * after the ones of the PIM.
 */
final class RegisterElasticsearchMappingPass implements CompilerPassInterface
{
    private const INDEX_CONFIGURATION_FILES = 'akeneo_elasticsearch.client.product_and_product_model.index_configuration.files';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::INDEX_CONFIGURATION_FILES)) {
            return;
        }

        $definition = $container->getDefinition(self::INDEX_CONFIGURATION_FILES);
        $files = $container->getParameterBag()->resolveValue($definition->getArgument(0));
        $files[] = dirname(__DIR__, 2) . '/Resources/elasticsearch/table_mapping.yml';

        $definition->replaceArgument(0, $files);
    }
}
