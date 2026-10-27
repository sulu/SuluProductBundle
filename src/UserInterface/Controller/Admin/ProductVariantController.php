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

use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Exception\UnavailableContentTransitionException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Domain\Value\ProductFamilyAttributeModeEnum;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeFieldFactory;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\InvalidArgumentException;

/**
 * Admin CRUD for variants (children with `parent` set) of a product.
 *
 * @internal
 *
 * @phpstan-import-type CreateProductMessageData from CreateProductMessage
 * @phpstan-import-type ModifyProductMessageData from ModifyProductMessage
 */
final class ProductVariantController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        MessageBusInterface $messageBus,
        private NormalizerInterface $normalizer,
        private ContentManagerInterface $contentManager,
        private ProductFamilyRepositoryInterface $productFamilyRepository,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
        private ProductAttributeValueRepositoryInterface $productAttributeValueRepository,
        private AttributeTypeRegistry $attributeTypeRegistry,
    ) {
        $this->messageBus = $messageBus;
    }

    public function cgetAction(Request $request, string $parentId): Response
    {
        $locale = $this->getLocale($request);

        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ProductInterface::LIST_KEY_VARIANTS);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        // the publish and ghost indicators are rendered from these, whether or not the list requests them
        $listBuilder->addSelectField($fieldDescriptors['published']);
        $listBuilder->addSelectField($fieldDescriptors['publishedState']);
        $listBuilder->addSelectField($fieldDescriptors['ghostLocale']);
        $listBuilder->setParameter('locale', $locale);
        $listBuilder->where($fieldDescriptors['parent'], $parentId);

        if (null === $request->query->get('sortBy')) {
            $listBuilder->sort($fieldDescriptors['position']);
            $listBuilder->sort($fieldDescriptors['id']);
        }

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductInterface::LIST_KEY_VARIANTS,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        /** @var array{_embedded: array{product_variants: array<int, array<string, mixed>>}} $list */
        $list = $listRepresentation->toArray();
        foreach ($list['_embedded'][ProductInterface::LIST_KEY_VARIANTS] as &$item) {
            // the admin expects a boolean, the list builder returns the raw workflow place
            $item['publishedState'] = WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === ($item['publishedState'] ?? null);
        }

        return new JsonResponse($this->normalizer->normalize($list, 'json'));
    }

    public function getAction(Request $request, string $parentId, string $id): Response
    {
        return $this->renderVariant($request, $parentId, $id);
    }

    /**
     * @param ProductFamilyInterface|null $family the parent's family when the caller already loaded it
     */
    private function renderVariant(Request $request, string $parentId, string $id, ?ProductFamilyInterface $family = null): Response
    {
        $locale = $this->getLocale($request);
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        try {
            $variant = $this->productRepository->getOneBy(
                ['uuid' => $id],
                [
                    ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                        'dimensionAttributes' => $dimensionAttributes,
                        'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    ],
                ],
            );
        } catch (ProductNotFoundException $e) {
            $exception = new EntityNotFoundException($e->getModel(), $id, $e);

            return new JsonResponse($exception->toArray(), 404);
        }

        if ($parentId !== $variant->getParent()?->getUuid()) {
            $exception = new EntityNotFoundException(ProductInterface::class, $id);

            return new JsonResponse($exception->toArray(), 404);
        }

        try {
            $dimensionContent = $this->contentManager->resolve($variant, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return new JsonResponse(['template' => ProductInterface::TEMPLATE_TYPE]);
        }

        $normalizedContent = $this->contentManager->normalize($dimensionContent);

        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize(
            $normalizedContent,
            'json',
            ['sulu_admin' => true, 'sulu_admin_product' => true, 'sulu_admin_product_content' => true],
        );

        return new JsonResponse($this->applyInheritedAttributes(
            $data,
            $parentId,
            $family ?? $this->findFamily($parentId),
            $locale,
        ));
    }

    public function postAction(Request $request, string $parentId): Response
    {
        $parent = $this->getParentOrFail($parentId);

        if (!$parent->isType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS)) {
            return new JsonResponse([
                'detail' => \sprintf('Product "%s" cannot have variants.', $parentId),
            ], 409);
        }

        $family = $this->resolveFamily($parent);

        /** @var CreateProductMessageData $data */
        $data = $this->buildData($request, $parentId, $family);

        $message = new CreateProductMessage($data);

        try {
            /** @var ProductInterface $variant */
            $variant = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (RequiredProductAttributeMissingException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['detail' => 'Invalid attribute value provided.'], 400);
        }

        $response = $this->renderVariant($request, $parentId, $variant->getUuid(), $family);
        $response->setStatusCode(201);

        return $response;
    }

    public function putAction(Request $request, string $parentId, string $id): Response
    {
        $parent = $this->getParentOrFail($parentId);
        $this->assertVariantOwnedByParent($parentId, $id);

        $family = $this->resolveFamily($parent);

        /** @var ModifyProductMessageData $data */
        $data = $this->buildData($request, $parentId, $family);

        $message = new ModifyProductMessage(['uuid' => $id], $data);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (RequiredProductAttributeMissingException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['detail' => 'Invalid attribute value provided.'], 400);
        }

        return $this->renderVariant($request, $parentId, $id, $family);
    }

    public function deleteAction(Request $request, string $parentId, string $id): Response
    {
        $this->assertVariantOwnedByParent($parentId, $id);

        $locale = $this->getLocale($request);

        $message = new RemoveProductMessage(['uuid' => $id], $locale);
        $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        return new Response('', 204);
    }

    /**
     * Publishes or unpublishes a single variant; its parent is never touched.
     */
    public function postTriggerAction(Request $request, string $parentId, string $id): Response
    {
        $this->assertVariantOwnedByParent($parentId, $id);

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

        return $this->getAction($request, $parentId, $id);
    }

    public function getSecurityContext(): string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    private function getParentOrFail(string $parentId): ProductInterface
    {
        try {
            return $this->productRepository->getOneBy(['uuid' => $parentId]);
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }
    }

    /**
     * 404s a `{parentId}`/`{id}` pair for two unrelated products instead of silently acting on
     * a variant of a different parent.
     */
    private function assertVariantOwnedByParent(string $parentId, string $id): ProductInterface
    {
        try {
            $variant = $this->productRepository->getOneBy(['uuid' => $id]);
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        if ($parentId !== $variant->getParent()?->getUuid()) {
            throw new NotFoundHttpException(\sprintf(
                'Variant "%s" does not belong to parent "%s".',
                $id,
                $parentId,
            ));
        }

        return $variant;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Request $request, string $parentId, ProductFamilyInterface $family): array
    {
        $requestData = $request->request->all();

        $data = \array_replace(
            $requestData,
            [
                'locale' => $this->getLocale($request),
                'type' => ProductInterface::TYPE_VARIANT,
                'parent' => $parentId,
                'productFamily' => $family->getUuid(),
            ],
        );

        // `attributes` is always sent: the mapper skips an absent key, which would let an
        // untouched required axis attribute through.
        $attributes = $data['attributes'] ?? [];
        /** @var array<string, mixed> $attributes */
        $attributes = \is_array($attributes) ? $attributes : [];
        $data['attributes'] = $this->stripInheritedAttributes($family, $attributes);

        return $data;
    }

    /**
     * An overridable attribute the variant has no value for shows the parent's value, and every
     * overridable attribute carries an override flag the admin renders as a toggle; saving reads
     * the flag back, see {@see self::stripInheritedAttributes()}.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function applyInheritedAttributes(array $data, string $parentId, ?ProductFamilyInterface $family, string $locale): array
    {
        if (null === $family) {
            return $data;
        }

        $attributes = \is_array($data['attributes'] ?? null) ? $data['attributes'] : [];
        $inherited = [];
        $overridable = false;

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if (ProductFamilyAttributeModeEnum::OVERRIDABLE !== $familyAttribute->getMode()) {
                continue;
            }

            $overridable = true;
            $uuid = $familyAttribute->getAttribute()->getUuid();
            $own = $attributes[$uuid] ?? null;
            $overridden = null !== $own && '' !== $own && [] !== $own;
            $attributes[AttributeFieldFactory::overrideKey($uuid)] = $overridden;

            if (!$overridden) {
                $inherited[] = $uuid;
            }
        }

        if (!$overridable) {
            return $data;
        }

        if ([] !== $inherited) {
            $attributes = \array_replace($attributes, $this->loadParentValues($parentId, $inherited, $locale));
        }

        $data['attributes'] = $attributes;

        return $data;
    }

    /**
     * The parent's draft values of the given attributes in one query, wire-formatted like the
     * normalizer does; a localized value wins over an unlocalized one.
     *
     * @param list<string> $attributeUuids
     *
     * @return array<string, mixed> attribute uuid => value
     */
    private function loadParentValues(string $parentId, array $attributeUuids, string $locale): array
    {
        $values = $this->productAttributeValueRepository->findBy([
            'product' => $parentId,
            'attributeUuids' => $attributeUuids,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
            'locale' => $locale,
        ]);

        $unlocalized = [];
        $localized = [];
        foreach ($values as $value) {
            $attribute = $value->getAttribute();
            if (!$this->attributeTypeRegistry->has($attribute->getType())) {
                continue;
            }

            $wireValue = $this->attributeTypeRegistry->get($attribute->getType())->readValue($value);
            if (null === $wireValue) {
                continue;
            }

            if (null === $value->getProductDimensionContent()->getLocale()) {
                $unlocalized[$attribute->getUuid()] = $wireValue;
            } else {
                $localized[$attribute->getUuid()] = $wireValue;
            }
        }

        return \array_replace($unlocalized, $localized);
    }

    private function resolveFamily(ProductInterface $parent): ProductFamilyInterface
    {
        $family = $this->findFamily($parent->getUuid());

        if (null === $family) {
            throw new \RuntimeException(\sprintf(
                'Parent product "%s" has no product family assigned; a variant cannot be created or modified without one.',
                $parent->getUuid(),
            ));
        }

        return $family;
    }

    private function findFamily(string $parentId): ?ProductFamilyInterface
    {
        return $this->productFamilyRepository->findOneBy(
            ['productUuid' => $parentId],
            [ProductFamilyRepositoryInterface::SELECT_FAMILY_ATTRIBUTES => true],
        );
    }

    /**
     * Only per-variant axis values may be persisted on the variant's own dimension content;
     * shared/inherited attribute values are stripped even if a client submits them directly.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    private function stripInheritedAttributes(ProductFamilyInterface $family, array $attributes): array
    {
        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            $uuid = $familyAttribute->getAttribute()->getUuid();

            if (ProductFamilyAttributeModeEnum::SHARED === $familyAttribute->getMode()) {
                unset($attributes[$uuid]);
            }

            // A submitted flag decides: off clears the override (an empty value deletes it), on keeps the value.
            $flagKey = AttributeFieldFactory::overrideKey($uuid);
            if (ProductFamilyAttributeModeEnum::OVERRIDABLE === $familyAttribute->getMode()
                && \array_key_exists($flagKey, $attributes)
                && !$attributes[$flagKey]
            ) {
                $attributes[$uuid] = null;
            }
            unset($attributes[$flagKey]);
        }

        return $attributes;
    }
}
