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

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Domain\Exception\InvalidProductFamilyAttributesException;
use Sulu\Product\Domain\Exception\ProductFamilyHasProductsException;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class ProductFamilyController implements SecuredControllerInterface
{
    use HandleTrait;

    public const SECURITY_CONTEXT = 'sulu.product.product_families';

    public function __construct(
        private ProductFamilyRepositoryInterface $productFamilyRepository,
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
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ProductFamilyInterface::RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductFamilyInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            ProductFamilyInterface::RESOURCE_KEY,
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
        $family = $this->productFamilyRepository->findOneBy(['uuid' => $id]);

        if (null === $family) {
            return new JsonResponse(['detail' => 'ProductFamily not found.'], 404);
        }

        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize($family, null, ['locale' => $locale]);

        return new JsonResponse($data);
    }

    public function postAction(Request $request): Response
    {
        try {
            $message = new CreateProductFamilyMessage($this->getData($request));
        } catch (InvalidProductFamilyAttributesException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        }

        try {
            /** @var ProductFamilyInterface $family */
            $family = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['detail' => 'ProductFamily already exists.'], 409);
        }

        $locale = $this->getLocale($request);
        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize($family, null, ['locale' => $locale]);

        return new JsonResponse($data, 201);
    }

    public function putAction(Request $request, string $id): Response
    {
        try {
            $message = new ModifyProductFamilyMessage(['uuid' => $id], $this->getData($request));
        } catch (InvalidProductFamilyAttributesException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 422);
        }

        try {
            /** @var ProductFamilyInterface $family */
            $family = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException) {
            return new JsonResponse(['detail' => 'ProductFamily already exists.'], 409);
        } catch (ProductFamilyNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        }

        $locale = $this->getLocale($request);
        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize($family, null, ['locale' => $locale]);

        return new JsonResponse($data);
    }

    public function deleteAction(Request $request, string $id): Response
    {
        try {
            $this->handle(new Envelope(new RemoveProductFamilyMessage($id), [new EnableFlushStamp()]));
        } catch (ProductFamilyNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        } catch (ProductFamilyHasProductsException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 409);
        }

        return new Response('', 204);
    }

    public function getSecurityContext(): string
    {
        return self::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @return array{
     *   locale: string,
     *   name: string,
     *   description: string|null,
     *   attributes: list<array{id: string, required: bool, variantSpecific: bool}>,
     * }
     */
    private function getData(Request $request): array
    {
        /** @var string|null $description */
        $description = $request->request->get('description');

        return [
            'name' => (string) $request->request->get('name', ''),
            'description' => $description,
            'attributes' => $this->extractAttributes($request),
            'locale' => $this->getLocale($request),
        ];
    }

    /**
     * @return list<array{id: string, required: bool, variantSpecific: bool}>
     *
     * @throws InvalidProductFamilyAttributesException if "attributes" is submitted non-empty but no
     *                                                 entry parses, which would otherwise be
     *                                                 indistinguishable from "remove everything"
     */
    private function extractAttributes(Request $request): array
    {
        $rawAttributes = $request->request->all('attributes');
        $attributes = [];

        foreach ($rawAttributes as $attribute) {
            if (!\is_array($attribute) || !isset($attribute['id']) || !\is_string($attribute['id'])) {
                continue;
            }

            $attributes[] = [
                'id' => $attribute['id'],
                'required' => (bool) ($attribute['required'] ?? false),
                'variantSpecific' => (bool) ($attribute['variantSpecific'] ?? false),
            ];
        }

        if ([] !== $rawAttributes && [] === $attributes) {
            throw new InvalidProductFamilyAttributesException();
        }

        return $attributes;
    }
}
