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

namespace Sulu\Product\Tests\Functional\Content;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductResolver;

#[CoversClass(ProductResolver::class)]
class ProductResolverAttributesTest extends SuluTestCase
{
    private ContentResolverInterface $contentResolver;

    private EntityManagerInterface $entityManager;

    private ProductRepositoryInterface $productRepository;

    private AttributeGroupRepositoryInterface $attributeGroupRepository;

    private AttributeRepositoryInterface $attributeRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ContentResolverInterface $contentResolver */
        $contentResolver = $container->get('sulu_content.content_resolver');
        $this->contentResolver = $contentResolver;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get('sulu_product.product_repository');
        $this->productRepository = $productRepository;

        /** @var AttributeGroupRepositoryInterface $attributeGroupRepository */
        $attributeGroupRepository = $container->get('sulu_product.attribute_group_repository');
        $this->attributeGroupRepository = $attributeGroupRepository;

        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get('sulu_product.attribute_repository');
        $this->attributeRepository = $attributeRepository;

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testAttributesAppearUnderRootProductAttributes(): void
    {
        $group = $this->attributeGroupRepository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Mechanische Daten'));
        $this->attributeGroupRepository->add($group);

        $attribute = $this->attributeRepository->createNew($group);
        $attribute->setKey('housing');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setPosition(1);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Gehäuse'));
        $this->attributeRepository->add($attribute);

        $this->entityManager->flush();

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('de');
        $dimensionContent->setStage('draft');
        // TemplateResolver needs a registered template key to resolve the template section
        $dimensionContent->setTemplateKey('product');
        $product->addDimensionContent($dimensionContent);

        $value = new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey());
        $value->setText('Zink');
        $dimensionContent->addAttribute($value);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        self::assertArrayHasKey('attributes', $productData);

        $attributes = $productData['attributes'];
        self::assertIsArray($attributes);
        self::assertArrayHasKey('housing', $attributes);

        $housing = $attributes['housing'];
        self::assertIsArray($housing);
        self::assertSame('Zink', $housing['value']);
        self::assertSame('Zink', $housing['formattedValue']);
        $attribute = $housing['attribute'];
        self::assertInstanceOf(AttributeInterface::class, $attribute);
        self::assertSame('Mechanische Daten', $attribute->getGroup()->getTranslation('de')?->getName());
    }

    public function testRangeAttributeResolvesToItsBounds(): void
    {
        $group = $this->attributeGroupRepository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Technische Daten'));
        $this->attributeGroupRepository->add($group);

        $attribute = $this->attributeRepository->createNew($group);
        $attribute->setKey('temperature');
        $attribute->setType(AttributeInterface::TYPE_RANGE);
        $attribute->setPosition(1);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Temperatur'));
        $this->attributeRepository->add($attribute);

        $this->entityManager->flush();

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('de');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $product->addDimensionContent($dimensionContent);

        foreach (['from' => -20.0, 'to' => 60.0] as $valueKey => $number) {
            $value = new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey(), valueKey: $valueKey);
            $value->setNumber($number);
            $dimensionContent->addAttribute($value);
        }

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        $attributes = $productData['attributes'];
        self::assertIsArray($attributes);
        self::assertArrayHasKey('temperature', $attributes);

        $temperature = $attributes['temperature'];
        self::assertIsArray($temperature);
        self::assertSame(['from' => -20.0, 'to' => 60.0], $temperature['value']);
        self::assertSame('-20 – 60', $temperature['formattedValue']);
        self::assertInstanceOf(AttributeInterface::class, $temperature['attribute']);
    }

    public function testMultiOptionsAttributeResolvesToTheListOfItsOptionKeysInTheOrderOfTheOptions(): void
    {
        $group = $this->attributeGroupRepository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Optik'));
        $this->attributeGroupRepository->add($group);

        $attribute = $this->attributeRepository->createNew($group);
        $attribute->setKey('colours');
        $attribute->setType(AttributeInterface::TYPE_MULTI_OPTIONS);
        $attribute->setPosition(1);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Farben'));
        foreach (['red', 'blue', 'green'] as $optionKey) {
            $attribute->addOption(new AttributeOption($attribute, $optionKey));
        }
        $this->attributeRepository->add($attribute);

        $this->entityManager->flush();

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('de');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $product->addDimensionContent($dimensionContent);

        foreach (['blue', 'red'] as $optionKey) {
            $option = $attribute->getOption($optionKey);
            self::assertNotNull($option);
            $dimensionContent->addAttribute(new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey(), $option, $option->getUuid()));
        }

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        $attributes = $productData['attributes'];
        self::assertIsArray($attributes);
        self::assertArrayHasKey('colours', $attributes);

        $colours = $attributes['colours'];
        self::assertIsArray($colours);
        self::assertSame(['red', 'blue'], $colours['value']);
        self::assertSame('red, blue', $colours['formattedValue']);
        self::assertInstanceOf(AttributeInterface::class, $colours['attribute']);
    }

    /**
     * @return iterable<string, array{float, bool}>
     */
    public static function provideBooleanValues(): iterable
    {
        yield 'true' => [1.0, true];
        yield 'false' => [0.0, false];
    }

    #[DataProvider('provideBooleanValues')]
    public function testBooleanAttributeResolvesToAPhpBool(float $stored, bool $expected): void
    {
        $group = $this->attributeGroupRepository->createNew();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Eigenschaften'));
        $this->attributeGroupRepository->add($group);

        $attribute = $this->attributeRepository->createNew($group);
        $attribute->setKey('waterproof');
        $attribute->setType(AttributeInterface::TYPE_BOOLEAN);
        $attribute->setPosition(1);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Wasserdicht'));
        $this->attributeRepository->add($attribute);

        $this->entityManager->flush();

        $product = $this->productRepository->createNew();

        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale('de');
        $dimensionContent->setStage('draft');
        $dimensionContent->setTemplateKey('product');
        $product->addDimensionContent($dimensionContent);

        $value = new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey());
        $value->setNumber($stored);
        $dimensionContent->addAttribute($value);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);
        $this->entityManager->flush();

        $result = $this->contentResolver->resolve($dimensionContent);

        self::assertArrayHasKey('product', $result);
        $productData = $result['product'];
        self::assertIsArray($productData);
        $attributes = $productData['attributes'];
        self::assertIsArray($attributes);
        self::assertArrayHasKey('waterproof', $attributes);

        $waterproof = $attributes['waterproof'];
        self::assertIsArray($waterproof);
        self::assertSame($expected, $waterproof['value']);
        self::assertSame($expected, $waterproof['formattedValue']);
    }
}
