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
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductFamilyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ProductFamilyControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $productFamilyRepository;

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
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->restHelper = $this->prophesize(RestHelperInterface::class);
    }

    private function createController(): ProductFamilyController
    {
        return new ProductFamilyController(
            $this->productFamilyRepository->reveal(),
            $this->messageBus->reveal(),
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
            $this->restHelper->reveal(),
        );
    }

    private function attributeWithId(int $id, string $key): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);
        $attribute->setKey($key);

        return $attribute;
    }

    /**
     * @return Envelope handled envelope wrapping the given family
     */
    private function handledEnvelope(ProductFamilyInterface $family): Envelope
    {
        return new Envelope(new \stdClass(), [new HandledStamp($family, 'handler')]);
    }

    public function testGetSecurityContextReturnsCorrectString(): void
    {
        $this->assertSame('sulu.product.product_families', $this->createController()->getSecurityContext());
    }

    public function testGetActionReturns404WhenNotFound(): void
    {
        $this->productFamilyRepository->findOneBy(['uuid' => 'non-existent-uuid'])
            ->shouldBeCalledOnce()
            ->willReturn(null);

        $response = $this->createController()->getAction(new Request(['locale' => 'en']), 'non-existent-uuid');

        $this->assertSame(404, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetActionSerializesFamilyWithoutTranslation(): void
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid-1');

        $this->productFamilyRepository->findOneBy(['uuid' => 'family-uuid-1'])
            ->shouldBeCalledOnce()
            ->willReturn($family);

        $response = $this->createController()->getAction(new Request(['locale' => 'en']), 'family-uuid-1');

        $this->assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('family-uuid-1', $data['id']);
        $this->assertSame('', $data['name']);
        $this->assertNull($data['description']);
    }

    public function testGetActionSerializesFamilyWithTranslationAndAttributes(): void
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid-2');
        $translation = new ProductFamilyTranslation($family, 'en', 'Apparel');
        $translation->setDescription('Clothing family');
        $family->addTranslation($translation);

        $familyAttribute = new ProductFamilyAttribute($family, $this->attributeWithId(9, 'size'));
        $familyAttribute->setRequired(true);
        $family->addFamilyAttribute($familyAttribute);

        $this->productFamilyRepository->findOneBy(['uuid' => 'family-uuid-2'])
            ->shouldBeCalledOnce()
            ->willReturn($family);

        $response = $this->createController()->getAction(new Request(['locale' => 'en']), 'family-uuid-2');

        $this->assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('Apparel', $data['name']);
        $this->assertSame('Clothing family', $data['description']);
        $this->assertTrue($data['attribute_9_enabled']);
        $this->assertTrue($data['attribute_9_required']);
    }

    public function testPostActionReturns201AndExtractsFamilyAttributes(): void
    {
        $family = new ProductFamily();
        $family->setUuid('created-uuid');

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->willReturn($this->handledEnvelope($family));

        // 'attribute_5_enabled' => true (kept), 'attribute_6_enabled' => false (skipped),
        // plus non-matching keys (name/description) exercise the regex-skip branch.
        $request = new Request(
            ['locale' => 'en'],
            [
                'name' => 'New Family',
                'description' => null,
                'attribute_5_enabled' => true,
                'attribute_5_required' => false,
                'attribute_6_enabled' => false,
            ],
        );

        $response = $this->createController()->postAction($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('created-uuid', $data['id']);
    }

    public function testPostActionReturns409OnUniqueConstraintViolation(): void
    {
        $exception = $this->createMock(UniqueConstraintViolationException::class);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $response = $this->createController()->postAction(new Request(['locale' => 'en'], ['name' => 'X']));

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testPutActionReturns200OnSuccess(): void
    {
        $family = new ProductFamily();
        $family->setUuid('put-uuid');

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->willReturn($this->handledEnvelope($family));

        $response = $this->createController()->putAction(new Request(['locale' => 'en'], ['name' => 'Y']), 'put-uuid');

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPutActionReturns409OnUniqueConstraintViolation(): void
    {
        $exception = $this->createMock(UniqueConstraintViolationException::class);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $response = $this->createController()->putAction(new Request(['locale' => 'en'], ['name' => 'X']), 'some-uuid');

        $this->assertSame(409, $response->getStatusCode());
    }

    public function testPutActionReturns404WhenNotFound(): void
    {
        $exception = new ProductFamilyNotFoundException(['uuid' => 'missing']);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $response = $this->createController()->putAction(new Request(['locale' => 'en'], ['name' => 'X']), 'missing');

        $this->assertSame(404, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testDeleteActionReturns204OnSuccess(): void
    {
        $envelope = new Envelope(new RemoveProductFamilyMessage('delete-uuid'), [new HandledStamp(null, 'handler')]);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->willReturn($envelope);

        $response = $this->createController()->deleteAction(new Request(['locale' => 'en']), 'delete-uuid');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
    }

    public function testDeleteActionReturns404WhenNotFound(): void
    {
        $exception = new ProductFamilyNotFoundException(['uuid' => 'missing']);

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $response = $this->createController()->deleteAction(new Request(['locale' => 'en']), 'missing');

        $this->assertSame(404, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('detail', $data);
    }

    public function testGetLocaleUsesQueryParameter(): void
    {
        $this->assertSame('de', $this->createController()->getLocale(new Request(['locale' => 'de'])));
    }

    public function testGetLocaleUsesRequestLocaleAsFallback(): void
    {
        $request = new Request();
        $request->setLocale('fr');

        $this->assertSame('fr', $this->createController()->getLocale($request));
    }
}
