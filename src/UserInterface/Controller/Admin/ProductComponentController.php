<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\UserInterface\Controller\Admin;

use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductComponentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Admin CRUD for the components of a configurable product.
 *
 * The components are the ordered `components` of the parent's draft content, so every write replaces
 * that list through the modify message. Publishing acts on the component product itself, never on
 * the parent.
 *
 * @internal
 *
 * @phpstan-import-type ModifyProductMessageData from ModifyProductMessage
 */
final class ProductComponentController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        MessageBusInterface $messageBus,
        private NormalizerInterface $normalizer,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
    ) {
        $this->messageBus = $messageBus;
    }

    public function cgetAction(Request $request, string $parentId): Response
    {
        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ProductInterface::LIST_KEY_COMPONENTS);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        // selecting the position also joins the components that restrict the list to the parent
        $listBuilder->addSelectField($fieldDescriptors['componentPosition']);
        // the publish and ghost indicators are rendered from these, whether or not the list requests them
        $listBuilder->addSelectField($fieldDescriptors['published']);
        $listBuilder->addSelectField($fieldDescriptors['publishedState']);
        $listBuilder->addSelectField($fieldDescriptors['ghostLocale']);
        $listBuilder->setParameter('locale', $this->getLocale($request));
        $listBuilder->setParameter('parentId', $parentId);

        if (null === $request->query->get('sortBy')) {
            $listBuilder->sort($fieldDescriptors['productFamily']);
            $listBuilder->sort($fieldDescriptors['componentPosition']);
        }

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductInterface::LIST_KEY_COMPONENTS,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        /** @var array{_embedded: array{product_components: array<int, array<string, mixed>>}} $list */
        $list = $listRepresentation->toArray();
        foreach ($list['_embedded'][ProductInterface::LIST_KEY_COMPONENTS] as &$item) {
            // the admin expects a boolean, the list builder returns the raw workflow place
            $item['publishedState'] = WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === ($item['publishedState'] ?? null);
        }

        return new JsonResponse($this->normalizer->normalize($list, 'json'));
    }

    public function getAction(string $parentId, string $id): Response
    {
        $this->assertComponentOfParent($this->loadComponentUuids($parentId), $parentId, $id);

        return new JsonResponse(['id' => $id, 'product' => $id]);
    }

    public function postAction(Request $request, string $parentId): Response
    {
        $components = $this->loadComponentUuids($parentId);
        $error = $this->validate($request, $parentId, $components);

        if ($error instanceof Response) {
            return $error;
        }

        $uuid = $request->request->getString('product');
        $this->writeComponents($request, $parentId, [...$components, $uuid]);

        return new JsonResponse(['id' => $uuid, 'product' => $uuid], 201);
    }

    public function putAction(Request $request, string $parentId, string $id): Response
    {
        if ($request->query->has('action')) {
            return $this->postTriggerAction($request, $parentId, $id);
        }

        $components = $this->loadComponentUuids($parentId);
        $this->assertComponentOfParent($components, $parentId, $id);

        $uuid = $request->request->getString('product');
        $error = $this->validate($request, $parentId, $components, $id);

        if ($error instanceof Response) {
            return $error;
        }

        $this->writeComponents(
            $request,
            $parentId,
            \array_map(static fn (string $component): string => $component === $id ? $uuid : $component, $components),
        );

        return new JsonResponse(['id' => $uuid, 'product' => $uuid]);
    }

    public function deleteAction(Request $request, string $parentId, string $id): Response
    {
        $components = $this->loadComponentUuids($parentId);
        $this->assertComponentOfParent($components, $parentId, $id);

        $this->writeComponents(
            $request,
            $parentId,
            \array_values(\array_filter($components, static fn (string $component): bool => $component !== $id)),
        );

        return new Response('', 204);
    }

    /**
     * Publishes or unpublishes the component product itself; the parent is never touched.
     */
    public function postTriggerAction(Request $request, string $parentId, string $id): Response
    {
        $this->assertComponentOfParent($this->loadComponentUuids($parentId), $parentId, $id);

        $action = $request->query->getString('action');
        $transitions = [WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH, WorkflowInterface::WORKFLOW_TRANSITION_UNPUBLISH];

        if (!\in_array($action, $transitions, true)) {
            return new JsonResponse([
                'detail' => \sprintf('Unsupported action "%s", expected one of "%s".', $action, \implode('", "', $transitions)),
            ], 400);
        }

        $message = new ApplyWorkflowTransitionProductMessage(['uuid' => $id], $this->getLocale($request), $action);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UnavailableContentTransitionException|ContentNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 409);
        }

        return new JsonResponse(['id' => $id, 'product' => $id]);
    }

    public function getSecurityContext(): string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @param array<int, string> $components
     */
    private function validate(Request $request, string $parentId, array $components, ?string $replacedId = null): ?Response
    {
        $parent = $this->getProductOrFail($parentId);

        if (!$parent->isType(ProductInterface::TYPE_CONFIGURABLE_PRODUCT)) {
            return new JsonResponse([
                'detail' => \sprintf('Product "%s" cannot have components.', $parentId),
            ], 409);
        }

        $uuid = $request->request->getString('product');

        if ('' === $uuid) {
            return new JsonResponse(['detail' => 'A product is required.'], 422);
        }

        if ($uuid === $parentId) {
            return new JsonResponse(['detail' => 'A product cannot be a component of itself.'], 422);
        }

        if ($this->getProductOrFail($uuid)->isType(ProductInterface::TYPE_VARIANT)) {
            return new JsonResponse([
                'detail' => \sprintf('Product "%s" is a variant and cannot be a component.', $uuid),
            ], 422);
        }

        if ($uuid !== $replacedId && \in_array($uuid, $components, true)) {
            return new JsonResponse([
                'detail' => \sprintf('Product "%s" is already a component of "%s".', $uuid, $parentId),
            ], 409);
        }

        return null;
    }

    /**
     * The components in their order, read from the draft content that all locales share.
     *
     * @return array<int, string>
     */
    private function loadComponentUuids(string $parentId): array
    {
        try {
            $parent = $this->productRepository->getOneBy(
                ['uuid' => $parentId],
                [
                    ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                        'dimensionAttributes' => ['locale' => null, 'stage' => DimensionContentInterface::STAGE_DRAFT],
                        'selects' => [],
                    ],
                ],
            );
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        $uuids = [];
        // the query above loads the unlocalized draft only
        foreach ($parent->getDimensionContents() as $dimensionContent) {
            $uuids = \array_values(\array_map(
                static fn (ProductComponentInterface $component): string => $component->getComponent()->getUuid(),
                $dimensionContent->getComponents(),
            ));
        }

        return $uuids;
    }

    /**
     * 404s a `{parentId}`/`{id}` pair for unrelated products instead of silently acting on another parent.
     *
     * @param array<int, string> $components
     */
    private function assertComponentOfParent(array $components, string $parentId, string $id): void
    {
        if (!\in_array($id, $components, true)) {
            throw new NotFoundHttpException(\sprintf('Component "%s" does not belong to parent "%s".', $id, $parentId));
        }
    }

    private function getProductOrFail(string $uuid): ProductInterface
    {
        try {
            return $this->productRepository->getOneBy(['uuid' => $uuid]);
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }

    /**
     * @param array<int, string> $components
     */
    private function writeComponents(Request $request, string $parentId, array $components): void
    {
        /** @var array<string, mixed> $values */
        $values = [
            'locale' => $this->getLocale($request),
            'components' => $components,
        ];
        /** @var ModifyProductMessageData $data */
        $data = $values;

        $this->handle(new Envelope(new ModifyProductMessage(['uuid' => $parentId], $data), [new EnableFlushStamp()]));
    }
}
