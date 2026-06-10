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
use Sulu\Product\Application\Message\CreateAttributeGroupMessage;
use Sulu\Product\Application\Message\ModifyAttributeGroupMessage;
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\AttributeGroupAttributeInterface;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
final class AttributeGroupController implements SecuredControllerInterface
{
    use HandleTrait;

    public const SECURITY_CONTEXT = 'sulu.product.attribute_groups';

    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
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
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(AttributeGroupInterface::RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(AttributeGroupInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            AttributeGroupInterface::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($listRepresentation->toArray());
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);
        $group = $this->attributeGroupRepository->findOneBy(['uuid' => $id]);

        if (null === $group) {
            return new JsonResponse(['detail' => 'AttributeGroup not found.'], 404);
        }

        return new JsonResponse($this->serializeAttributeGroup($group, $locale));
    }

    public function postAction(Request $request): Response
    {
        $data = $this->getData($request);
        $message = new CreateAttributeGroupMessage(
            $data['locale'],
            $data['name'],
            $data['description'],
            $data['attributes'],
        );

        try {
            /** @var AttributeGroupInterface $group */
            $group = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => 'AttributeGroup already exists.'], 409);
        }

        return new JsonResponse($this->serializeAttributeGroup($group, $this->getLocale($request)), 201);
    }

    public function putAction(Request $request, string $id): Response
    {
        $data = $this->getData($request);
        $message = new ModifyAttributeGroupMessage(
            $id,
            $data['locale'],
            $data['name'],
            $data['description'],
            $data['attributes'],
        );

        try {
            /** @var AttributeGroupInterface $group */
            $group = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => 'AttributeGroup already exists.'], 409);
        } catch (AttributeGroupNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        }

        return new JsonResponse($this->serializeAttributeGroup($group, $this->getLocale($request)));
    }

    public function deleteAction(Request $request, string $id): Response
    {
        $message = new RemoveAttributeGroupMessage($id);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (AttributeGroupNotFoundException $e) {
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
     *   attributes: list<array{attribute: string, position: int}>,
     * }
     */
    private function getData(Request $request): array
    {
        /** @var array{
         *     locale: string,
         *     name: string,
         *     description: string|null,
         *     attributes: list<array{attribute: string, position: int}>,
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
    private function serializeAttributeGroup(AttributeGroupInterface $group, string $locale): array
    {
        $translation = $group->getTranslation($locale);

        return [
            'id' => $group->getUuid() ?? '',
            'name' => $translation?->getName() ?? '',
            'description' => $translation?->getDescription(),
            'externalIdentifier' => $group->getExternalIdentifier(),
            'attributes' => \array_map(
                static fn (AttributeGroupAttributeInterface $ga) => [
                    'type' => 'attribute_item',
                    'attribute' => $ga->getAttribute()->getUuid(),
                ],
                $group->getGroupAttributes(),
            ),
        ];
    }
}
