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
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Application\Message\ModifyAttributeMessage;
use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Domain\Exception\AttributeNotFoundException;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @phpstan-import-type CreateAttributeMessageData from CreateAttributeMessage
 *
 * @internal
 */
final class AttributeController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
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
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(AttributeInterface::RESOURCE_KEY);

        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(AttributeInterface::class);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);
        $listBuilder->setParameter('locale', $this->getLocale($request));

        $listRepresentation = new PaginatedRepresentation(
            $listBuilder->execute(),
            AttributeInterface::RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse($listRepresentation->toArray());
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);
        $attribute = $this->attributeRepository->findOneBy(['uuid' => $id]);

        if (null === $attribute) {
            return new JsonResponse(['detail' => 'Attribute not found.'], 404);
        }

        return new JsonResponse($this->serializeAttribute($attribute, $locale));
    }

    public function postAction(Request $request): Response
    {
        $data = $this->getData($request);
        $message = new CreateAttributeMessage($data);

        try {
            /** @var AttributeInterface $attribute */
            $attribute = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => \sprintf('Attribute with key "%s" already exists.', $data['key'])], 409);
        }

        return new JsonResponse($this->serializeAttribute($attribute, $this->getLocale($request)), 201);
    }

    public function putAction(Request $request, string $id): Response
    {
        $data = $this->getData($request);
        $message = new ModifyAttributeMessage(['uuid' => $id], $data);

        try {
            /** @var AttributeInterface $attribute */
            $attribute = $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['detail' => \sprintf('Attribute with key "%s" already exists.', $data['key'])], 409);
        } catch (AttributeNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        }

        return new JsonResponse($this->serializeAttribute($attribute, $this->getLocale($request)));
    }

    public function deleteAction(Request $request, string $id): Response
    {
        $message = new RemoveAttributeMessage(['uuid' => $id]);

        try {
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        } catch (AttributeNotFoundException $e) {
            return new JsonResponse(['detail' => $e->getMessage()], 404);
        }

        return new Response('', 204);
    }

    public function getSecurityContext(): string
    {
        return AttributeAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @return CreateAttributeMessageData
     */
    private function getData(Request $request): array
    {
        /** @var CreateAttributeMessageData $data */
        $data = \array_replace(
            $request->request->all(),
            ['locale' => $this->getLocale($request)],
        );

        return $data;
    }

    /** @return array<string, mixed> */
    private function serializeAttribute(?AttributeInterface $attribute, string $locale): array
    {
        if (null === $attribute) {
            return []; // @codeCoverageIgnore
        }

        $translation = $attribute->getTranslation($locale);

        return [
            'id' => $attribute->getUuid(),
            'key' => $attribute->getKey(),
            'type' => $attribute->getType(),
            'name' => $translation?->getName() ?? '',
            'description' => $translation?->getDescription(),
            'options' => \array_map(
                fn (AttributeOptionInterface $option) => [
                    'type' => 'option',
                    'key' => $option->getKey(),
                    'name' => $option->getTranslation($locale)?->getName() ?? '',
                ],
                $attribute->getOptions(),
            ),
        ];
    }
}
