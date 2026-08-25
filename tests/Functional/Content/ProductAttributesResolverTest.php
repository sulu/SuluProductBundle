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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Application\ContentResolver\ContentResolverInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductAttributesResolver;

#[CoversClass(ProductAttributesResolver::class)]
class ProductAttributesResolverTest extends SuluTestCase
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
        $group = $this->attributeGroupRepository->create();
        $group->addTranslation(new AttributeGroupTranslation($group, 'de', 'Mechanische Daten'));
        $this->attributeGroupRepository->save($group);

        $attribute = $this->attributeRepository->create($group);
        $attribute->setKey('housing');
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setPosition(1);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'de', 'Gehäuse'));
        $this->attributeRepository->save($attribute);

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

        $attribute = $attributes['housing'];
        self::assertIsArray($attribute);
        self::assertSame('Zink', $attribute['formattedValue']);

        $group = $attribute['group'];
        self::assertIsArray($group);
        self::assertSame('Mechanische Daten', $group['label']);
    }
}
