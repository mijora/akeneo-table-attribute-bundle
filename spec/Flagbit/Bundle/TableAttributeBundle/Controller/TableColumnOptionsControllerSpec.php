<?php

declare(strict_types=1);

namespace spec\Flagbit\Bundle\TableAttributeBundle\Controller;

use Akeneo\Pim\Structure\Component\Model\AttributeInterface;
use Akeneo\Pim\Structure\Component\Repository\AttributeRepositoryInterface;
use Akeneo\Platform\Bundle\UIBundle\Controller\AjaxOptionController;
use Flagbit\Bundle\TableAttributeBundle\Controller\TableColumnOptionsController;
use Flagbit\Bundle\TableAttributeBundle\Entity\AttributeOption;
use Flagbit\Bundle\TableAttributeBundle\Query\GetTableColumns;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class TableColumnOptionsControllerSpec extends ObjectBehavior
{
    function let(
        GetTableColumns $getTableColumns,
        AttributeRepositoryInterface $attributeRepository,
        AjaxOptionController $ajaxOptionController
    ) {
        $getTableColumns->forAttributeCodes(['suppliers_info'])->willReturn(['suppliers_info' => [
            [
                'code' => 'price_list',
                'type' => 'select',
                'config' => ['options' => ['retail' => 'Retail', 'b2b' => 'Business', 'old' => '']],
                'labels' => [],
            ],
            ['code' => 'supplier', 'type' => 'select_from_attribute', 'config' => ['attribute' => 'brand'], 'labels' => []],
            ['code' => 'note', 'type' => 'text', 'config' => [], 'labels' => []],
        ]]);

        $this->beConstructedWith($getTableColumns, $attributeRepository, $ajaxOptionController);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType(TableColumnOptionsController::class);
    }

    function it_lists_the_options_of_a_select_column()
    {
        $this->listAction($this->request(['column' => 'price_list']))->getContent()->shouldReturn(json_encode(['results' => [
            ['id' => 'retail', 'text' => 'Retail'],
            ['id' => 'b2b', 'text' => 'Business'],
            ['id' => 'old', 'text' => '[old]'],
        ]]));
    }

    function it_searches_and_pages_the_options_of_a_select_column()
    {
        $this->listAction($this->request(['column' => 'price_list', 'search' => 'b']))->getContent()
            ->shouldReturn(json_encode(['results' => [['id' => 'b2b', 'text' => 'Business']]]));
        $this->listAction($this->request(['column' => 'price_list', 'options' => ['limit' => 2, 'page' => 2]]))->getContent()
            ->shouldReturn(json_encode(['results' => [['id' => 'old', 'text' => '[old]']]]));
        $this->listAction($this->request(['column' => 'price_list', 'options' => ['identifiers' => ['old', 'retail']]]))->getContent()
            ->shouldReturn(json_encode(['results' => [['id' => 'retail', 'text' => 'Retail'], ['id' => 'old', 'text' => '[old]']]]));
    }

    function it_lists_the_options_of_the_attribute_of_a_select_from_attribute_column(
        AttributeRepositoryInterface $attributeRepository,
        AjaxOptionController $ajaxOptionController,
        AttributeInterface $brand
    ) {
        $brand->getId()->willReturn(42);
        $attributeRepository->findOneByIdentifier('brand')->willReturn($brand);
        $response = new JsonResponse(['results' => [['id' => 'gitana', 'text' => 'Gitana']]]);
        $ajaxOptionController->listAction(Argument::that(
            fn (Request $request) => AttributeOption::class === $request->query->get('class')
                && 42 === $request->query->get('collectionId')
                && 'lt_LT' === $request->query->get('dataLocale')
                && 'git' === $request->query->get('search')
                && ['limit' => '20', 'type' => 'code'] === $request->query->all('options')
        ))->willReturn($response);

        $this->listAction($this->request(['column' => 'supplier', 'search' => 'git', 'options' => ['limit' => '20']]))
            ->shouldReturn($response);
    }

    function it_lists_no_options_for_other_columns(AttributeRepositoryInterface $attributeRepository)
    {
        $attributeRepository->findOneByIdentifier('brand')->willReturn(null);
        $noOptions = json_encode(['results' => []]);

        $this->listAction($this->request(['column' => 'note']))->getContent()->shouldReturn($noOptions);
        $this->listAction($this->request(['column' => 'unknown']))->getContent()->shouldReturn($noOptions);
        $this->listAction($this->request(['column' => 'supplier']))->getContent()->shouldReturn($noOptions);
    }

    private function request(array $query): Request
    {
        return new Request($query + ['attribute' => 'suppliers_info', 'dataLocale' => 'lt_LT']);
    }
}
