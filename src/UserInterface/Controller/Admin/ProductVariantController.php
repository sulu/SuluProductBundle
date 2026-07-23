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
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
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
        $listBuilder->setParameter('locale', $locale);
        $listBuilder->where($fieldDescriptors['parent'], $parentId);

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductInterface::LIST_KEY_VARIANTS,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize($listRepresentation->toArray(), 'json'));
    }

    public function getAction(Request $request, string $parentId, string $id): Response
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

        return new JsonResponse($data);
    }

    public function postAction(Request $request, string $parentId): Response
    {
        $parent = $this->getParentOrFail($parentId);

        if (!$parent->isProductWithVariants()) {
            return new JsonResponse([
                'detail' => \sprintf('Product "%s" cannot have variants.', $parentId),
            ], 409);
        }

        $data = $this->getCreateData($request, $parentId, $parent);
        $message = new CreateProductMessage($data);
        /** @var ProductInterface $variant */
        $variant = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        $response = $this->getAction($request, $parentId, $variant->getUuid());
        $response->setStatusCode(201);

        return $response;
    }

    public function putAction(Request $request, string $parentId, string $id): Response
    {
        $parent = $this->getParentOrFail($parentId);
        $this->assertVariantOwnedByParent($parentId, $id);

        $message = new ModifyProductMessage(['uuid' => $id], $this->getModifyData($request, $parentId, $parent));

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (RequiredProductAttributeMissingException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['detail' => 'Invalid attribute value provided.'], 400);
        }

        return $this->getAction($request, $parentId, $id);
    }

    public function deleteAction(Request $request, string $parentId, string $id): Response
    {
        $this->assertVariantOwnedByParent($parentId, $id);

        $locale = $this->getLocale($request);

        $message = new RemoveProductMessage(['uuid' => $id], $locale);
        $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        return new Response('', 204);
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
     * @return CreateProductMessageData
     */
    private function getCreateData(Request $request, string $parentId, ProductInterface $parent): array
    {
        /** @var CreateProductMessageData $data */
        $data = $this->buildData($request, $parentId, $parent);

        return $data;
    }

    /**
     * @return ModifyProductMessageData
     */
    private function getModifyData(Request $request, string $parentId, ProductInterface $parent): array
    {
        /** @var ModifyProductMessageData $data */
        $data = $this->buildData($request, $parentId, $parent);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildData(Request $request, string $parentId, ProductInterface $parent): array
    {
        $requestData = $request->request->all();

        $data = \array_replace(
            $requestData,
            [
                'locale' => $this->getLocale($request),
                'type' => ProductInterface::TYPE_VARIANT,
                'parent' => $parentId,
                'productFamily' => $this->resolveFamilyUuid($parent),
            ],
        );

        if (isset($data['attributes']) && \is_array($data['attributes'])) {
            /** @var array<int, mixed> $attributes */
            $attributes = $data['attributes'];
            $data['attributes'] = $this->stripInheritedAttributes($parent, $attributes);
        }

        return $data;
    }

    private function resolveFamilyUuid(ProductInterface $parent): string
    {
        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $parent->getUuid()]);
        $familyUuid = $family?->getUuid();

        if (null === $familyUuid) {
            throw new \RuntimeException(\sprintf(
                'Parent product "%s" has no product family assigned; a variant cannot be created or modified without one.',
                $parent->getUuid(),
            ));
        }

        return $familyUuid;
    }

    /**
     * Only per-variant axis values may be persisted on the variant's own dimension content;
     * shared/inherited attribute values are stripped even if a client submits them directly.
     *
     * @param array<int, mixed> $attributes
     *
     * @return array<int, mixed>
     */
    private function stripInheritedAttributes(ProductInterface $parent, array $attributes): array
    {
        $family = $this->productFamilyRepository->findOneBy(['productUuid' => $parent->getUuid()]);
        if (null === $family) {
            return $attributes;
        }

        foreach ($family->getFamilyAttributes() as $familyAttribute) {
            if (!$familyAttribute->isVariant()) {
                unset($attributes[$familyAttribute->getAttribute()->getId()]);
            }
        }

        return $attributes;
    }
}
