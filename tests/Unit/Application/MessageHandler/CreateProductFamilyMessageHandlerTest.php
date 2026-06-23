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

namespace Sulu\Product\Tests\Unit\Application\MessageHandler;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\ProductFamilyMapper;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\MessageHandler\CreateProductFamilyMessageHandler;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

class CreateProductFamilyMessageHandlerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<ProductFamilyRepositoryInterface> */
    private ObjectProphecy $familyRepository;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->familyRepository = $this->prophesize(ProductFamilyRepositoryInterface::class);
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    private function createHandler(): CreateProductFamilyMessageHandler
    {
        return new CreateProductFamilyMessageHandler(
            $this->familyRepository->reveal(),
            [new ProductFamilyMapper($this->attributeRepository->reveal())],
        );
    }

    public function testCreateFamilyWithTranslationAndAttributes(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->create()->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();

        $attribute = new Attribute(new AttributeGroup());
        $this->attributeRepository->findOneBy(['id' => 7])->willReturn($attribute);

        $handler = $this->createHandler();
        $result = ($handler)(new CreateProductFamilyMessage([
            'locale' => 'en',
            'name' => 'My Family',
            'description' => 'desc',
            'attributes' => [7 => ['enabled' => true, 'required' => true]],
        ]));

        $this->assertSame($family, $result);
        $translation = $family->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('My Family', $translation->getName());
        $this->assertSame('desc', $translation->getDescription());

        $familyAttributes = $family->getFamilyAttributes();
        $this->assertCount(1, $familyAttributes);
        $this->assertSame($attribute, $familyAttributes[0]->getAttribute());
        $this->assertTrue($familyAttributes[0]->isRequired());
    }

    public function testCreateSkipsMissingAttribute(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->create()->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();
        $this->attributeRepository->findOneBy(['id' => 99])->willReturn(null);

        $handler = $this->createHandler();
        ($handler)(new CreateProductFamilyMessage([
            'locale' => 'en',
            'name' => 'My Family',
            'attributes' => [99 => ['enabled' => true, 'required' => false]],
        ]));

        $this->assertCount(0, $family->getFamilyAttributes());
    }
}
