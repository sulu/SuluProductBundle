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
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAttributeFormMetadataVisitor;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

#[CoversClass(ProductAttributeFormMetadataVisitor::class)]
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
            'description' => null,
            'attributes' => [
                ['id' => $weight, 'required' => true, 'variantSpecific' => false],
                ['id' => $colour, 'required' => false, 'variantSpecific' => true],
            ],
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $family['id']);
        $response = $this->client->getResponse();
        $this->assertHttpStatusCode(200, $response);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        /** @var array{form: array<string, array{label: string, items: array<string, array{type: string, label: string, required: bool}>}>, schema: array{allOf: array{0: mixed, 1: array{properties: array<string, mixed>, required: list<string>}}}} $shared */
        $shared = \json_decode((string) $response->getContent(), true);
        $this->assertCount(1, $shared['form']);
        $section = \reset($shared['form']);
        $this->assertSame('Dimensions', $section['label']);
        $this->assertCount(1, $section['items']);
        $field = \reset($section['items']);
        $this->assertSame('text_line', $field['type']);
        $this->assertSame('Weight (kg)', $field['label']);
        $this->assertTrue($field['required']);
        $fieldName = (string) \array_key_first($section['items']);
        $this->assertStringStartsWith('attribute_', $fieldName);
        $this->assertSame([$fieldName], $shared['schema']['allOf'][1]['required']);
        $this->assertArrayHasKey($fieldName, $shared['schema']['allOf'][1]['properties']);

        $this->client->request('GET', '/admin/metadata/form/product_attributes?productFamily=' . $family['id'] . '&variant=true');
        $this->assertHttpStatusCode(200, $this->client->getResponse());
        /** @var array{form: array<string, array{label: string, items: array<string, array{label: string}>}>} $axis */
        $axis = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $axis['form']);
        $axisSection = \reset($axis['form']);
        $this->assertSame('Appearance', $axisSection['label']);
        $this->assertCount(1, $axisSection['items']);
        $axisField = \reset($axisSection['items']);
        $this->assertSame('Colour', $axisField['label']);
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
    private function createAttribute(string $key, string $name, string $groupName, array $config = []): string
    {
        $container = self::getContainer();

        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        /** @var AttributeRepositoryInterface $attributeRepository */
        $attributeRepository = $container->get(AttributeRepositoryInterface::class);
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $group = $groupRepository->create();
        $group->setDefaultLocale('en');
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', $groupName));
        $groupRepository->save($group);

        $attribute = $attributeRepository->create($group);
        $attribute->setKey($key);
        $attribute->setType(AttributeInterface::TYPE_TEXT);
        $attribute->setConfig($config);
        $attribute->setDefaultLocale('en');
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));
        $attributeRepository->save($attribute);

        $entityManager->flush();

        $uuid = $attribute->getUuid();
        \assert(null !== $uuid);

        return $uuid;
    }
}
