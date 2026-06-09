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
use Sulu\Product\Application\Message\CreateAttributeSetMessage;
use Sulu\Product\Application\Message\ModifyAttributeSetMessage;
use Sulu\Product\Application\Message\RemoveAttributeSetMessage;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Model\AttributeSetAttributeInterface;
use Sulu\Product\Domain\Model\AttributeSetInterface;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class AttributeSetController implements SecuredControllerInterface
{
    use HandleTrait;

    public const SECURITY_CONTEXT = 'sulu.product.attribute_sets';

    public function __construct(
        private AttributeSetRepositoryInterface $attributeSetRepository,
        MessageBusInterface $messageBus,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
    ) {
        $this->messageBus = $messageBus;
    }

    public function cgetAction(Request $request): Response
    {
        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(AttributeSetInterface::RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(AttributeSetInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            AttributeSetInterface::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($listRepresentation->toArray());
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);
        $set = $this->attributeSetRepository->findOneBy(['uuid' => $id]);

        if (null === $set) {
            return new JsonResponse(['detail' => 'AttributeSet not found.'], 404);
        }

        return new JsonResponse($this->serializeAttributeSet($set, $locale));
    }

    public function postAction(Request $request): Response
    {
        $data = $this->getData($request);
        $message = new CreateAttributeSetMessage(
            $data['locale'],
            $data['name'],
            $data['description'],
            $data['attributes'],
        );

        try {
            /** @var AttributeSetInterface $set */
            $set = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => 'AttributeSet already exists.'], 409);
        }

        return new JsonResponse($this->serializeAttributeSet($set, $this->getLocale($request)), 201);
    }

    public function putAction(Request $request, string $id): Response
    {
        $data = $this->getData($request);
        $message = new ModifyAttributeSetMessage(
            $id,
            $data['locale'],
            $data['name'],
            $data['description'],
            $data['attributes'],
        );

        try {
            /** @var AttributeSetInterface $set */
            $set = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => 'AttributeSet already exists.'], 409);
        } catch (AttributeSetNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        }

        return new JsonResponse($this->serializeAttributeSet($set, $this->getLocale($request)));
    }

    public function deleteAction(Request $request, string $id): Response
    {
        $message = new RemoveAttributeSetMessage($id);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (AttributeSetNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
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

    /** @return array{
     *   locale: string,
     *   name: string,
     *   description: string|null,
     *   attributes: list<array{attribute: string, required?: bool, position: int}>,
     * }
     */
    private function getData(Request $request): array
    {
        /** @var array{
         *     locale: string,
         *     name: string,
         *     description: string|null,
         *     attributes: list<array{attribute: string, required?: bool, position: int}>,
         * } $data */
        $data = [
            'name' => $request->request->get('name', ''),
            'description' => $request->request->get('description'),
            'attributes' => $request->request->all('attributes'),
            'locale' => $this->getLocale($request),
        ];

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeAttributeSet(AttributeSetInterface $set, string $locale): array
    {
        $translation = $set->getTranslation($locale);

        return [
            'id' => $set->getUuid() ?? '',
            'name' => $translation?->getName() ?? '',
            'description' => $translation?->getDescription(),
            'externalIdentifier' => $set->getExternalIdentifier(),
            'attributes' => \array_map(
                static fn (AttributeSetAttributeInterface $sa) => [
                    'type' => 'attribute_item',
                    'attribute' => $sa->getAttribute()->getUuid(),
                    'required' => $sa->getRequired(),
                ],
                $set->getSetAttributes(),
            ),
        ];
    }
}
