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
                ['id' => $weight->getUuid(), 'required' => true, 'mode' => 'shared'],
                ['id' => $colour->getUuid(), 'required' => false, 'mode' => 'variant_specific'],
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
            'attributes' => [['id' => $weight->getUuid(), 'required' => true, 'mode' => 'shared']],
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
        yield 'optional' => [false, [['name' => '', 'title' => 'Not specified'], ['name' => 'true', 'title' => 'Yes'], ['name' => 'false', 'title' => 'No']]];
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
            'attributes' => [['id' => $waterproof, 'required' => $required, 'mode' => 'shared']],
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

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideRequired(): iterable
    {
        yield 'required' => [true];
        yield 'optional' => [false];
    }

    #[DataProvider('provideRequired')]
    public function testRangeAttributeIsASectionOfItsTwoBounds(bool $required): void
    {
        $range = $this->createAttribute('temperature', 'Temperature', 'Features', ['unit' => 'CELSIUS', 'min' => -50, 'max' => 150, 'step' => 0.5], AttributeInterface::TYPE_RANGE);
        $familyId = $this->createFamily([['id' => $range->getUuid(), 'required' => $required, 'variantSpecific' => false]]);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $familyId . '&locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{form: array<string, array{items: array<string, array{type: string, label: string, items: array<string, array{type: string, label: string, required: bool, colSpan: int, options: array<string, array{value: string}>}>}>}>} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $group = \reset($data['form']);
        $this->assertNotFalse($group);
        $this->assertSame(['attribute_' . $range->getUuid()], \array_keys($group['items']));

        $section = $group['items']['attribute_' . $range->getUuid()];
        $this->assertSame('section', $section['type']);
        $this->assertSame('Temperature (°C)', $section['label']);
        $this->assertSame(['from', 'to'], \array_keys($section['items']));

        foreach (['from' => 'From', 'to' => 'To'] as $name => $label) {
            $field = $section['items'][$name];
            $this->assertSame('number', $field['type']);
            $this->assertSame($label, $field['label']);
            $this->assertSame($required, $field['required']);
            $this->assertSame(6, $field['colSpan']);
            $this->assertSame('-50', $field['options']['min']['value']);
            $this->assertSame('150', $field['options']['max']['value']);
            $this->assertSame('0.5', $field['options']['step']['value']);
        }
    }

    public function testNumberAttributeStaysAPlainField(): void
    {
        $weight = $this->createAttribute('weight', 'Weight', 'Dimensions', ['unit' => 'KILOGRAM'], AttributeInterface::TYPE_NUMBER);
        $familyId = $this->createFamily([['id' => $weight->getUuid(), 'required' => false, 'variantSpecific' => false]]);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $familyId . '&locale=en');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var array{form: array<string, array{items: array<string, array{type: string, label: string}>}>} $data */
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $group = \reset($data['form']);
        $this->assertNotFalse($group);
        $this->assertSame(['attribute_' . $weight->getUuid()], \array_keys($group['items']));
        $this->assertSame('number', $group['items']['attribute_' . $weight->getUuid()]['type']);
        $this->assertSame('Weight (kg)', $group['items']['attribute_' . $weight->getUuid()]['label']);
    }

    public function testDetailsFormSchemaValidatesARangeAsAnObjectOfItsBounds(): void
    {
        $required = $this->createAttribute('temperature', 'Temperature', 'Features', ['min' => -50, 'max' => 150], AttributeInterface::TYPE_RANGE);
        $optional = $this->createAttribute('humidity', 'Humidity', 'Features', [], AttributeInterface::TYPE_RANGE);
        $this->createFamily([
            ['id' => $required->getUuid(), 'required' => true, 'variantSpecific' => false],
            ['id' => $optional->getUuid(), 'required' => false, 'variantSpecific' => false],
        ]);

        $this->client->request('GET', '/admin/metadata/form/product_details');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        /** @var array{schema: mixed} $details */
        $details = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $encoded = \json_encode($details['schema']) ?: '';

        $bound = '{"anyOf":[{"type":"null"},{"type":"number","minimum":-50,"maximum":150}]}';
        $this->assertStringContainsString(
            '"' . $required->getUuid() . '":{"type":"object","properties":{"from":' . $bound . ',"to":' . $bound . '}}',
            $encoded,
            'A required range is an object, its bounds are not required themselves.',
        );

        $openBound = '{"anyOf":[{"type":"null"},{"type":"number"}]}';
        $this->assertStringContainsString(
            '"' . $optional->getUuid() . '":{"anyOf":[{"type":"null"},{"type":"object","properties":{"from":' . $openBound . ',"to":' . $openBound . '}}]}',
            $encoded,
            'An optional range may also be null.',
        );
        $this->assertStringContainsString('"required":["' . $required->getUuid() . '"]', $encoded);
        $this->assertStringNotContainsString('"required":["' . $required->getUuid() . '","' . $optional->getUuid() . '"]', $encoded);
    }

    /**
     * @param list<array{id: string, required: bool, variantSpecific: bool}> $attributes
     */
    private function createFamily(array $attributes): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Sensors',
            'key' => 'sensors',
            'description' => null,
            'attributes' => $attributes,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        return $family['id'];
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
