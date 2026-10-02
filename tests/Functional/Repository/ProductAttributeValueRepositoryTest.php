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

namespace Sulu\Product\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductAttributeValueInterface;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductAttributeValueRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductAttributeValueRepository;

#[CoversClass(ProductAttributeValueRepository::class)]
class ProductAttributeValueRepositoryTest extends SuluTestCase
{
    private ProductAttributeValueRepositoryInterface $repository;

    private ProductRepositoryInterface $productRepository;

    private AttributeGroupRepositoryInterface $attributeGroupRepository;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $container->get(ProductRepositoryInterface::class);
        $this->productRepository = $productRepository;

        /** @var AttributeGroupRepositoryInterface $attributeGroupRepository */
        $attributeGroupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        $this->attributeGroupRepository = $attributeGroupRepository;

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;

        // private and not consumed by any service of the test application, so not fetchable from the container
        $this->repository = new ProductAttributeValueRepository($entityManager);

        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    private function createAttribute(string $key = 'material'): AttributeInterface
    {
        $group = $this->attributeGroupRepository->createNew();
        $this->attributeGroupRepository->save($group);

        $attribute = new Attribute($group);
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $this->entityManager->persist($attribute);
        $this->entityManager->flush();

        return $attribute;
    }

    /**
     * @return array{ProductInterface, ProductDimensionContentInterface}
     */
    private function createProductWithDimensionContent(?string $locale, string $stage, int $version): array
    {
        $product = $this->productRepository->createNew();
        $dimensionContent = $product->createDimensionContent();
        $dimensionContent->setLocale($locale);
        $dimensionContent->setStage($stage);
        $dimensionContent->setVersion($version);
        $product->addDimensionContent($dimensionContent);

        $this->productRepository->add($product);
        $this->entityManager->persist($dimensionContent);

        return [$product, $dimensionContent];
    }

    private function createValue(
        AttributeInterface $attribute,
        ProductDimensionContentInterface $dimensionContent,
        string $text,
    ): ProductAttributeValue {
        $value = new ProductAttributeValue($dimensionContent, $attribute, $attribute->getKey());
        $value->setText($text);
        $this->entityManager->persist($value);

        return $value;
    }

    public function testFindByAttributeReturnsOnlyMatchingAttributeValues(): void
    {
        $attribute = $this->createAttribute('material');
        $otherAttribute = $this->createAttribute('finish');

        [, $dimensionContent] = $this->createProductWithDimensionContent(null, 'live', 0);
        $this->createValue($attribute, $dimensionContent, 'Brass');
        $this->createValue($otherAttribute, $dimensionContent, 'Steel');
        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $results = $this->repository->findBy(['attribute' => $attribute]);

        $this->assertCount(1, $results);
        $this->assertSame('Brass', $results[0]->getText());
    }

    public function testFindByStageFiltersOutDraftValues(): void
    {
        $attribute = $this->createAttribute();

        [, $liveContent] = $this->createProductWithDimensionContent(null, 'live', 0);
        $this->createValue($attribute, $liveContent, 'Live Value');

        [, $draftContent] = $this->createProductWithDimensionContent(null, 'draft', 0);
        $this->createValue($attribute, $draftContent, 'Draft Value');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $results = $this->repository->findBy(['attribute' => $attribute, 'stage' => 'live']);

        $this->assertCount(1, $results);
        $this->assertSame('Live Value', $results[0]->getText());
    }

    public function testFindByLocaleMatchesLocalizedAndUnlocalizedValues(): void
    {
        $attribute = $this->createAttribute();

        [, $enContent] = $this->createProductWithDimensionContent('en', 'live', 0);
        $this->createValue($attribute, $enContent, 'English Value');

        [, $deContent] = $this->createProductWithDimensionContent('de', 'live', 0);
        $this->createValue($attribute, $deContent, 'German Value');

        [, $unlocalizedContent] = $this->createProductWithDimensionContent(null, 'live', 0);
        $this->createValue($attribute, $unlocalizedContent, 'Unlocalized Value');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $results = $this->repository->findBy(['attribute' => $attribute, 'locale' => 'en']);

        $texts = \array_map(static fn (ProductAttributeValueInterface $value): ?string => $value->getText(), $results);
        \sort($texts);

        $this->assertSame(['English Value', 'Unlocalized Value'], $texts);
    }

