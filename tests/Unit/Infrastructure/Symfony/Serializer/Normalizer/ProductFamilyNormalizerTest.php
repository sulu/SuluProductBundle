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
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Infrastructure\Symfony\Serializer\Normalizer\ProductFamilyNormalizer;

#[CoversClass(ProductFamilyNormalizer::class)]
class ProductFamilyNormalizerTest extends TestCase
{
    private function normalizer(): ProductFamilyNormalizer
    {
        return new ProductFamilyNormalizer();
    }

    private function attributeWithUuid(string $uuid, string $key): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid($uuid);
        $attribute->setKey($key);

        return $attribute;
    }

    public function testSupportsNormalizationReturnsTrueForProductFamily(): void
    {
        $normalizer = $this->normalizer();

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

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame('Apparel', $result['name']);
        $this->assertSame('Clothing family', $result['description']);
    }

    public function testNormalizeReturnsOnlyTheFamilysOwnAttributesAsAList(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');

        $attributeColor = $this->attributeWithUuid('uuid-1', 'color');
        $attributeSize = $this->attributeWithUuid('uuid-2', 'size');

        $familyAttributeColor = new ProductFamilyAttribute($family, $attributeColor);
        $familyAttributeColor->setRequired(true);
        $family->addFamilyAttribute($familyAttributeColor);

        $familyAttributeSize = new ProductFamilyAttribute($family, $attributeSize);
        $familyAttributeSize->setVariantSpecific(true);
        $family->addFamilyAttribute($familyAttributeSize);

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame([
            ['id' => 'uuid-1', 'required' => true, 'variantSpecific' => false],
            ['id' => 'uuid-2', 'required' => false, 'variantSpecific' => true],
        ], $result['attributes']);
    }

    public function testNormalizeWithMissingLocaleUsesEmptyString(): void
    {
        $family = new ProductFamily();
        $family->setUuid('test-uuid');
        $translation = new ProductFamilyTranslation($family, 'en', 'English Name');
        $family->addTranslation($translation);

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

        $result = $this->normalizer()->normalize($family, null, ['locale' => 'en']);

        $this->assertSame('Fallback Name', $result['name']);
    }
}
