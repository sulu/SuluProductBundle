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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Serializer\Normalizer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Infrastructure\Symfony\Serializer\Normalizer\ProductFamilyNormalizer;

#[CoversClass(ProductFamilyNormalizer::class)]
class ProductFamilyNormalizerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
    }

    private function normalizer(): ProductFamilyNormalizer
    {
        return new ProductFamilyNormalizer($this->attributeGroupRepository->reveal());
    }

    private function attributeWithId(int $id, string $key): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);
        $attribute->setKey($key);

        return $attribute;
    }

    public function testSupportsNormalizationReturnsTrueForProductFamily(): void
    {
        $normalizer = $this->normalizer();
        $this->attributeGroupRepository->findAll()->willReturn([]);

        $this->assertTrue($normalizer->supportsNormalization(new ProductFamily()));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        $supported = $this->normalizer()->getSupportedTypes(null);

        $this->assertArrayHasKey(ProductFamilyInterface::class, $supported);
        $this->assertTrue($supported[ProductFamilyInterface::class]);
    }

    public function testNormalizeWithNoTranslationAndNoAttributes(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');

        $this->attributeGroupRepository->findAll()->willReturn([]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame('test-uuid', $result['id']);
        $this->assertSame('', $result['name']);
        $this->assertNull($result['description']);
        $this->assertNull($result['externalIdentifier']);
        $this->assertSame([], $result['attributes']);
    }

    public function testNormalizeWithTranslation(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');
        $translation = new ProductFamilyTranslation($family, 'en', 'Apparel');
        $translation->setDescription('Clothing family');
        $family->addTranslation($translation);

        $this->attributeGroupRepository->findAll()->willReturn([]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame('Apparel', $result['name']);
        $this->assertSame('Clothing family', $result['description']);
    }

    public function testNormalizePrePopulatesAllGroupAttributesAsDisabled(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');

        $group = new AttributeGroup();
        $attr9 = $this->attributeWithId(9, 'color');
        $attr10 = $this->attributeWithId(10, 'size');
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attr9));
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attr10));

        $this->attributeGroupRepository->findAll()->willReturn([$group]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame([
            9 => ['enabled' => false, 'required' => false, 'variant' => false],
            10 => ['enabled' => false, 'required' => false, 'variant' => false],
        ], $result['attributes']);
    }

    public function testNormalizeOverridesEnabledFamilyAttributes(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');

        $group = new AttributeGroup();
        $attr9 = $this->attributeWithId(9, 'color');
        $attr10 = $this->attributeWithId(10, 'size');
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attr9));
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attr10));

        $familyAttribute = new ProductFamilyAttribute($family, $attr9);
        $familyAttribute->setRequired(true);
        $family->addFamilyAttribute($familyAttribute);

        $this->attributeGroupRepository->findAll()->willReturn([$group]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame([
            9 => ['enabled' => true, 'required' => true, 'variant' => false],
            10 => ['enabled' => false, 'required' => false, 'variant' => false],
        ], $result['attributes']);
    }

    public function testNormalizeExposesVariantFlag(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');

        $group = new AttributeGroup();
        $attr9 = $this->attributeWithId(9, 'color');
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attr9));

        $familyAttribute = new ProductFamilyAttribute($family, $attr9);
        $familyAttribute->setVariant(true);
        $family->addFamilyAttribute($familyAttribute);

        $this->attributeGroupRepository->findAll()->willReturn([$group]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame([
            9 => ['enabled' => true, 'required' => false, 'variant' => true],
        ], $result['attributes']);
    }

    public function testNormalizeWithMissingLocaleUsesEmptyString(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');
        $translation = new ProductFamilyTranslation($family, 'en', 'English Name');
        $family->addTranslation($translation);

        $this->attributeGroupRepository->findAll()->willReturn([]);

        $result = $this->normalizer()->normalize($family, null, []);

        $this->assertSame('', $result['name']);
    }

    public function testNormalizeUseFallbackTranslationWhenLocaleNotFound(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');
        $family->setDefaultLocale('de');
        $de = new ProductFamilyTranslation($family, 'de', 'Fallback Name');
        $family->addTranslation($de);

        $this->attributeGroupRepository->findAll()->willReturn([]);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame('Fallback Name', $result['name']);
    }
}
