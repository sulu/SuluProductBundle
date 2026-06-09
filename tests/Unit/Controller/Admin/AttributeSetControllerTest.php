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
use Sulu\Product\Application\Message\RemoveAttributeSetMessage;
use Sulu\Product\Domain\Exception\AttributeSetNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeSet;
use Sulu\Product\Domain\Model\AttributeSetAttribute;
use Sulu\Product\Domain\Model\AttributeSetTranslation;
use Sulu\Product\Domain\Repository\AttributeSetRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\AttributeSetController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class AttributeSetControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeSetRepositoryInterface> */
    private ObjectProphecy $attributeSetRepository;

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
        $this->attributeSetRepository = $this->prophesize(AttributeSetRepositoryInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->restHelper = $this->prophesize(RestHelperInterface::class);
    }

    private function createController(): AttributeSetController
    {
        return new AttributeSetController(
            $this->attributeSetRepository->reveal(),
            $this->messageBus->reveal(),
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
            $this->restHelper->reveal(),
        );
    }

    public function testGetSecurityContextReturnsCorrectString(): void
    {
        $controller = $this->createController();

        $this->assertSame('sulu.product.attribute_sets', $controller->getSecurityContext());
    }

    public function testGetActionReturns404WhenNotFound(): void
    {
        $this->attributeSetRepository->findOneBy(['uuid' => 'non-existent-uuid'])
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

    public function testGetActionReturnsSerializedSetWithNoTranslation(): void
    {
        $attributeSet = new AttributeSet();
        $attributeSet->setUuid('test-uuid-1234');

        $this->attributeSetRepository->findOneBy(['uuid' => 'test-uuid-1234'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeSet);

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

    public function testGetActionReturnsSerializedSetWithTranslation(): void
    {
        $attributeSet = new AttributeSet();
        $attributeSet->setUuid('test-uuid-5678');
        $translation = new AttributeSetTranslation($attributeSet, 'en', 'My Set');
        $translation->setDescription('A description');
        $attributeSet->addTranslation($translation);

        $this->attributeSetRepository->findOneBy(['uuid' => 'test-uuid-5678'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeSet);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'test-uuid-5678');

        $this->assertSame(200, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $this->assertSame('test-uuid-5678', $data['id']);
        $this->assertSame('My Set', $data['name']);
        $this->assertSame('A description', $data['description']);
        $this->assertSame([], $data['attributes']);
    }

    public function testGetActionReturnsSerializedSetAttributes(): void
    {
        $attributeSet = new AttributeSet();
        $attributeSet->setUuid('test-uuid-with-attrs');

        $attribute = new Attribute();
        $attribute->setUuid('attr-uuid-1');
        $attribute->setKey('color');
        $attribute->setType('text');

        $setAttr = new AttributeSetAttribute($attributeSet, $attribute);
        $setAttr->setRequired(true);
        $setAttr->setPosition(0);
        $attributeSet->addSetAttribute($setAttr);

        $this->attributeSetRepository->findOneBy(['uuid' => 'test-uuid-with-attrs'])
            ->shouldBeCalledOnce()
            ->willReturn($attributeSet);

        $controller = $this->createController();
        $request = new Request(['locale' => 'en']);
        $response = $controller->getAction($request, 'test-uuid-with-attrs');

        $this->assertSame(200, $response->getStatusCode());

        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        /** @var array<string, mixed> $data */
        $attributes = $data['attributes'];
        $this->assertIsArray($attributes);
        /** @var list<array{attribute: string, required: bool}> $attributes */
        $this->assertCount(1, $attributes);
        $this->assertSame('attr-uuid-1', $attributes[0]['attribute']);
        $this->assertTrue($attributes[0]['required']);
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
        $dummyMessage = new RemoveAttributeSetMessage('delete-uuid');
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

    public function testDeleteActionReturns404WhenAttributeSetNotFound(): void
    {
        $exception = new AttributeSetNotFoundException(['uuid' => 'missing-uuid']);

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
