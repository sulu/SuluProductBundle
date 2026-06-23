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
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\AttributeTypeRegistry;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyAttribute;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Model\ProductTranslation;
use Sulu\Product\Infrastructure\Symfony\Serializer\Normalizer\ProductNormalizer;

#[CoversClass(ProductNormalizer::class)]
class ProductNormalizerTest extends TestCase
{
    /**
     * @param list<AttributeTypeInterface> $types
     */
    private function registry(array $types = []): AttributeTypeRegistry
    {
        return new AttributeTypeRegistry($types ?: [new NumberAttributeType(), new TextAttributeType()]);
    }

    private function normalizer(?AttributeTypeRegistry $registry = null): ProductNormalizer
    {
        return new ProductNormalizer($registry ?? $this->registry());
    }

    private function attributeWithId(int $id, string $key, string $type = AttributeInterface::TYPE_NUMBER): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);
        $attribute->setKey($key);
        $attribute->setType($type);

        return $attribute;
    }

    public function testSupportsNormalizationReturnsTrueForProduct(): void
    {
        $family = new ProductFamily();
        $product = new Product($family);

        $this->assertTrue($this->normalizer()->supportsNormalization($product));
        $this->assertFalse($this->normalizer()->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypes(): void
    {
        $supported = $this->normalizer()->getSupportedTypes(null);

        $this->assertArrayHasKey(ProductInterface::class, $supported);
        $this->assertTrue($supported[ProductInterface::class]);
    }

    public function testNormalizeBasicFields(): void
    {
        $family = new ProductFamily();
        $family->setUuid('family-uuid');
        $product = new Product($family, 'product-uuid');
        $product->setCode('PROD-001');

        $result = $this->normalizer()->normalize($product, null, ['locale' => 'en']);

        $this->assertSame('product-uuid', $result['id']);
        $this->assertSame('', $result['name']);
        $this->assertSame('PROD-001', $result['code']);
        $this->assertNull($result['externalIdentifier']);
        $this->assertSame('family-uuid', $result['productFamily']);
        $this->assertSame([], $result['attributes']);
    }

    public function testNormalizeWithTranslation(): void
    {
        $family = new ProductFamily();
        $product = new Product($family, 'product-uuid');
        $translation = new ProductTranslation($product, 'en', 'My Product');
        $product->addTranslation($translation);

        $result = $this->normalizer()->normalize($product, null, ['locale' => 'en']);

        $this->assertSame('My Product', $result['name']);
    }

    public function testNormalizePreseedsEnabledFamilyAttributesWithNull(): void
    {
        $family = new ProductFamily();
        $attr7 = $this->attributeWithId(7, 'weight');
        $familyAttribute = new ProductFamilyAttribute($family, $attr7);
        $family->addFamilyAttribute($familyAttribute);

        $product = new Product($family, 'product-uuid');

        $result = $this->normalizer()->normalize($product, null, ['locale' => 'en']);
        \assert(\is_array($result['attributes']));

        $this->assertArrayHasKey(7, $result['attributes']);
        $this->assertNull($result['attributes'][7]);
    }

    public function testNormalizeWithStoredAttributeValue(): void
    {
        $family = new ProductFamily();
        $attr7 = $this->attributeWithId(7, 'weight');
        $familyAttribute = new ProductFamilyAttribute($family, $attr7);
        $family->addFamilyAttribute($familyAttribute);

        $product = new Product($family, 'product-uuid');
        $value = new ProductAttributeValue($product, $attr7, 'weight');
        $value->setNumber(3.5);
        $product->addAttribute($value);

        $result = $this->normalizer()->normalize($product, null, ['locale' => 'en']);
        \assert(\is_array($result['attributes']));

        $this->assertSame(3.5, $result['attributes'][7]);
    }

    public function testNormalizeSkipsAttributesWithUnregisteredType(): void
    {
        $family = new ProductFamily();
        $attr8 = $this->attributeWithId(8, 'legacy');
        $attr8->setType('unregistered_type');
        $familyAttribute = new ProductFamilyAttribute($family, $attr8);
        $family->addFamilyAttribute($familyAttribute);

        $product = new Product($family, 'product-uuid');
        $value = new ProductAttributeValue($product, $attr8, 'legacy');
        $product->addAttribute($value);

        $result = $this->normalizer()->normalize($product, null, ['locale' => 'en']);

        $this->assertSame([], $result['attributes']);
    }

    public function testNormalizeWithMissingLocaleUsesEmptyString(): void
    {
        $family = new ProductFamily();
        $product = new Product($family, 'product-uuid');
        $translation = new ProductTranslation($product, 'en', 'English Name');
        $product->addTranslation($translation);

        $result = $this->normalizer()->normalize($product, null, []);

        $this->assertSame('', $result['name']);
    }
}
