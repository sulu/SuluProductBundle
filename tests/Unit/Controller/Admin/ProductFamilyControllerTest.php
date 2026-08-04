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
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\RemoveProductFamilyMessage;
use Sulu\Product\Domain\Exception\ProductFamilyHasProductsException;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;
use Sulu\Product\UserInterface\Controller\Admin\ProductFamilyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

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

    /** @var ObjectProphecy<NormalizerInterface> */
    private ObjectProphecy $normalizer;

    protected function setUp(): void
    {
        $this->productFamilyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->messageBus = $this->prophesize(MessageBusInterface::class);
        $this->fieldDescriptorFactory = $this->prophesize(FieldDescriptorFactoryInterface::class);
        $this->listBuilderFactory = $this->prophesize(DoctrineListBuilderFactoryInterface::class);
        $this->restHelper = $this->prophesize(RestHelperInterface::class);
        $this->normalizer = $this->prophesize(NormalizerInterface::class);
    }

    private function createController(): ProductFamilyController
    {
        return new ProductFamilyController(
            $this->productFamilyRepository->reveal(),
            $this->messageBus->reveal(),
            $this->fieldDescriptorFactory->reveal(),
            $this->listBuilderFactory->reveal(),
            $this->restHelper->reveal(),
            $this->normalizer->reveal(),
        );
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

    public function testGetActionReturnsNormalizedFamily(): void
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid-1');

        $this->productFamilyRepository->findOneBy(['uuid' => 'family-uuid-1'])
            ->shouldBeCalledOnce()
            ->willReturn($family);

        $this->normalizer->normalize($family, null, ['locale' => 'en'])
            ->shouldBeCalledOnce()
            ->willReturn(['id' => 'family-uuid-1', 'name' => '', 'description' => null, 'attributes' => []]);

        $response = $this->createController()->getAction(new Request(['locale' => 'en']), 'family-uuid-1');

        $this->assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('family-uuid-1', $data['id']);
        $this->assertSame('', $data['name']);
        $this->assertNull($data['description']);
    }

    public function testPostActionReturns201AndExtractsAttributes(): void
    {
        $family = new ProductFamily();
        $family->setUuid('created-uuid');

        $this->messageBus->dispatch(
            Argument::that(function(Envelope $envelope): bool {
                $message = $envelope->getMessage();

                // Nested attributes map is read straight from the request; non-array entries are ignored.
                return $message instanceof CreateProductFamilyMessage
                    && [
                        9 => ['enabled' => true, 'required' => false, 'variantSpecific' => false],
                        10 => ['enabled' => false, 'required' => false, 'variantSpecific' => false],
                    ] === $message->getAttributes();
            }),
            Argument::any(),
        )
            ->shouldBeCalledOnce()
            ->willReturn($this->handledEnvelope($family));

        $this->normalizer->normalize($family, null, ['locale' => 'en'])
            ->shouldBeCalledOnce()
            ->willReturn(['id' => 'created-uuid', 'name' => 'New Family', 'attributes' => []]);

        $request = new Request(
            ['locale' => 'en'],
            [
                'name' => 'New Family',
                'description' => null,
                'attributes' => [
                    9 => ['enabled' => true, 'required' => false],
                    10 => ['enabled' => false],
                    11 => 'not-an-array',
                ],
            ],
        );

        $response = $this->createController()->postAction($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertSame('created-uuid', $data['id']);
    }

    public function testPostActionExtractsVariantFlag(): void
    {
        $family = new ProductFamily();
        $family->setUuid('created-uuid');

        $this->messageBus->dispatch(
            Argument::that(function(Envelope $envelope): bool {
                $message = $envelope->getMessage();

                return $message instanceof CreateProductFamilyMessage
                    && [9 => ['enabled' => true, 'required' => false, 'variantSpecific' => true]] === $message->getAttributes();
            }),
            Argument::any(),
        )
            ->shouldBeCalledOnce()
            ->willReturn($this->handledEnvelope($family));

        $this->normalizer->normalize($family, null, ['locale' => 'en'])
            ->shouldBeCalledOnce()
            ->willReturn(['id' => 'created-uuid', 'name' => 'New Family', 'attributes' => []]);

        $request = new Request(
            ['locale' => 'en'],
            [
                'name' => 'New Family',
                'description' => null,
                'attributes' => [
                    9 => ['enabled' => true, 'required' => false, 'variantSpecific' => true],
                ],
            ],
        );

        $response = $this->createController()->postAction($request);

        $this->assertSame(201, $response->getStatusCode());
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

        $this->normalizer->normalize($family, null, ['locale' => 'en'])
            ->shouldBeCalledOnce()
            ->willReturn(['id' => 'put-uuid', 'name' => 'Y', 'attributes' => []]);

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

    public function testDeleteActionReturns409WhenProductsStillAssigned(): void
    {
        $exception = new ProductFamilyHasProductsException('in-use-uuid');

        $this->messageBus->dispatch(Argument::any(), Argument::any())
            ->shouldBeCalledOnce()
            ->will(function() use ($exception): never {
                throw $exception;
            });

        $response = $this->createController()->deleteAction(new Request(['locale' => 'en']), 'in-use-uuid');

        $this->assertSame(409, $response->getStatusCode());
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

    /**
     * @return array{ObjectProphecy<DoctrineListBuilder>, ObjectProphecy<DoctrineFieldDescriptorInterface>}
     */
    private function makeListBuilder(): array
    {
        /** @var ObjectProphecy<DoctrineFieldDescriptorInterface> $idDescriptor */
        $idDescriptor = $this->prophesize(DoctrineFieldDescriptorInterface::class);

        /** @var ObjectProphecy<DoctrineListBuilder> $listBuilder */
        $listBuilder = $this->prophesize(DoctrineListBuilder::class);
        $listBuilder->setIdField($idDescriptor->reveal())->shouldBeCalled();
        $listBuilder->setParameter('locale', 'en')->shouldBeCalled();
        $listBuilder->getCurrentPage()->willReturn(1);
        $listBuilder->getLimit()->willReturn(10);
        $listBuilder->count()->willReturn(1);

        $this->fieldDescriptorFactory->getFieldDescriptors('product_families')
            ->willReturn(['id' => $idDescriptor->reveal()]);
        $this->listBuilderFactory->create(ProductFamilyInterface::class)
            ->willReturn($listBuilder->reveal());
        $this->restHelper->initializeListBuilder($listBuilder->reveal(), ['id' => $idDescriptor->reveal()])->shouldBeCalled();

        return [$listBuilder, $idDescriptor];
    }

    public function testCgetActionSkipsRowsWithNonStringId(): void
    {
        [$listBuilder] = $this->makeListBuilder();
        $listBuilder->execute()->willReturn([
            ['id' => 123, 'name' => null],
        ]);

        $this->productFamilyRepository->findOneBy(Argument::any())->shouldNotBeCalled();

        $response = $this->createController()->cgetAction(new Request(['locale' => 'en']));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testCgetActionSkipsRowsWhenFamilyNotFound(): void
    {
        [$listBuilder] = $this->makeListBuilder();
        $listBuilder->execute()->willReturn([
            ['id' => 'missing-uuid', 'name' => null],
        ]);

        $this->productFamilyRepository->findOneBy(['uuid' => 'missing-uuid'])->willReturn(null);
        $this->normalizer->normalize(Argument::any(), 'json')->willReturnArgument(0);

        $response = $this->createController()->cgetAction(new Request(['locale' => 'en']));

        $this->assertSame(200, $response->getStatusCode());
        $data = \json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        $families = $embedded['product_families'];
        $this->assertIsArray($families);
        $first = $families[0];
        $this->assertIsArray($first);
        $this->assertNull($first['name']);
    }
}
