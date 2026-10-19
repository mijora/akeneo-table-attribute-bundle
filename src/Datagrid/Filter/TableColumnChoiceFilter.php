<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Datagrid\Filter;

use Oro\Bundle\FilterBundle\Datasource\FilterDatasourceAdapterInterface;
use Oro\Bundle\PimFilterBundle\Filter\AjaxChoiceFilter;
use Oro\Bundle\PimFilterBundle\Filter\ProductFilterUtility;

/**
 * Product grid filter of a select column of a table attribute, on the field "<attribute>.<column>": its choices come
 * from the "choice_url" of its options, set by TableColumnsFiltersConfigurator, and the chosen codes are filtered on as
 * they are, as the cells may hold codes that are no options anymore.
 */
final class TableColumnChoiceFilter extends AjaxChoiceFilter
{
    public function apply(FilterDatasourceAdapterInterface $ds, $data)
    {
        $data = $this->parseData($data);
        if (!$data) {
            return false;
        }

        $this->util->applyFilter(
            $ds,
            $this->get(ProductFilterUtility::DATA_NAME_KEY),
            $this->getOperator($data['type']),
            $data['value']
        );

        return true;
    }
}
