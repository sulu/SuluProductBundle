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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Product\Application\Mcp\ProductCompletenessChecker;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Tests\Unit\Fixture\ArrayMetadataProvider;
use Sulu\Product\Tests\Unit\Fixture\CompletenessCheckerFactory;

#[CoversClass(ProductCompletenessChecker::class)]
final class ProductCompletenessCheckerTest extends TestCase
{
    public function testACompleteProductHasNoRecommendations(): void
    {
        $checker = CompletenessCheckerFactory::create($this->mediaProvider(), ['en', 'de']);

        $this->assertSame([], $checker->check($this->productWithLocales(['en', 'de']), $this->completeData(), 'en'));
    }

    public function testAnEmptyProductGetsEveryRecommendation(): void
    {
        $checker = CompletenessCheckerFactory::create($this->mediaProvider(), ['en', 'de']);

        $recommendations = $checker->check($this->productWithLocales(['en']), ['title' => 'Shirt'], 'en');

        $this->assertCount(10, $recommendations);
        $text = \implode("\n", $recommendations);
        foreach ([
            'No code.', 'No url.', 'sulu_category_list', 'sulu_tag_list', 'seo.title', 'seo.description',
            'details.image', 'details.documents', '{"ids"', 'excerpt.image', 'sulu_media_list', '"de"',
        ] as $expected) {
            $this->assertStringContainsString($expected, $text);
        }
    }

    public function testMediaFieldsAreOnlyCheckedWhenTheFormDeclaresThem(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en']);

        $text = \implode("\n", $checker->check($this->productWithLocales(['en']), ['title' => 'Shirt'], 'en'));

        $this->assertStringNotContainsString('details.', $text);
        $this->assertStringNotContainsString('excerpt.image', $text);
    }

    public function testAnEmptyMediaValueCountsAsMissing(): void
    {
        $checker = CompletenessCheckerFactory::create($this->mediaProvider(), ['en']);
        $data = $this->completeData();
        $data['details'] = ['image' => ['id' => null], 'documents' => ['ids' => []]];

        $recommendations = $checker->check($this->productWithLocales(['en']), $data, 'en');

        $this->assertCount(2, $recommendations);
    }

    public function testAProductWithVariantsNeedsNoCodeOrUrl(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en']);
        $product = $this->productWithLocales(['en']);
        $product->setType(ProductInterface::TYPE_PRODUCT_WITH_VARIANTS);

        $text = \implode("\n", $checker->check($product, ['title' => 'Shirt'], 'en'));

        $this->assertStringNotContainsString('No code.', $text);
        $this->assertStringNotContainsString('No url.', $text);
    }

    public function testAVariantGetsCodeUrlLocaleAndDetailsHintsButNoContentHints(): void
    {
        $checker = CompletenessCheckerFactory::create($this->mediaProvider(), ['en', 'de']);
        $variant = $this->productWithLocales(['en']);
        $variant->setType(ProductInterface::TYPE_VARIANT);

        $text = \implode("\n", $checker->check($variant, ['title' => 'Shirt red'], 'en'));

        $this->assertStringContainsString('No code.', $text);
        $this->assertStringContainsString('sulu_product_variant_update', $text);
        $this->assertStringContainsString('No url. The url of a variant is set in the admin', $text);
        $this->assertStringContainsString('details.image', $text);
        $this->assertStringContainsString('details.documents', $text);
        $this->assertStringContainsString('No content in "de"', $text);
        $this->assertStringNotContainsString('sulu_category_list', $text);
        $this->assertStringNotContainsString('seo.', $text);
    }

    public function testACompleteVariantGetsNoCodeOrUrlHint(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en']);
        $variant = $this->productWithLocales(['en']);
        $variant->setType(ProductInterface::TYPE_VARIANT);

        $this->assertSame([], $checker->check($variant, ['code' => 'V-1', 'url' => '/products/v-1'], 'en'));
    }

