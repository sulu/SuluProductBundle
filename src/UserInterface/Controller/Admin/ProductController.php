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
use Sulu\Component\Rest\ListBuilder\ListBuilderInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\CopyLocaleProductMessage;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Application\Message\RemoveProductTranslationMessage;
use Sulu\Product\Application\Message\RestoreProductVersionMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Exception\RequiredProductAttributeMissingException;
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
use Webmozart\Assert\InvalidArgumentException;

/**
 * @internal
 *
 * @phpstan-import-type CreateProductMessageData from CreateProductMessage
 * @phpstan-import-type ModifyProductMessageData from ModifyProductMessage
 */
final class ProductController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        MessageBusInterface $messageBus,
        private NormalizerInterface $normalizer,
        private ContentManagerInterface $contentManager,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
    ) {
        $this->messageBus = $messageBus;
    }

    public function cgetAction(Request $request): Response
    {
        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ProductInterface::RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        // the publish indicator is rendered from these two, whether or not the list requests them
        $listBuilder->addSelectField($fieldDescriptors['published']);
        $listBuilder->addSelectField($fieldDescriptors['publishedState']);
        $listBuilder->setParameter('locale', $this->getLocale($request));
        // Variants are edited through their parent's variants tab and must not show up in the main list.
        $listBuilder->where(
            $fieldDescriptors['type'],
            ProductInterface::TYPE_VARIANT,
            ListBuilderInterface::WHERE_COMPARATOR_UNEQUAL,
        );

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductInterface::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        /** @var array{_embedded: array{products: array<int, array<string, mixed>>}} $list */
        $list = $listRepresentation->toArray();
        foreach ($list['_embedded'][ProductInterface::RESOURCE_KEY] as &$item) {
            // the admin expects a boolean, the list builder returns the raw workflow place
            $item['publishedState'] = WorkflowInterface::WORKFLOW_PLACE_PUBLISHED === ($item['publishedState'] ?? null);
        }

        return new JsonResponse($this->normalizer->normalize(
            $list,
            'json',
        ));
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);
        $dimensionAttributes = [
            'locale' => $locale,
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        try {
            $product = $this->productRepository->getOneBy(
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

        try {
            $dimensionContent = $this->contentManager->resolve($product, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return new JsonResponse(['template' => ProductInterface::TEMPLATE_TYPE]);
        }

        $normalizedContent = $this->contentManager->normalize($dimensionContent);

        return new JsonResponse($this->normalizer->normalize(
            $normalizedContent,
            'json',
            ['sulu_admin' => true, 'sulu_admin_product' => true, 'sulu_admin_product_content' => true],
        ));
    }

    public function postAction(Request $request): Response
    {
        /** @var CreateProductMessageData $data */
        $data = \array_replace(
            $request->request->all(),
            ['locale' => $this->getLocale($request)],
        );

        $message = new CreateProductMessage($data);
        /** @var ProductInterface $product */
        $product = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        $response = $this->getAction($request, $product->getUuid());
        $response->setStatusCode(201);

        return $response;
    }

    public function putAction(Request $request, string $id): Response
    {
        /** @var ModifyProductMessageData $data */
        $data = \array_replace(
            $request->request->all(),
            ['locale' => $this->getLocale($request)],
        );

        $message = new ModifyProductMessage(['uuid' => $id], $data);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (ProductNotFoundException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (RequiredProductAttributeMissingException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse(['detail' => 'Invalid attribute value provided.'], 400);
        }

        $this->handleAction($request, $id);

        return $this->getAction($request, $id);
    }

    public function deleteAction(Request $request, string $id): Response
    {
        $deleteLocale = $request->query->getBoolean('deleteLocale', false);
        $locale = $this->getLocale($request);

        if ($deleteLocale) {
            $message = new RemoveProductTranslationMessage(['uuid' => $id], $locale);
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } else {
            $message = new RemoveProductMessage(['uuid' => $id], $locale);
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        }

        return new Response('', 204);
    }

    public function postTriggerAction(Request $request, string $id): Response
    {
        $this->handleAction($request, $id);

        return $this->getAction($request, $id);
    }

    public function getVersionsAction(Request $request, string $id): JsonResponse
    {
        $locale = $this->getLocale($request);

        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors('products_versions');
        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductInterface::class);
        $listBuilder->setParameter('locale', $locale);
        $listBuilder->setParameter('id', $id);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $listBuilder->sort($fieldDescriptors['version'], 'DESC');
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $result = $listBuilder->execute();
        $listRepresentation = new PaginatedRepresentation(
            $result,
            'products_versions',
            $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse(
            $this->normalizer->normalize($listRepresentation->toArray(), 'json'),
        );
    }

    public function getSecurityContext(): string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    private function handleAction(Request $request, string $uuid): void
    {
        $action = $request->query->get('action');

        if (!$action || 'draft' === $action) {
            return;
        }

        if ('copy_locale' === $action) {
            $message = new CopyLocaleProductMessage(
                ['uuid' => $uuid],
                (string) $request->query->get('src'),
                (string) $request->query->get('dest'),
            );
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return;
        }

        if ('restore' === $action) {
            $version = (int) $request->query->get('version');
            if (!$version) {
                throw new \InvalidArgumentException('The "version" query parameter is required for restoring a version.');
            }
            $message = new RestoreProductVersionMessage(
                ['uuid' => $uuid],
                $version,
                $this->getLocale($request),
                $request->query->all(),
            );
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));

            return;
        }

        $message = new ApplyWorkflowTransitionProductMessage(
            ['uuid' => $uuid],
            $this->getLocale($request),
            $action,
        );
        $this->handle(new Envelope($message, [new EnableFlushStamp()]));
    }
}
