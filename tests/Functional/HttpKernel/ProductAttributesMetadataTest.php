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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributesFormMetadataVisitor;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributesSchemaFormMetadataVisitor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductAttributesFormMetadataVisitor::class)]
#[CoversClass(ProductAttributesSchemaFormMetadataVisitor::class)]
class ProductAttributesMetadataTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
        self::purgeDatabase();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testMetadataForFamilyAndVariant(): void
    {
        $weight = $this->createAttribute('weight', 'Weight', 'Dimensions', ['unit' => 'KILOGRAM']);
        $colour = $this->createAttribute('colour', 'Colour', 'Appearance');

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Shoes',
            'key' => 'shoes',
            'description' => null,
            'attributes' => [
                ['id' => $weight->getUuid(), 'required' => true, 'variantSpecific' => false],
                ['id' => $colour->getUuid(), 'required' => false, 'variantSpecific' => true],
            ],
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $family['id']);
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        /** @var array{form: array<string, array{label: string, items: array<string, array{type: string, label: string, required: bool}>}>, schema: array<string, mixed>} $shared */
        $shared = \json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $shared['form']);
        $this->assertSame(['attribute_group_' . $weight->getGroup()->getUuid()], \array_keys($shared['form']));
        $section = \reset($shared['form']);
        $this->assertSame('Dimensions', $section['label']);
        $this->assertCount(1, $section['items']);
        $field = \reset($section['items']);
        $this->assertSame('text_line', $field['type']);
        $this->assertSame('Weight (kg)', $field['label']);
        $this->assertTrue($field['required']);
        $this->assertSame(['attribute_' . $weight->getUuid()], \array_keys($section['items']));
        $this->assertSame(['type' => ['number', 'string', 'boolean', 'object', 'array', 'null']], $shared['schema']);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $family['id'] . '&productType=variant');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        /** @var array{form: array<string, array{label: string, items: array<string, array{label: string}>}>} $axis */
        $axis = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $axis['form']);
        $this->assertSame(['attribute_group_' . $colour->getGroup()->getUuid()], \array_keys($axis['form']));
        $axisSection = \reset($axis['form']);
        $this->assertSame('Appearance', $axisSection['label']);
        $this->assertCount(1, $axisSection['items']);
        $this->assertSame(['attribute_' . $colour->getUuid()], \array_keys($axisSection['items']));
        $axisField = \reset($axisSection['items']);
        $this->assertSame('Colour', $axisField['label']);
    }

    public function testDetailsFormSchemaValidatesTheAttributesOfTheSelectedFamily(): void
    {
        $weight = $this->createAttribute('weight', 'Weight', 'Dimensions');

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Shoes',
            'key' => 'shoes',
            'description' => null,
            'attributes' => [['id' => $weight->getUuid(), 'required' => true, 'variantSpecific' => false]],
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        $weightUuid = $weight->getUuid();

        $this->client->request('GET', '/admin/metadata/form/product_details');
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        /** @var array{schema: mixed} $details */
        $details = \json_decode((string) $response->getContent(), true);

        // Visitors merge by nesting `allOf`, so the branch sits at no fixed depth, hence the text match.
        $encoded = \json_encode($details['schema']) ?: '';
        foreach (['product', 'product_with_variants'] as $productType) {
            $this->assertStringContainsString(
                '"if":{"type":"object","properties":{"productFamily":{"const":"' . $family['id'] . '"},"type":{"const":"' . $productType . '"}},"required":["productFamily","type"]},'
                . '"then":{"type":"object","properties":{"attributes":{"type":"object","properties":{"' . $weightUuid . '":{"type":"string","minLength":1}},"required":["' . $weightUuid . '"]}},"required":["attributes"]}',
                $encoded,
                'a shared attribute is validated for both product types',
            );
        }
    }

    /**
     * @return iterable<string, array{bool, list<array{name: string, title: string}>}>
     */
    public static function provideBooleanChoices(): iterable
    {
        yield 'required' => [true, [['name' => 'true', 'title' => 'Yes'], ['name' => 'false', 'title' => 'No']]];
        yield 'optional' => [false, [['name' => '', 'title' => 'Please choose'], ['name' => 'true', 'title' => 'Yes'], ['name' => 'false', 'title' => 'No']]];
    }

    /**
     * @param list<array{name: string, title: string}> $expectedChoices
     */
    #[DataProvider('provideBooleanChoices')]
    public function testBooleanAttributeIsAYesNoSelect(bool $required, array $expectedChoices): void
    {
        $waterproof = $this->createAttribute('waterproof', 'Waterproof', 'Features', type: AttributeInterface::TYPE_BOOLEAN)->getUuid();

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Boots',
            'key' => 'boots',
            'description' => null,
            'attributes' => [['id' => $waterproof, 'required' => $required, 'variantSpecific' => false]],
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $family['id'] . '&locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{form: array<string, array{items: array<string, array{type: string, options: array{values: array{value: list<array{name: string, title: string}>}}}>}>} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $section = \reset($data['form']);
        $this->assertNotFalse($section);
        $field = \reset($section['items']);
        $this->assertNotFalse($field);

        $this->assertSame('single_select', $field['type']);
        $this->assertSame(
            $expectedChoices,
            \array_map(
                static fn (array $option): array => ['name' => $option['name'], 'title' => $option['title']],
                $field['options']['values']['value'],
            ),
        );
    }

    public function testMetadataWithoutSelectorIsEmpty(): void
    {
        $this->client->request('GET', '/admin/metadata/form/product_attributes');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        /** @var array{form: array<string, mixed>} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertSame([], $data['form']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createAttribute(string $key, string $name, string $groupName, array $config = [], string $type = AttributeInterface::TYPE_TEXT): AttributeInterface
    {
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->createNew();
        $group->setDefaultLocale('en');
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', $groupName));
        $groupRepository->save($group);

        $attribute = $attributeRepository->createNew($group);
        $attribute->setKey($key);
        $attribute->setType($type);
        $attribute->setConfig($config);
        $attribute->setDefaultLocale('en');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        $attributeRepository->save($attribute);

        $entityManager->flush();

        return $attribute;
    }
}
