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
use Sulu\Component\Security\UserRepositoryInterface;

class ProductManager implements ProductManagerInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(
        ProductRepositoryInterface $productRepository,
        UserRepositoryInterface $userRepository,
        ObjectManager $em
    )
    {
        $this->productRepository = $productRepository;
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdAndLocale($id, $locale)
    {
        $product = $this->productRepository->findByIdAndLocale($id, $locale);

        if ($product) {
            return new Product($product, $locale);
        } else {
            return null;
        }
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

    /**
     * {@inheritDoc}
     */
    public function save(Product $product, $userId)
    {
        $user = $this->userRepository->findUserById($userId);

        $product->setChanged(new \DateTime());
        $product->setChanger($user);

        if ($product->getId() == null) {
            $product->setCreated(new \DateTime());
            $product->setCreator($user);
            $this->em->persist($product->getEntity());
        }

        $this->em->flush();

        return $product;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Product $product, $userId)
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
