<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Application\Mapper;

use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Content\Application\ContentPersister\ContentPersisterInterface;
use Webmozart\Assert\Assert;

/**
 * @internal This class should be instantiated inside a project.
 *           Use the message to create or modify an product.
 *           Or inject all the mappers into a custom service.
 *           Create an own Mapper to extend the mapper with
 *           custom logic.
 */
final class ProductContentMapper implements ProductMapperInterface
{
    /**
     * @var ContentPersisterInterface
     */
    private $contentPersister;

    public function __construct(ContentPersisterInterface $contentPersister)
    {
        $this->contentPersister = $contentPersister;
    }

    public function mapProductData(ProductInterface $product, array $data): void
    {
        if (!\array_key_exists('template', $data)) {
            return;
        }

        $locale = $data['locale'] ?? null;
        Assert::string($locale);

        $dimensionAttributes = ['locale' => $locale];

        $this->contentPersister->persist($product, $data, $dimensionAttributes);
    }
}