    public function testFindByOnlyMatchesCurrentVersion(): void
    {
        $attribute = $this->createAttribute();

        [, $currentContent] = $this->createProductWithDimensionContent(null, 'live', 0);
        $this->createValue($attribute, $currentContent, 'Current Version');

        [, $olderContent] = $this->createProductWithDimensionContent(null, 'live', 1);
        $this->createValue($attribute, $olderContent, 'Other Version');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $results = $this->repository->findBy(['attribute' => $attribute]);

        $this->assertCount(1, $results);
        $this->assertSame('Current Version', $results[0]->getText());
    }

    public function testFindByWithoutFiltersReturnsAllValues(): void
    {
        $attribute = $this->createAttribute();

        [, $dimensionContent] = $this->createProductWithDimensionContent(null, 'live', 0);
        $this->createValue($attribute, $dimensionContent, 'Any Value');

        $this->entityManager->flush();
        $this->entityManager->clear();

        $results = $this->repository->findBy();

        $this->assertNotEmpty($results);
    }

    public function testFindByOnEmptyDatabaseReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repository->findBy());
    }

    public function testCountValuesOnEmptyDatabaseReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->repository->countValues());
    }

    public function testCountValuesGroupsIdenticalValuesMostCommonFirst(): void
    {
        $attribute = $this->createAttribute('material');

        foreach (['Brass', 'Steel', 'Brass', 'Brass', 'Steel', 'Zinc'] as $text) {
            [, $dimensionContent] = $this->createProductWithDimensionContent(null, 'live', 0);
            $this->createValue($attribute, $dimensionContent, $text);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $groups = $this->repository->countValues(['attribute' => $attribute, 'stage' => 'live']);

        $this->assertSame(
            [['Brass', 3], ['Steel', 2], ['Zinc', 1]],
            \array_map(static fn (array $group): array => [$group['value']->getText(), $group['count']], $groups),
        );
    }

    public function testCountValuesAppliesTheLimitAndTheStageFilter(): void
    {
        $attribute = $this->createAttribute('material');

        foreach ([['Brass', 'live'], ['Brass', 'live'], ['Steel', 'live'], ['Draft only', 'draft'], ['Draft only', 'draft'], ['Draft only', 'draft']] as [$text, $stage]) {
            [, $dimensionContent] = $this->createProductWithDimensionContent(null, $stage, 0);
            $this->createValue($attribute, $dimensionContent, $text);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $groups = $this->repository->countValues(['attribute' => $attribute, 'stage' => 'live'], 1);

        $this->assertCount(1, $groups);
        $this->assertSame('Brass', $groups[0]['value']->getText());
        $this->assertSame(2, $groups[0]['count']);
    }

    public function testCountValuesGroupsOptionValuesByOptionAndSkipsEmptyValues(): void
    {
        $attribute = $this->createAttribute('color');
        $attribute->setType(AttributeInterface::TYPE_OPTIONS);
        $red = new AttributeOption($attribute, 'red');
        $red->addTranslation(new AttributeOptionTranslation($red, 'en', 'Red'));
        $blue = new AttributeOption($attribute, 'blue');
        $this->entityManager->persist($red);
        $this->entityManager->persist($blue);

        foreach ([$red, $red, $blue, null] as $option) {
            [, $dimensionContent] = $this->createProductWithDimensionContent(null, 'live', 0);
            $value = new ProductAttributeValue($dimensionContent, $attribute, 'color');
            $value->setAttributeOption($option);
            $this->entityManager->persist($value);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $groups = $this->repository->countValues(['attribute' => $attribute, 'stage' => 'live']);

        $this->assertSame(
            [['red', 2], ['blue', 1]],
            \array_map(static fn (array $group): array => [$group['value']->getAttributeOption()?->getKey(), $group['count']], $groups),
        );
    }

    public function testCountValuesRunsAFixedNumberOfSelectsRegardlessOfTheNumberOfValues(): void
    {
        $attribute = $this->createAttribute('material');

        foreach (\range(1, 30) as $i) {
            [, $dimensionContent] = $this->createProductWithDimensionContent(null, 'live', 0);
            $this->createValue($attribute, $dimensionContent, 'Value ' . ($i % 3));
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $attribute = $this->entityManager->getRepository(Attribute::class)->find($attribute->getUuid());
        $this->assertNotNull($attribute);

        $before = $this->selectCount();
        $this->repository->countValues(['attribute' => $attribute, 'stage' => 'live']);

        $this->assertLessThanOrEqual(2, $this->selectCount() - $before, 'grouping and one representative load, not one query per value');
    }

    private function selectCount(): int
    {
        /** @var array<array{Value: string}> $rows */
        $rows = $this->entityManager->getConnection()
            ->executeQuery("SHOW SESSION STATUS LIKE 'Com_select'")
            ->fetchAllAssociative();

        return (int) $rows[0]['Value'];
    }
}
