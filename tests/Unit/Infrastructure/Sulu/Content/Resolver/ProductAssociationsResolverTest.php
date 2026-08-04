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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Resolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAssociation;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductAssociationsResolver;

#[CoversClass(ProductAssociationsResolver::class)]
final class ProductAssociationsResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<MetadataProviderInterface> */
    private ObjectProphecy $formMetadataProvider;

    /** @var ObjectProphecy<MetadataResolver> */
    private ObjectProphecy $metadataResolver;

    private ProductAssociationsResolver $resolver;

    protected function setUp(): void
    {
        $this->formMetadataProvider = $this->prophesize(MetadataProviderInterface::class);
        $this->metadataResolver = $this->prophesize(MetadataResolver::class);

        $this->resolver = new ProductAssociationsResolver(
            $this->formMetadataProvider->reveal(),
            $this->metadataResolver->reveal(),
        );
    }

    public function testReturnsNullForNonProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        $this->formMetadataProvider->getMetadata(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->resolver->resolve($other->reveal()));
    }

    public function testResolvesFormFieldsReKeyedByBareType(): void
    {
        $alternativeField = new FieldMetadata('associations/alternative');
        $alternativeField->setType('product_selection');
        $suitableField = new FieldMetadata('associations/suitable');
        $suitableField->setType('product_selection');
        $otherField = new FieldMetadata('other');
        $otherField->setType('text_line');

        $section = new SectionMetadata('associations');
        $section->addItem($alternativeField);
        $section->addItem($suitableField);

        $formMetadata = new FormMetadata();
        $formMetadata->setKey('product_associations');
        $formMetadata->setItems([
            $section->getName() => $section,
            $otherField->getName() => $otherField,
        ]);

        $this->formMetadataProvider->getMetadata('product_associations', 'en', [])
            ->willReturn($formMetadata)
            ->shouldBeCalledOnce();

        $target = new Product('uuid-b');

        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');
        $dimensionContent->addAssociation(new ProductAssociation($dimensionContent, $target, 'alternative'));

        $alternativeView = ContentView::create(['resolved-alternative'], []);
        $suitableView = ContentView::create([], []);

        $this->metadataResolver->resolveItems(
            ['alternative' => $alternativeField, 'suitable' => $suitableField],
            ['alternative' => [$target->getUuid()], 'suitable' => []],
            'en',
        )
            ->willReturn(['alternative' => $alternativeView, 'suitable' => $suitableView])
            ->shouldBeCalledOnce();

        $result = $this->resolver->resolve($dimensionContent);

        self::assertNotNull($result);
        self::assertSame(
            ['alternative' => $alternativeView, 'suitable' => $suitableView],
            $result->getContent(),
        );
        self::assertSame([], $result->getView());
    }
}