    public function testLocalesFromTheUnlocalizedDimensionCountAsContent(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en', 'de', 'fr']);
        $product = $this->productWithLocales(['en']);
        $unlocalized = new ProductDimensionContent($product);
        $unlocalized->setStage(DimensionContentInterface::STAGE_DRAFT);
        $unlocalized->addAvailableLocale('en');
        $unlocalized->addAvailableLocale('de');
        $product->addDimensionContent($unlocalized);

        $text = \implode("\n", $checker->check($product, $this->completeData(), 'en'));

        $this->assertStringContainsString('"fr"', $text);
        $this->assertStringNotContainsString('"de"', $text);
    }

    public function testAUrlGivenAsAStringCountsAsPresent(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en']);
        $data = $this->completeData();
        $data['url'] = '/products/shirt';

        $text = \implode("\n", $checker->check($this->productWithLocales(['en']), $data, 'en'));

        $this->assertStringNotContainsString('No url.', $text);
    }

    public function testAnEmptyUrlStringCountsAsMissing(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en']);
        $data = $this->completeData();
        $data['url'] = '';

        $text = \implode("\n", $checker->check($this->productWithLocales(['en']), $data, 'en'));

        $this->assertStringContainsString('No url.', $text);
    }

    public function testMediaFieldsAreSkippedWhenTheMetadataIsNotAForm(): void
    {
        $checker = CompletenessCheckerFactory::create((new ArrayMetadataProvider())->setDefault($this->createStub(MetadataInterface::class)), ['en']);

        $text = \implode("\n", $checker->check($this->productWithLocales(['en']), $this->completeData(), 'en'));

        $this->assertSame('', $text);
    }

    public function testContentOfAnotherStageDoesNotCountAsALocale(): void
    {
        $checker = CompletenessCheckerFactory::create(null, ['en', 'de']);
        $product = $this->productWithLocales(['en']);
        $live = new ProductDimensionContent($product);
        $live->setLocale('de');
        $live->setStage(DimensionContentInterface::STAGE_LIVE);
        $product->addDimensionContent($live);

        $text = \implode("\n", $checker->check($product, $this->completeData(), 'en'));

        $this->assertStringContainsString('"de"', $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeData(): array
    {
        return [
            'title' => 'Shirt',
            'code' => 'SHIRT-1',
            'url' => ['page' => ['uuid' => 'p', 'path' => '/products'], 'suffix' => '/shirt'],
            'excerptCategories' => [1],
            'excerptTags' => [2],
            'seo' => ['title' => 'Shirt', 'description' => 'A shirt'],
            'details' => ['image' => ['id' => 3], 'documents' => ['ids' => [4]]],
            'excerpt' => ['image' => ['id' => 5]],
        ];
    }

    /**
     * @param list<string> $locales
     */
    private function productWithLocales(array $locales): Product
    {
        $product = new Product('uuid-1');
        foreach ($locales as $locale) {
            $content = new ProductDimensionContent($product);
            $content->setLocale($locale);
            $content->setStage(DimensionContentInterface::STAGE_DRAFT);
            $product->addDimensionContent($content);
        }

        return $product;
    }

    private function mediaProvider(): ArrayMetadataProvider
    {
        $details = new FormMetadata();
        $details->addItem($this->field('details/image', 'single_media_selection'));
        $details->addItem($this->field('details/documents', 'media_selection'));
        $details->addItem($this->field('details/shortDescription', 'text_editor'));

        $excerpt = new FormMetadata();
        $excerpt->addItem($this->field('excerpt/title', 'text_line'));
        $excerpt->addItem($this->field('excerpt/image', 'single_media_selection'));

        return new ArrayMetadataProvider([
            ProductInterface::FORM_KEY => $details,
            'content_excerpt_metadata' => $excerpt,
        ]);
    }

    private function field(string $name, string $type): FieldMetadata
    {
        $field = new FieldMetadata($name);
        $field->setType($type);

        return $field;
    }
}
