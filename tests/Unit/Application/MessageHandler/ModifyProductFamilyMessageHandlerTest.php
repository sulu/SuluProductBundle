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
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Application\MessageHandler\ModifyProductFamilyMessageHandler;
use Sulu\Product\Domain\Exception\ProductFamilyNotFoundException;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductFamilyRepositoryInterface;

class ModifyProductFamilyMessageHandlerTest extends TestCase
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

    private function createHandler(): ModifyProductFamilyMessageHandler
    {
        return new ModifyProductFamilyMessageHandler(
            $this->familyRepository->reveal(),
            new ProductFamilyMapper($this->attributeRepository->reveal()),
        );
    }

    /**
     * @param array<int, array{enabled: bool, required: bool}> $attributes
     */
    private function message(string $uuid, string $name, ?string $description = null, array $attributes = []): ModifyProductFamilyMessage
    {
        $data = ['locale' => 'en', 'name' => $name];
        if (null !== $description) {
            $data['description'] = $description;
        }
        if ([] !== $attributes) {
            $data['attributes'] = $attributes;
        }

        return new ModifyProductFamilyMessage(['uuid' => $uuid], $data);
    }

    private function attributeWithId(int $id): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);

        return $attribute;
    }

    public function testThrowsNotFoundWhenMissing(): void
    {
        $this->familyRepository->getOneBy(['uuid' => 'missing'])
            ->willThrow(new ProductFamilyNotFoundException(['uuid' => 'missing']));

        $this->expectException(ProductFamilyNotFoundException::class);
        ($this->createHandler())($this->message('missing', 'N'));
    }

    public function testCreatesTranslationWhenMissing(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();

        ($this->createHandler())($this->message('f', 'Name', 'Desc'));

        $translation = $family->getTranslation('en');
        $this->assertNotNull($translation);
        $this->assertSame('Name', $translation->getName());
        $this->assertSame('Desc', $translation->getDescription());
    }

    public function testUpdatesExistingTranslation(): void
    {
        $family = new ProductFamily();
        $translation = new ProductFamilyTranslation($family, 'en', 'Old');
        $translation->setDescription('Old desc');
        $family->addTranslation($translation);

        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();

        ($this->createHandler())($this->message('f', 'New', 'New desc'));

        $this->assertSame('New', $translation->getName());
        $this->assertSame('New desc', $translation->getDescription());
    }

    public function testAddsNewFamilyAttribute(): void
    {
        $family = new ProductFamily();
        $attribute = $this->attributeWithId(7);

        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();
        $this->attributeRepository->findOneBy(['id' => 7])->willReturn($attribute);

        ($this->createHandler())($this->message('f', 'Name', null, [7 => ['enabled' => true, 'required' => true]]));

        $familyAttributes = $family->getFamilyAttributes();
        $this->assertCount(1, $familyAttributes);
        $this->assertSame($attribute, $familyAttributes[0]->getAttribute());
        $this->assertTrue($familyAttributes[0]->isRequired());
    }

    public function testUpdatesRequiredOnExistingFamilyAttribute(): void
    {
        $family = new ProductFamily();
        $attribute = $this->attributeWithId(7);
        $existing = new ProductFamilyAttribute($family, $attribute);
        $existing->setRequired(false);
        $family->addFamilyAttribute($existing);

        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();
        $this->attributeRepository->findOneBy(['id' => 7])->shouldNotBeCalled();

        ($this->createHandler())($this->message('f', 'Name', null, [7 => ['enabled' => true, 'required' => true]]));

        $this->assertCount(1, $family->getFamilyAttributes());
        $this->assertTrue($existing->isRequired());
    }

    public function testRemovesStaleFamilyAttributes(): void
    {
        $family = new ProductFamily();
        $keep = new ProductFamilyAttribute($family, $this->attributeWithId(1));
        $remove = new ProductFamilyAttribute($family, $this->attributeWithId(2));
        $family->addFamilyAttribute($keep)->addFamilyAttribute($remove);

        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();

        ($this->createHandler())($this->message('f', 'Name', null, [1 => ['enabled' => true, 'required' => false]]));

        $familyAttributes = $family->getFamilyAttributes();
        $this->assertCount(1, $familyAttributes);
        $this->assertSame(1, $familyAttributes[0]->getAttribute()->getId());
    }

    public function testRemovesFamilyAttributeWhenSubmittedAsDisabled(): void
    {
        $family = new ProductFamily();
        $existing = new ProductFamilyAttribute($family, $this->attributeWithId(3));
        $family->addFamilyAttribute($existing);

        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();
        $this->attributeRepository->findOneBy(['id' => 3])->shouldNotBeCalled();

        ($this->createHandler())($this->message('f', 'Name', null, [3 => ['enabled' => false, 'required' => false]]));

        $this->assertCount(0, $family->getFamilyAttributes());
    }

    public function testSkipsMissingAttributeOnAdd(): void
    {
        $family = new ProductFamily();
        $this->familyRepository->getOneBy(['uuid' => 'f'])->willReturn($family);
        $this->familyRepository->save($family)->shouldBeCalledOnce();
        $this->attributeRepository->findOneBy(['id' => 99])->willReturn(null);

        ($this->createHandler())($this->message('f', 'Name', null, [99 => ['enabled' => true, 'required' => false]]));

        $this->assertCount(0, $family->getFamilyAttributes());
    }
}
