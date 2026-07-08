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
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\CreateProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RemoveProductMessage;
use Sulu\Product\Application\Message\RemoveProductTranslationMessage;
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
 */
final class ProductDetailsController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        MessageBusInterface $messageBus,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
        private NormalizerInterface $normalizer,
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
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductInterface::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($this->normalizer->normalize(
            $listRepresentation->toArray(),
            'json',
        ));
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);
        $product = $this->productRepository->findOneBy(['uuid' => $id]);

        if (null === $product) {
            return new JsonResponse(['detail' => 'Product not found.'], 404);
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($product, null, ['locale' => $locale]);

        return new JsonResponse($normalized);
    }

    public function postAction(Request $request): Response
    {
        $data = $this->getData($request);
        $message = new CreateProductMessage($data);
        /** @var ProductInterface $product */
        $product = $this->handle(new Envelope($message, [new EnableFlushStamp()]));

        $locale = $this->getLocale($request);
        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($product, null, ['locale' => $locale]);

        return new JsonResponse($normalized, 201);
    }

    public function putAction(Request $request, string $id): Response
    {
        $data = $this->getData($request);
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

        $product = $this->productRepository->findOneBy(['uuid' => $id]);
        if (null === $product) {
            return new JsonResponse(['detail' => 'Product not found.'], 404); // @codeCoverageIgnore
        }

        $locale = $this->getLocale($request);
        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($product, null, ['locale' => $locale]);

        return new JsonResponse($normalized);
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

    public function getSecurityContext(): string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @return CreateProductMessageData
     */
    private function getData(Request $request): array
    {
        /** @var CreateProductMessageData $data */
        $data = \array_replace(
            $request->request->all(),
            ['locale' => $this->getLocale($request)],
        );

        return $data;
    }
}
