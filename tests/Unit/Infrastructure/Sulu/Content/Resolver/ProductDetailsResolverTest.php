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

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\PropertyResolver\MediaSelectionPropertyResolver;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\PropertyResolver\SingleMediaSelectionPropertyResolver;
use Sulu\Bundle\MediaBundle\Infrastructure\Sulu\Content\ResourceLoader\MediaResourceLoader;
use Sulu\Content\Application\ContentResolver\Value\ContentView;
use Sulu\Content\Application\ContentResolver\Value\ResolvableResource;
use Sulu\Content\Application\MetadataResolver\MetadataResolver;
use Sulu\Content\Application\PropertyResolver\PropertyResolverProvider;
use Sulu\Content\Application\PropertyResolver\Resolver\DefaultPropertyResolver;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Infrastructure\Sulu\Content\Resolver\ProductDetailsResolver;

class ProductDetailsResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<FormMetadataLoaderInterface> */
    private ObjectProphecy $formMetadataLoader;

    private ProductDetailsResolver $resolver;

    protected function setUp(): void
    {
        $this->formMetadataLoader = $this->prophesize(FormMetadataLoaderInterface::class);
        $this->formMetadataLoader->getMetadata(Argument::cetera())->willReturn(null);

        // the real property resolvers — the bucket is interpreted by the resolver its XML
        // `type` selects, which is the whole point of storing the wire-shape verbatim
        $metadataResolver = new MetadataResolver(
            new PropertyResolverProvider(new \ArrayIterator([
                'default' => new DefaultPropertyResolver(),
                'single_media_selection' => new SingleMediaSelectionPropertyResolver(),
                'media_selection' => new MediaSelectionPropertyResolver(),
            ])),
        );

        $this->resolver = new ProductDetailsResolver(
            $this->formMetadataLoader->reveal(),
            $metadataResolver,
        );
    }

    /**
     * @param array<string, string> $fields field name => type
     */
    private function givenFormMetadata(array $fields): void
    {
        $items = [];
        foreach ($fields as $name => $type) {
            $field = new FieldMetadata($name);
            $field->setType($type);
            $items[$name] = $field;
        }

        $formMetadata = new FormMetadata();
        $formMetadata->setKey(ProductInterface::FORM_KEY);
        $formMetadata->setItems($items);

        $this->formMetadataLoader->getMetadata(ProductInterface::FORM_KEY, Argument::type('string'), [])
            ->willReturn($formMetadata);
    }

    private function makeDimensionContent(mixed $details = []): ProductDimensionContent
    {
        $dimensionContent = new ProductDimensionContent(new Product());
        $dimensionContent->setLocale('en');
        /** @var array<string, mixed> $details */
        $dimensionContent->setDetailsData($details);

        return $dimensionContent;
    }

    /**
     * @return array<mixed, mixed>
     */
    private function resolveContent(ProductDimensionContent $dimensionContent): array
    {
        $result = $this->resolver->resolve($dimensionContent);
        self::assertInstanceOf(ContentView::class, $result);

        $content = $result->getContent();
        self::assertIsArray($content);

        return $content;
    }

    private function contentViewAt(ProductDimensionContent $dimensionContent, string $key): ContentView
    {
        $view = $this->resolveContent($dimensionContent)[$key];
        self::assertInstanceOf(ContentView::class, $view);

        return $view;
    }

    public function testReturnsNullForNonProductDimensionContent(): void
    {
        $other = $this->prophesize(DimensionContentInterface::class);

        self::assertNull($this->resolver->resolve($other->reveal()));
    }

    public function testResolvesEntityOwnedFields(): void
    {
        $family = new ProductFamily();
        $family->setUuid('fam-uuid');

        $dc = $this->makeDimensionContent();
        $dc->setCode('SKU-1');
        $dc->setExternalIdentifier('EXT-1');
        $dc->setProductFamily($family);
        $dc->setStatus('available');

        self::assertSame('SKU-1', $this->contentViewAt($dc, 'code')->getContent());
        self::assertSame('EXT-1', $this->contentViewAt($dc, 'externalIdentifier')->getContent());
        self::assertSame('fam-uuid', $this->contentViewAt($dc, 'productFamily')->getContent());
        self::assertSame('available', $this->contentViewAt($dc, 'status')->getContent());
    }

    public function testResolvesNullEntityOwnedFields(): void
    {
        $dc = $this->makeDimensionContent();

        self::assertNull($this->contentViewAt($dc, 'code')->getContent());
        self::assertNull($this->contentViewAt($dc, 'externalIdentifier')->getContent());
        self::assertNull($this->contentViewAt($dc, 'productFamily')->getContent());
        self::assertNull($this->contentViewAt($dc, 'status')->getContent());
    }

    public function testResolvesBucketFieldByItsXmlType(): void
    {
        $this->givenFormMetadata(['details/shortDescription' => 'text_editor']);

        $dc = $this->makeDimensionContent(['shortDescription' => '<p>hi</p>']);

        self::assertSame('<p>hi</p>', $this->contentViewAt($dc, 'shortDescription')->getContent());
    }

    public function testResolvesImageThroughSingleMediaSelectionPropertyResolver(): void
    {
        $this->givenFormMetadata(['details/image' => 'single_media_selection']);

        // regression: the bucket stores {"id": 5} verbatim, so the property resolver gets the
        // array it expects. Storing a bare 5 made it bail and emit id: null.
        $dc = $this->makeDimensionContent(['image' => ['id' => 5]]);

        $resolvable = $this->contentViewAt($dc, 'image')->getContent();

        self::assertInstanceOf(ResolvableResource::class, $resolvable);
        self::assertSame(5, $resolvable->getId());
        self::assertSame(MediaResourceLoader::getKey(), $resolvable->getResourceLoaderKey());
        self::assertSame(MediaInterface::RESOURCE_KEY, $resolvable->getResourceKey());
        self::assertSame(-50, $resolvable->getPriority());
    }

    public function testResolvesEmptyImage(): void
    {
        $this->givenFormMetadata(['details/image' => 'single_media_selection']);

        $imageView = $this->contentViewAt($this->makeDimensionContent(), 'image');

        self::assertNull($imageView->getContent());
        self::assertNull($imageView->getView()['id']);
    }

    public function testResolvesDocumentsThroughMediaSelectionPropertyResolver(): void
    {
        $this->givenFormMetadata(['details/documents' => 'media_selection']);

        $dc = $this->makeDimensionContent(['documents' => ['ids' => [7, 9]]]);

        $resolvables = $this->contentViewAt($dc, 'documents')->getContent();
        self::assertIsArray($resolvables);
        self::assertCount(2, $resolvables);

        $first = $resolvables[0];
        $second = $resolvables[1];
        self::assertInstanceOf(ResolvableResource::class, $first);
        self::assertInstanceOf(ResolvableResource::class, $second);

        self::assertSame(7, $first->getId());
        self::assertSame(9, $second->getId());
        self::assertSame(MediaResourceLoader::getKey(), $first->getResourceLoaderKey());
        self::assertSame(MediaInterface::RESOURCE_KEY, $first->getResourceKey());
    }

    public function testResolvesEmptyDocuments(): void
    {
        $this->givenFormMetadata(['details/documents' => 'media_selection']);

        $documentsView = $this->contentViewAt($this->makeDimensionContent(), 'documents');

        self::assertSame([], $documentsView->getContent());
    }

    public function testResolvesProjectDefinedBucketField(): void
    {
        // a field the bundle knows nothing about, declared only in a project's form fragment
        $this->givenFormMetadata(['details/datasheet' => 'single_media_selection']);

        $dc = $this->makeDimensionContent(['datasheet' => ['id' => 77]]);

        $resolvable = $this->contentViewAt($dc, 'datasheet')->getContent();

        self::assertInstanceOf(ResolvableResource::class, $resolvable);
        self::assertSame(77, $resolvable->getId());
    }

    public function testIgnoresFormPropertiesOutsideTheDetailsBucket(): void
    {
        $this->givenFormMetadata([
            'title' => 'text_line',
            'details' => 'text_line',
            'details/shortDescription' => 'text_editor',
        ]);

        $content = $this->resolveContent($this->makeDimensionContent(['shortDescription' => '<p>hi</p>']));

        self::assertArrayHasKey('shortDescription', $content);
        self::assertArrayNotHasKey('title', $content);
    }

    public function testResolvesWithoutDetailsWhenFormMetadataIsMissing(): void
    {
        // no givenFormMetadata() — the loader returns null for an unknown form
        $dc = $this->makeDimensionContent(['shortDescription' => '<p>hi</p>']);
        $dc->setCode('SKU-1');

        $content = $this->resolveContent($dc);

        self::assertArrayNotHasKey('shortDescription', $content);
        self::assertArrayHasKey('code', $content);
    }
}
