<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Datagrid\Filter;

use Oro\Bundle\FilterBundle\Filter\NumberFilter as OroNumberFilter;
use Oro\Bundle\PimFilterBundle\Filter\ProductValue\NumberFilter;

/**
 * Product grid filter of a number column of a table attribute, on the field "<attribute>.<column>": the filter of the
 * number attributes, which takes from the attribute named by its field whether it shows decimals, takes it from the
 * "decimals" parameter set by TableColumnsFiltersConfigurator.
 */
final class TableColumnNumberFilter extends NumberFilter
{
    public const DECIMALS_KEY = 'decimals';

    public function getMetadata()
    {
        $metadata = OroNumberFilter::getMetadata();

        if (true === $this->getOr(self::DECIMALS_KEY, false)) {
            $metadata['formatterOptions']['decimals'] = 2;
            $metadata['formatterOptions']['grouping'] = true;
        }

        return $metadata;
    }
}
