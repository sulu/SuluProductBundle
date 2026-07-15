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
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttributeValue;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;

#[CoversClass(ProductDimensionContent::class)]
class ProductDimensionContentTest extends TestCase
{
    use ProphecyTrait;

    public function testConstructorAssignsResourceAndDefaults(): void
    {
        $product = new Product();
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

    public function testIsRouteMandatoryReturnsFalse(): void
    {
        $this->assertFalse(ProductDimensionContent::isRouteMandatory());
    }

    public function testSetTemplateDataExtractsTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $dimensionContent->setTemplateData(['title' => 'My Product']);

        $this->assertSame('My Product', $dimensionContent->getTitle());
    }

    public function testSetTemplateDataIgnoresMissingTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $dimensionContent->setTemplateData(['other' => 'value']);

        $this->assertNull($dimensionContent->getTitle());
    }

    public function testSetTemplateDataIgnoresNonStringTitle(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $dimensionContent->setTemplateData(['title' => 123]);

        $this->assertNull($dimensionContent->getTitle());
    }

    public function testSetCustomizeWebspaceSettingsIsFluentAndStores(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $this->assertSame($dimensionContent, $dimensionContent->setCustomizeWebspaceSettings(true));
        $this->assertTrue($dimensionContent->getCustomizeWebspaceSettings());

        $dimensionContent->setCustomizeWebspaceSettings(false);
        $this->assertFalse($dimensionContent->getCustomizeWebspaceSettings());
    }

    public function testAddAdditionalWebspaceStoresAndDeduplicates(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $this->assertSame($dimensionContent, $dimensionContent->addAdditionalWebspace('sulu-io'));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testHasAdditionalWebspace(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $this->assertFalse($dimensionContent->hasAdditionalWebspace('sulu-io'));
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $this->assertTrue($dimensionContent->hasAdditionalWebspace('sulu-io'));
        $this->assertFalse($dimensionContent->hasAdditionalWebspace('blog'));
    }

    public function testSetAdditionalWebspacesAddsNewOnes(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());

        $this->assertSame($dimensionContent, $dimensionContent->setAdditionalWebspaces(['sulu-io', 'blog']));
        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesRemovesMissingOnes(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $dimensionContent->setAdditionalWebspaces(['sulu-io']);

        $this->assertSame(['sulu-io'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesPreservesExistingAndAddsNew(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->addAdditionalWebspace('sulu-io');

        $dimensionContent->setAdditionalWebspaces(['sulu-io', 'blog']);

        $this->assertSame(['sulu-io', 'blog'], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetAdditionalWebspacesWithEmptyArrayRemovesAll(): void
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->addAdditionalWebspace('sulu-io');
        $dimensionContent->addAdditionalWebspace('blog');

        $dimensionContent->setAdditionalWebspaces([]);

        $this->assertSame([], $dimensionContent->getAdditionalWebspaces());
    }

    public function testSetTitleIsFluentAndStores(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setTitle('Hello'));
        $this->assertSame('Hello', $dc->getTitle());
    }

    public function testSetCodeAndGetCode(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getCode());
        $dc->setCode('SKU-1');
        $this->assertSame('SKU-1', $dc->getCode());
    }

    public function testSetCodeIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setCode('SKU-2'));
        $this->assertSame('SKU-2', $dc->getCode());
    }

    public function testSetExternalIdentifierAndGet(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getExternalIdentifier());
        $dc->setExternalIdentifier('EXT-X');
        $this->assertSame('EXT-X', $dc->getExternalIdentifier());
    }

    public function testSetExternalIdentifierIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setExternalIdentifier('EXT-Y'));
        $this->assertSame('EXT-Y', $dc->getExternalIdentifier());
    }

    public function testSetProductFamilyAndGet(): void
    {
        $family = new ProductFamily();
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getProductFamily());
        $dc->setProductFamily($family);
        $this->assertSame($family, $dc->getProductFamily());
    }

    public function testSetProductFamilyIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $family = new ProductFamily();
        $this->assertSame($dc, $dc->setProductFamily($family));
        $this->assertSame($family, $dc->getProductFamily());
    }

    public function testSetStatusAndGet(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getStatus());
        $dc->setStatus('available');
        $this->assertSame('available', $dc->getStatus());
    }

    public function testSetStatusIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setStatus('announced'));
        $this->assertSame('announced', $dc->getStatus());
    }

    public function testSetShortDescriptionAndGet(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getShortDescription());
        $dc->setShortDescription('<p>hi</p>');
        $this->assertSame('<p>hi</p>', $dc->getShortDescription());
    }

    public function testSetShortDescriptionIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setShortDescription('<p>x</p>'));
        $this->assertSame('<p>x</p>', $dc->getShortDescription());
    }

    public function testSetImageAndGet(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getImage());
        $dc->setImage(5);
        $this->assertSame(5, $dc->getImage());
    }

    public function testSetImageIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setImage(7));
        $this->assertSame(7, $dc->getImage());
    }

    public function testSetDocumentsAndGet(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertNull($dc->getDocuments());
        $dc->setDocuments([3, 7]);
        $this->assertSame([3, 7], $dc->getDocuments());
    }

    public function testSetDocumentsIsFluent(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame($dc, $dc->setDocuments([1, 2]));
        $this->assertSame([1, 2], $dc->getDocuments());
    }

    public function testDetailFieldsCoexistInSharedStorage(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $dc->setShortDescription('<p>hi</p>');
        $dc->setImage(5);
        $dc->setDocuments([3, 7]);

        // all three non-queried fields share the details_data json column without clobbering
        $this->assertSame('<p>hi</p>', $dc->getShortDescription());
        $this->assertSame(5, $dc->getImage());
        $this->assertSame([3, 7], $dc->getDocuments());
    }

    public function testSetImageNullPrunesAndRoundTrips(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $dc->setImage(5);
        $dc->setImage(null);

        $this->assertNull($dc->getImage());
    }

    public function testSetShortDescriptionNullRoundTrips(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $dc->setShortDescription('<p>hi</p>');
        $dc->setShortDescription(null);

        $this->assertNull($dc->getShortDescription());
    }

    public function testSetDocumentsEmptyArrayRoundTrips(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $dc->setDocuments([]);

        $this->assertSame([], $dc->getDocuments());
    }

    public function testSetDocumentsNullRoundTrips(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $dc->setDocuments([1, 2]);
        $dc->setDocuments(null);

        $this->assertNull($dc->getDocuments());
    }

    public function testGetDocumentsFiltersNonIntValuesFromJson(): void
    {
        $dc = new ProductDimensionContent(new Product());

        // simulate Doctrine json hydration of an untyped details_data payload
        $property = new \ReflectionProperty(ProductDimensionContent::class, 'detailsData');
        $property->setValue($dc, ['documents' => [3, 'foo', 7, null]]);

        $this->assertSame([3, 7], $dc->getDocuments());
    }

    public function testGetAttributesReturnsEmptyCollectionInitially(): void
    {
        $dc = new ProductDimensionContent(new Product());
        $this->assertSame([], $dc->getAttributes()->toArray());
    }

    public function testAddAttributeIsFluentAndStores(): void
    {
        $dc = new ProductDimensionContent(new Product());
        /** @var ObjectProphecy<AttributeInterface> $attrInterface */
        $attrInterface = $this->prophesize(AttributeInterface::class);
        $attr = new ProductAttributeValue($dc, $attrInterface->reveal(), 'k');
        $this->assertSame($dc, $dc->addAttribute($attr));
        $this->assertTrue($dc->getAttributes()->contains($attr));
    }

    public function testRemoveAttributeIsFluentAndRemoves(): void
    {
        $dc = new ProductDimensionContent(new Product());
        /** @var ObjectProphecy<AttributeInterface> $attrInterface */
        $attrInterface = $this->prophesize(AttributeInterface::class);
        $attr = new ProductAttributeValue($dc, $attrInterface->reveal(), 'k');
        $dc->addAttribute($attr);
        $this->assertSame($dc, $dc->removeAttribute($attr));
        $this->assertFalse($dc->getAttributes()->contains($attr));
    }
}
