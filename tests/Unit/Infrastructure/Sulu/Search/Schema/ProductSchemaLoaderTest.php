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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Search\Schema;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Sulu\Product\Infrastructure\Sulu\Search\ProductIndex;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\NumericAttributeLister;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\ProductSchemaLoader;

#[CoversClass(ProductSchemaLoader::class)]
class ProductSchemaLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testAppendsNumericFieldsToProductField(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $inner->load()->willReturn(new Schema([
            ProductIndex::NAME => new Index('test_' . ProductIndex::NAME, $this->websiteFields()),
            'admin' => new Index('test_admin', ['id' => new Field\IdentifierField('id')]),
        ]));

        $lister = $this->prophesize(NumericAttributeLister::class);
        $lister->getFields()->willReturn([
            'weight' => new Field\FloatField('weight', multiple: true, searchable: false, filterable: true, facet: true),
        ]);

        $schema = (new ProductSchemaLoader($inner->reveal(), $lister->reveal()))->load();
        $index = $schema->indexes[ProductIndex::NAME];

        $this->assertSame('test_' . ProductIndex::NAME, $index->name);
        $this->assertArrayHasKey('id', $index->fields);
        $this->assertContains('product.attributes_numeric_values.weight', $index->filterableFields);
        $this->assertContains('product.attributes_numeric_values.weight', $index->facetFields);
        $this->assertContains('product.attributes_text_values', $index->filterableFields);
    }

    public function testStaticFieldWinsOverNumericFieldOfSameName(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $inner->load()->willReturn(new Schema([
            ProductIndex::NAME => new Index(ProductIndex::NAME, $this->websiteFields([
                'weight' => new Field\TextField('weight', searchable: false, filterable: true),
            ])),
        ]));

        $lister = $this->prophesize(NumericAttributeLister::class);
        $lister->getFields()->willReturn([
            'weight' => new Field\FloatField('weight', multiple: true, searchable: false, filterable: true),
        ]);

        $schema = (new ProductSchemaLoader($inner->reveal(), $lister->reveal()))->load();

        /** @var Field\ObjectField $product */
        $product = $schema->indexes[ProductIndex::NAME]->fields[ProductIndex::FIELD];
        /** @var Field\ObjectField $numericValues */
        $numericValues = $product->fields[ProductIndex::NUMERIC_VALUES_FIELD];
        $this->assertInstanceOf(Field\TextField::class, $numericValues->fields['weight']);
    }

    public function testSchemaWithoutProductFieldIsReturnedUnchanged(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $original = new Schema([ProductIndex::NAME => new Index(ProductIndex::NAME, ['id' => new Field\IdentifierField('id')])]);
        $inner->load()->willReturn($original);

        $lister = $this->prophesize(NumericAttributeLister::class);
        $lister->getFields()->shouldNotBeCalled();

        $this->assertSame($original, (new ProductSchemaLoader($inner->reveal(), $lister->reveal()))->load());
    }

    /**
     * @param array<string, Field\AbstractField> $numericFields
     *
     * @return array<string, Field\AbstractField>
     */
    private function websiteFields(array $numericFields = []): array
    {
        return [
            'id' => new Field\IdentifierField('id'),
            ProductIndex::FIELD => new Field\ObjectField(ProductIndex::FIELD, [
                ProductIndex::TEXT_VALUES_FIELD => new Field\TextField(ProductIndex::TEXT_VALUES_FIELD, multiple: true, searchable: false, filterable: true, facet: true),
                ProductIndex::NUMERIC_VALUES_FIELD => new Field\ObjectField(ProductIndex::NUMERIC_VALUES_FIELD, $numericFields),
            ]),
        ];
    }
}
