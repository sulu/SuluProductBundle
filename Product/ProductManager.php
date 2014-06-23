<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Api\Product;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;

class ProductManager implements ProductManagerInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(ProductRepositoryInterface $productRepository, ObjectManager $em)
    {
        $this->productRepository = $productRepository;
        $this->em = $em;
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        $product = $this->productRepository->findByIdAndLocale($id, $locale);

        return new Product($product, $locale);
    }

    /**
     * {@inheritDoc}
     */
    public function findAllByLocale($locale, $filter = array())
    {
        if (empty($filter)) {
            $products = $this->productRepository->findAllByLocale($locale);
        } else {
            $products = $this->productRepository->findByLocaleAndFilter($locale, $filter);
        }

        array_walk(
            $products,
            function (&$product) use ($locale) {
                $product = new Product($product, $locale);
            }
        );

        return $products;
    }
}
