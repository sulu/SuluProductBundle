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

namespace Sulu\Product\Tests\Unit\Application\Mapper;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Application\Mapper\ProductFamilyMapper;
use Sulu\Product\Application\Message\CreateProductFamilyMessage;
use Sulu\Product\Application\Message\ModifyProductFamilyMessage;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

class ProductFamilyMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeRepositoryInterface> */
    private ObjectProphecy $attributeRepository;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->prophesize(AttributeRepositoryInterface::class);
    }

    private function createMapper(): ProductFamilyMapper
    {
        return new ProductFamilyMapper($this->attributeRepository->reveal());
    }

    private function attributeWithUuid(string $uuid): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid($uuid);
        $attribute->setKey('attr-' . $uuid);

        return $attribute;
    }

    public function testMapProductFamilyDataCreatesTranslationWhenMissing(): void
    {
        $family = new ProductFamily();

        $message = new CreateProductFamilyMessage([
            'locale' => 'en',
            'name' => 'Family',
            'description' => 'Desc',
        ]);

        $this->createMapper()->mapProductFamilyData($family, $message);

        $translation = $family->getTranslation('en');
        self::assertNotNull($translation);
        self::assertSame('Family', $translation->getName());
        self::assertSame('Desc', $translation->getDescription());
        self::assertSame('en', $family->getDefaultLocale());
    }

    public function testMapProductFamilyDataUpdatesExistingTranslation(): void
    {
        $family = new ProductFamily();
        $translation = new ProductFamilyTranslation($family, 'en', 'Old');
        $translation->setDescription('Old desc');
        $family->addTranslation($translation);

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            ['locale' => 'en', 'name' => 'New', 'description' => 'New desc'],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        self::assertSame('New', $translation->getName());
        self::assertSame('New desc', $translation->getDescription());
    }

    public function testMapAttributesAddsNewAttributeByUuid(): void
    {
        $family = new ProductFamily();
        $attribute = $this->attributeWithUuid('uuid-1');

        $this->attributeRepository->findOneBy(['uuid' => 'uuid-1'])->willReturn($attribute);

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            [
                'locale' => 'en',
                'name' => 'Family',
                'attributes' => [
                    ['id' => 'uuid-1', 'required' => true, 'variantSpecific' => false],
                ],
            ],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        $familyAttributes = $family->getFamilyAttributes();
        self::assertCount(1, $familyAttributes);
        self::assertSame($attribute, $familyAttributes[0]->getAttribute());
        self::assertTrue($familyAttributes[0]->isRequired());
    }

    public function testMapAttributesUpdatesExistingAttributeByUuidWithoutRefetching(): void
    {
        $family = new ProductFamily();
        $attribute = $this->attributeWithUuid('uuid-1');
        $existing = new ProductFamilyAttribute($family, $attribute);
        $existing->setRequired(false);
        $family->addFamilyAttribute($existing);

        $this->attributeRepository->findOneBy(['uuid' => 'uuid-1'])->shouldNotBeCalled();

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            [
                'locale' => 'en',
                'name' => 'Family',
                'attributes' => [
                    ['id' => 'uuid-1', 'required' => true, 'variantSpecific' => false],
                ],
            ],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        self::assertCount(1, $family->getFamilyAttributes());
        self::assertTrue($existing->isRequired());
        self::assertSame($existing, $family->getFamilyAttributes()[0]);
    }

    public function testMapAttributesRemovesAttributeMissingFromSubmittedList(): void
    {
        $family = new ProductFamily();
        $existing = new ProductFamilyAttribute($family, $this->attributeWithUuid('uuid-1'));
        $family->addFamilyAttribute($existing);

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            ['locale' => 'en', 'name' => 'Family'],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        self::assertCount(0, $family->getFamilyAttributes());
    }

    public function testMapAttributesSkipsUuidUnknownToRepository(): void
    {
        $family = new ProductFamily();

        $this->attributeRepository->findOneBy(['uuid' => 'missing-uuid'])->willReturn(null);

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            [
                'locale' => 'en',
                'name' => 'Family',
                'attributes' => [
                    ['id' => 'missing-uuid', 'required' => false, 'variantSpecific' => false],
                ],
            ],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        self::assertCount(0, $family->getFamilyAttributes());
    }

    public function testMapAttributesPersistsVariantFlag(): void
    {
        $family = new ProductFamily();
        $attribute = $this->attributeWithUuid('uuid-1');

        $this->attributeRepository->findOneBy(['uuid' => 'uuid-1'])->willReturn($attribute);

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            [
                'locale' => 'en',
                'name' => 'Family',
                'attributes' => [
                    ['id' => 'uuid-1', 'required' => false, 'variantSpecific' => true],
                ],
            ],
        );

        $this->createMapper()->mapProductFamilyData($family, $message);

        $familyAttributes = $family->getFamilyAttributes();
        self::assertCount(1, $familyAttributes);
        self::assertTrue($familyAttributes[0]->isVariantSpecific());
    }

    public function testMapAttributesAddsUpdatesAndRemovesByUuid(): void
    {
        $keptAttribute = $this->prophesize(AttributeInterface::class);
        $keptAttribute->getUuid()->willReturn('uuid-kept');
        $addedAttribute = $this->prophesize(AttributeInterface::class);
        $addedAttribute->getUuid()->willReturn('uuid-added');

        $family = new ProductFamily();
        $existing = new ProductFamilyAttribute($family, $keptAttribute->reveal());
        $existing->setRequired(false);
        $family->addFamilyAttribute($existing);

        $removedAttribute = $this->prophesize(AttributeInterface::class);
        $removedAttribute->getUuid()->willReturn('uuid-removed');
        $removed = new ProductFamilyAttribute($family, $removedAttribute->reveal());
        $family->addFamilyAttribute($removed);

        $repository = $this->prophesize(AttributeRepositoryInterface::class);
        $repository->findOneBy(['uuid' => 'uuid-kept'])->shouldNotBeCalled();
        $repository->findOneBy(['uuid' => 'uuid-added'])->willReturn($addedAttribute->reveal());

        $message = new ModifyProductFamilyMessage(
            ['uuid' => 'family-uuid'],
            [
                'locale' => 'en',
                'name' => 'Family',
                'attributes' => [
                    ['id' => 'uuid-kept', 'required' => true, 'variantSpecific' => false],
                    ['id' => 'uuid-added', 'required' => false, 'variantSpecific' => true],
                ],
            ]
        );

        (new ProductFamilyMapper($repository->reveal()))->mapProductFamilyData($family, $message);

        // ArrayCollection keeps whatever integer keys elements were inserted under, so a
        // remove-then-add leaves a gap; reindex before asserting by position.
        $familyAttributes = \array_values($family->getFamilyAttributes());

        self::assertCount(2, $familyAttributes);
        // "kept" means the same row was updated in place, not removed and re-created from the repository.
        self::assertSame($existing, $familyAttributes[0]);
        self::assertTrue($familyAttributes[0]->isRequired());
        self::assertFalse($familyAttributes[0]->isVariantSpecific());
        self::assertFalse($familyAttributes[1]->isRequired());
        self::assertTrue($familyAttributes[1]->isVariantSpecific());
    }
}
