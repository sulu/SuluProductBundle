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

namespace Sulu\Product\Infrastructure\Sulu\Search\Visitor;

use CmsIg\Seal\Converter\HtmlToTextConverter;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Indexes the details tab: external identifier, family name and short description as content, and
 * the details image as fallback.
 *
 * @internal this class is internal no backwards compatibility promise is given for this class
 *           use Symfony Dependency Injection to override or create your own enhancer instead
 */
final class WebsiteProductDetailsReindexProviderEnhancer implements WebsiteProductReindexProviderEnhancerInterface
{
    public function enhanceQuery(QueryBuilder $queryBuilder): void
    {
        $queryBuilder
            ->leftJoin('unlocalizedDimensionContent.productFamily', 'productFamily')
            ->leftJoin('productFamily.translations', 'productFamilyTranslation', Join::WITH, 'productFamilyTranslation.locale = dimensionContent.locale')
            ->addSelect('unlocalizedDimensionContent.externalIdentifier')
            ->addSelect('productFamily.uuid AS productFamilyId')
            ->addSelect('productFamilyTranslation.name AS productFamilyName')
            ->addSelect('dimensionContent.detailsData')
            ->addSelect('unlocalizedDimensionContent.detailsData AS unlocalizedDetailsData');
    }

    public function enhanceDocument(array $queryResult, array $document): array
    {
        /** @var list<string> $content */
        $content = $document['content'];

        foreach (['externalIdentifier', 'productFamilyName'] as $key) {
            $value = $queryResult[$key] ?? null;
            if (\is_string($value) && '' !== $value) {
                $content[] = $value;
            }
        }

        // Localized details win over unlocalized ones.
        $detailsData = \array_merge(
            \is_array($queryResult['unlocalizedDetailsData'] ?? null) ? $queryResult['unlocalizedDetailsData'] : [],
            \is_array($queryResult['detailsData'] ?? null) ? $queryResult['detailsData'] : [],
        );

        $shortDescription = $detailsData['shortDescription'] ?? null;
        if (\is_string($shortDescription)) {
            $text = HtmlToTextConverter::convert($shortDescription);
            if ('' !== $text) {
                $content[] = $text;
            }
        }

        $image = $detailsData['image'] ?? null;
        if ('' === ($document['mediaId'] ?? '') && \is_array($image) && isset($image['id']) && \is_numeric($image['id'])) {
            $document['mediaId'] = (string) $image['id'];
        }

        $document['content'] = \array_values(\array_unique($content));

        return $document;
    }
}
