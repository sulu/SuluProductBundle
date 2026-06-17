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

namespace Sulu\Product\Tests\Unit\Controller\Admin;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Product\Application\Message\RemoveAttributeGroupMessage;
use Sulu\Product\Domain\Exception\AttributeGroupNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\AttributeGroupController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class AttributeGroupControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    /** @var ObjectProphecy<MessageBusInterface> */
    private ObjectProphecy $messageBus;

    /** @var ObjectProphecy<FieldDescriptorFactoryInterface> */
    private ObjectProphecy $fieldDescriptorFactory;

    /** @var ObjectProphecy<DoctrineListBuilderFactoryInterface> */
    private ObjectProphecy $listBuilderFactory;

    /** @var ObjectProphecy<RestHelperInterface> */
    private ObjectProphecy $restHelper;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->restHelper = $this->prophesize(RestHelperInterface::class);
    }

    private function createController(): AttributeGroupController
    {
        return new AttributeGroupController(
            $this->attributeGroupRepository->reveal(),
            $this->messageBus->reveal(),
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
            $this->restHelper->reveal(),
        );
    }

    public function testGetSecurityContextReturnsCorrectString(): void
    {
        $controller = $this->createController();

        $this->assertSame('sulu.product.attribute_groups', $controller->getSecurityContext());
    }

    public function testGetActionReturns404WhenNotFound(): void
    {
        $this->attributeGroupRepository->findOneBy(['uuid' => 'non-existent-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'non-existent-uuid');

        $this->assertSame(404, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetActionReturnsSerializedGroupWithNoTranslation(): void
    {
        $attributeGroup = new AttributeGroup();
        $attributeGroup->setUuid('test-uuid-1234');

        $this->attributeGroupRepository->findOneBy(['uuid' => 'test-uuid-1234'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeGroup);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'test-uuid-1234');

        $this->assertSame(200, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertSame('test-uuid-1234', $data['id']);
        $this->assertSame('', $data['name']);
        $this->assertNull($data['description']);
        $this->assertSame([], $data['attributes']);
    }

    public function testGetActionReturnsSerializedGroupWithTranslation(): void
    {
        $attributeGroup = new AttributeGroup();
        $attributeGroup->setUuid('test-uuid-5678');
        $translation = new AttributeGroupTranslation($attributeGroup, 'en', 'My Group');
        $translation->setDescription('A description');
        $attributeGroup->addTranslation($translation);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'test-uuid-5678'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeGroup);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'test-uuid-5678');

        $this->assertSame(200, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertSame('test-uuid-5678', $data['id']);
        $this->assertSame('My Group', $data['name']);
        $this->assertSame('A description', $data['description']);
        $this->assertSame([], $data['attributes']);
    }

    public function testGetActionReturnsSerializedGroupAttributes(): void
    {
        $attributeGroup = new AttributeGroup();
        $attributeGroup->setUuid('test-uuid-with-attrs');

        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $groupAttr = new AttributeGroupAttribute($attributeGroup, $attribute);
        $groupAttr->setPosition(0);
        $attributeGroup->addGroupAttribute($groupAttr);

        $this->attributeGroupRepository->findOneBy(['uuid' => 'test-uuid-with-attrs'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeGroup);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'test-uuid-with-attrs');

        $this->assertSame(200, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $attributes = $data['attributes'];
        $this->assertIsArray($attributes);
        /** @var list<array{attribute: string}> $attributes */
        $this->assertCount(1, $attributes);
        $this->assertSame('attr-uuid-1', $attributes[0]['attribute']);
        $this->assertArrayNotHasKey('required', $attributes[0]);
    }

    public function testPostActionReturns409OnUniqueConstraintViolation(): void
    {
        $exception = $this->createMock(UniqueConstraintViolationException::class);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $controller = $this->createController();
        $request = new Request(['locale' => 'en'], ['name' => 'Colors']);
        $response = $controller->postAction($request);

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testPutActionReturns409OnUniqueConstraintViolation(): void
    {
        $exception = $this->createMock(UniqueConstraintViolationException::class);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $controller = $this->createController();
        $request = new Request(['locale' => 'en'], ['name' => 'Colors']);
        $response = $controller->putAction($request, 'some-uuid');

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testDeleteActionReturns204OnSuccess(): void
    {
        $dummyMessage = new RemoveAttributeGroupMessage('delete-uuid');
        $responseEnvelope = new Envelope($dummyMessage, [new HandledStamp(null, 'handler')]);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->willReturn($responseEnvelope);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->deleteAction($request, 'delete-uuid');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testDeleteActionReturns404WhenAttributeGroupNotFound(): void
    {
        $exception = new AttributeGroupNotFoundException(['uuid' => 'missing-uuid']);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->deleteAction($request, 'missing-uuid');

        $this->assertSame(404, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetLocaleUsesQueryParameter(): void
    {
        $controller = $this->createController();
        $request = new Request(['locale' => 'de']);

        $this->assertSame('de', $controller->getLocale($request));
    }

    public function testGetLocaleUsesRequestLocaleAsFallback(): void
    {
        $controller = $this->createController();
        $request = new Request();
        $request->setLocale('fr');

        $this->assertSame('fr', $controller->getLocale($request));
    }
}
