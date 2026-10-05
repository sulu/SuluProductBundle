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

namespace Sulu\Product\Tests\Unit\Fixture;

use Prophecy\Argument;
use Prophecy\Prophet;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TagMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;
use Sulu\Product\Application\Mcp\ProductUrlHelper;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Route\Application\ResourceLocator\PathCleanup\PathCleanup;
use Sulu\Route\Application\ResourceLocator\ResourceLocatorGenerator;
use Sulu\Route\Application\ResourceLocator\RouteSchemaEvaluator;
use Sulu\Route\Domain\Repository\RouteRepositoryInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class ProductUrlHelperFactory
{
    /**
     * @param list<string> $takenSlugs routes that exist already
     */
    public static function create(
        string $routeType = 'page_tree_route',
        array $takenSlugs = [],
        string $routeSchema = "/products/{implode('-', object)}",
        ?MetadataProviderInterface $formMetadataProvider = null,
    ): ProductUrlHelper {
        $prophet = new Prophet();
        $pathCleanup = new PathCleanup(new AsciiSlugger(), []);

        $routeRepository = $prophet->prophesize(RouteRepositoryInterface::class);
        $routeRepository->existBy(Argument::that(
            static fn (array $criteria): bool => \in_array($criteria['slug'] ?? null, $takenSlugs, true),
        ))->willReturn(true);
        $routeRepository->existBy(Argument::any())->willReturn(false);

        $generator = new ResourceLocatorGenerator(
            $routeRepository->reveal(),
            new RouteSchemaEvaluator($prophet->prophesize(TranslatorInterface::class)->reveal(), $pathCleanup),
        );

        return new ProductUrlHelper(
            $routeType,
            ['route_schema' => $routeSchema],
            $pathCleanup,
            $generator,
            $formMetadataProvider ?? self::detailsForm(),
        );
    }

    /**
     * The product form as the bundle ships it: the title is the only route part, the code is a field
     * of the form that the route generator of the admin never receives.
     */
    public static function detailsForm(): ArrayMetadataProvider
    {
        $title = new FieldMetadata('title');
        $title->setType('text_line');
        $tag = new TagMetadata();
        $tag->setName('sulu.rlp.part');
        $title->addTag($tag);

        $code = new FieldMetadata('code');
        $code->setType('text_line');

        $details = new FormMetadata();
        $details->addItem($title);
        $details->addItem($code);

        return new ArrayMetadataProvider([ProductInterface::FORM_KEY => $details]);
    }
}
