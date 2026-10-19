<?php

declare(strict_types=1);

namespace Flagbit\Bundle\TableAttributeBundle\Controller;

use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Platform\Bundle\UIBundle\Controller\AjaxOptionController as BaseAjaxOptionController;
use Flagbit\Bundle\TableAttributeBundle\Entity\AttributeOption;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Options of a select column of a table attribute, for the filter of the column in the product grid, in the format of
 * the options of the PIM, {"results": [{"id": <code>, "text": <label>}]}: the options of the column for a
 * "Simple select" column, the ones of its attribute for a "Simple select from attribute" column.
 *
 * Query parameters: "attribute", "column", "dataLocale", "search", "options[limit]", "options[page]" and
 * "options[identifiers]".
 */
final class TableColumnOptionsController
{
    public function __construct(
        private GetTableColumns $getTableColumns,
        private AttributeRepositoryInterface $attributeRepository,
        private BaseAjaxOptionController $ajaxOptionController
    ) {
    }

    public function listAction(Request $request): JsonResponse
    {
        $attributeCode = (string) $request->query->get('attribute', '');
        $column = $this->getColumn($attributeCode, (string) $request->query->get('column', ''));
        $options = $request->query->all('options');

        if ('select_from_attribute' === ($column['type'] ?? null)) {
            $sourceAttribute = $this->attributeRepository->findOneByIdentifier((string) ($column['config']['attribute'] ?? ''));
            if (null === $sourceAttribute) {
                return new JsonResponse(['results' => []]);
            }

            return $this->ajaxOptionController->listAction($request->duplicate(array_merge($request->query->all(), [
                'class' => AttributeOption::class,
                'collectionId' => $sourceAttribute->getId(),
                'options' => array_merge($options, ['type' => 'code']),
            ])));
        }

        if ('select' !== ($column['type'] ?? null)) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];
        foreach ($column['config']['options'] ?? [] as $code => $label) {
            $results[] = ['id' => (string) $code, 'text' => '' === (string) $label ? sprintf('[%s]', $code) : (string) $label];
        }

        $identifiers = $options['identifiers'] ?? $options['ids'] ?? [];
        if (is_array($identifiers) && [] !== $identifiers) {
            $identifiers = array_map('strval', $identifiers);
            $results = array_filter($results, fn (array $result) => in_array($result['id'], $identifiers, true));
        }

        $search = (string) $request->query->get('search', '');
        if ('' !== $search) {
            $results = array_filter(
                $results,
                fn (array $result) => false !== mb_stripos($result['text'], $search) || false !== mb_stripos($result['id'], $search)
            );
        }

        $results = array_values($results);
        if (isset($options['limit'])) {
            $limit = max(1, (int) $options['limit']);
            $page = max(1, (int) ($options['page'] ?? 1));
            $results = array_slice($results, ($page - 1) * $limit, $limit);
        }

        return new JsonResponse(['results' => $results]);
    }

    private function getColumn(string $attributeCode, string $columnCode): ?array
    {
        $columns = $this->getTableColumns->forAttributeCodes([$attributeCode])[strtolower($attributeCode)] ?? [];
        foreach ($columns as $column) {
            if ($column['code'] === $columnCode) {
                return $column;
            }
        }

        return null;
    }
}
