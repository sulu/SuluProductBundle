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
use Sulu\Product\Infrastructure\Sulu\Search\Schema\AttributeIndexFieldProvider;
use Sulu\Product\Infrastructure\Sulu\Search\Schema\ProductSchemaLoader;

#[CoversClass(ProductSchemaLoader::class)]
class ProductSchemaLoaderTest extends TestCase
{
    use ProphecyTrait;

    public function testAppendsDynamicFieldsToProductsIndexOnly(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $inner->load()->willReturn(new Schema([
            ProductIndex::NAME => new Index('test_' . ProductIndex::NAME, [
                'id' => new Field\IdentifierField('id'),
            ]),
            'website' => new Index('test_website', [
                'id' => new Field\IdentifierField('id'),
            ]),
        ]));

        $fieldProvider = $this->prophesize(AttributeIndexFieldProvider::class);
        $fieldProvider->getFields()->willReturn([
            'attr_weight' => new Field\FloatField('attr_weight', multiple: true, filterable: true, facet: true),
        ]);

        $schema = (new ProductSchemaLoader($inner->reveal(), $fieldProvider->reveal()))->load();

        $products = $schema->indexes[ProductIndex::NAME];
        $this->assertSame('test_' . ProductIndex::NAME, $products->name);
        $this->assertArrayHasKey('id', $products->fields);
        $this->assertArrayHasKey('attr_weight', $products->fields);
        $this->assertArrayNotHasKey('attr_weight', $schema->indexes['website']->fields);
    }

    public function testStaticFieldWinsOverDynamicFieldOfSameName(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $inner->load()->willReturn(new Schema([
            ProductIndex::NAME => new Index(ProductIndex::NAME, [
                'id' => new Field\IdentifierField('id'),
                'attr_weight' => new Field\TextField('attr_weight'),
            ]),
        ]));
        $fieldProvider = $this->prophesize(AttributeIndexFieldProvider::class);
        $fieldProvider->getFields()->willReturn([
            'attr_weight' => new Field\FloatField('attr_weight', multiple: true, filterable: true),
        ]);

        $schema = (new ProductSchemaLoader($inner->reveal(), $fieldProvider->reveal()))->load();

        $this->assertInstanceOf(Field\TextField::class, $schema->indexes[ProductIndex::NAME]->fields['attr_weight']);
    }

    public function testSchemaWithoutProductsIndexIsReturnedUnchanged(): void
    {
        $inner = $this->prophesize(LoaderInterface::class);
        $original = new Schema(['website' => new Index('website', ['id' => new Field\IdentifierField('id')])]);
        $inner->load()->willReturn($original);
        $fieldProvider = $this->prophesize(AttributeIndexFieldProvider::class);
        $fieldProvider->getFields()->shouldNotBeCalled();

        $this->assertSame($original, (new ProductSchemaLoader($inner->reveal(), $fieldProvider->reveal()))->load());
    }
}
