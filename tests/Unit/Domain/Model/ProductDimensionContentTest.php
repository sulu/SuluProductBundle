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

namespace Sulu\Product\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductDimensionContent::class)]
class ProductDimensionContentTest extends TestCase
{
    public function testConstructorAssignsResourceAndDefaults(): void
    {
        $product = new Product(new ProductFamily());
        $dimensionContent = new ProductDimensionContent($product);

        $this->assertSame($product, $dimensionContent->getResource());
        $this->assertNull($dimensionContent->getTitle());
        $this->assertFalse($dimensionContent->getCustomizeWebspaceSettings());
        $this->assertSame([], $dimensionContent->getAdditionalWebspaces());
    }

    public function testGetTemplateTypeReturnsProduct(): void
    {
        $this->assertSame(ProductInterface::TEMPLATE_TYPE, ProductDimensionContent::getTemplateType());
    }

    public function testGetResourceKeyReturnsExpectedKey(): void
    {
        $this->assertSame(
            ProductDimensionContentInterface::RESOURCE_KEY,
            ProductDimensionContent::getResourceKey(),
        );
    }

    public function testSetTemplateDataExtractsTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $dimensionContent->setTemplateData(['title' => 'My Product']);

        $this->assertSame('My Product', $dimensionContent->getTitle());
    }

    public function testSetTemplateDataIgnoresMissingTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $dimensionContent->setTemplateData(['other' => 'value']);

        $this->assertNull($dimensionContent->getTitle());
    }

    public function testSetTemplateDataIgnoresNonStringTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $dimensionContent->setTemplateData(['title' => 123]);

        $this->assertNull($dimensionContent->getTitle());
    }

    public function testSetCustomizeWebspaceSettingsIsFluentAndStores(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $this->assertSame($dimensionContent, $dimensionContent->setCustomizeWebspaceSettings(true));
        $this->assertTrue($dimensionContent->getCustomizeWebspaceSettings());

        $dimensionContent->setCustomizeWebspaceSettings(false);
        $this->assertFalse($dimensionContent->getCustomizeWebspaceSettings());
    }

    public function testAddAdditionalWebspaceStoresAndDeduplicates(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $this->assertSame($dimensionContent, $dimensionContent->addAdditionalWebspace('sulu-io'));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testHasAdditionalWebspace(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $this->assertFalse($dimensionContent->hasAdditionalWebspace('sulu-io'));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $this->assertTrue($dimensionContent->hasAdditionalWebspace('sulu-io'));
        $this->assertFalse($dimensionContent->hasAdditionalWebspace('blog'));
    }

    public function testSetAdditionalWebspacesAddsNewOnes(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));

        $this->assertSame($dimensionContent, $dimensionContent->setAdditionalWebspaces(['sulu-io', 'blog']));
        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesRemovesMissingOnes(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $dimensionContent->setAdditionalWebspaces(['sulu-io']);

        $this->assertSame(['sulu-io'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesPreservesExistingAndAddsNew(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));
        $dimensionContent->addAdditionalWebspace('sulu-io');

        $dimensionContent->setAdditionalWebspaces(['sulu-io', 'blog']);

        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesWithEmptyArrayRemovesAll(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product(new ProductFamily()));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $dimensionContent->setAdditionalWebspaces([]);

        $this->assertSame([], $dimensionContent->getAdditionalWebspaces());
    }
}
