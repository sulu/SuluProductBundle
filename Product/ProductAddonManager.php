<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Api\Addon as ApiAddon;
use Sulu\Bundle\ProductBundle\Entity\Addon;
use Sulu\Bundle\ProductBundle\Entity\AddonPrice;
use Sulu\Bundle\ProductBundle\Entity\Currency;
use Sulu\Bundle\ProductBundle\Entity\CurrencyRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductAddonRepository;
use Sulu\Component\Rest\Exception\EntityNotFoundException;

class ProductAddonManager implements ProductAddonManagerInterface
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ProductFactoryInterface
     */
    protected $productFactory;

    /**
     * @var ObjectManager
     */
    protected $entityManager;

    /**
     * @var ProductAddonRepository
     */
    protected $addonRepository;

    /**
     * @var CurrencyRepository
     */
    protected $currencyRepository;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductFactoryInterface $productFactory
     * @param ProductAddonRepository $addonRepository
     * @param ObjectManager $entityManager
     * @param CurrencyRepository $currencyRepository
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductFactoryInterface $productFactory,
        ProductAddonRepository $addonRepository,
        ObjectManager $entityManager,
        CurrencyRepository $currencyRepository
    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->addonRepository = $addonRepository;
        $this->entityManager = $entityManager;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function findAddonsByProductIdAndLocale($id, $locale)
    {
        $product = $this->productRepository->findByIdAndLocale($id, $locale);

        $apiAddons = [];
        foreach ($product->getAddons() as $addon) {
            $apiAddons[] = new ApiAddon($addon, $this->productFactory, $locale);
        }

        return $apiAddons;
    }

    /**
     * {@inheritdoc}
     */
    public function findAddonById($id, $locale)
    {
        /** @var Addon $addon */
        $addon = $this->addonRepository->find($id);
        if (!$addon) {
            throw new EntityNotFoundException('SuluProductBundle:Addon', $id);
        }

        return new ApiAddon($addon, $this->productFactory, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function saveProductAddon($productId, $addonId, $locale, array $prices = null)
    {
        $productAddon = $this->addonRepository->findOneBy(
            [
                'product' => $productId,
                'addon' => $addonId,
            ]
        );
        if (!$productAddon) {
            $product = $this->productRepository->findById($productId);
            $addon = $this->productRepository->findById($addonId);

            if (!$product) {
                throw new EntityNotFoundException('SuluProductBundle:Product', $productId);
            }
            if (!$addon) {
                throw new EntityNotFoundException('SuluProductBundle:Product', $addonId);
            }

            $productAddon = new Addon();
            $productAddon->setAddon($addon);
            $productAddon->setProduct($product);

            $this->entityManager->persist($productAddon);
        }

        $addonPrices = [];
        foreach ($prices as $price) {
            $addonPrice = new AddonPrice();
            $addonPrice->setAddon($productAddon);
            $addonPrice->setPrice($price['value']);
            /** @var Currency $currency */
            $currency = $this->currencyRepository->findByCode($price['currency']);
            if (!$currency) {
                throw new EntityNotFoundException('SuluProductBundle:Currency', $price['currency']);
            }
            $addonPrice->setCurrency($currency);

            $addonPrices[] = $addonPrice;
        }

        /** @var AddonPrice $savedPrice */
        // Delete old entries
        foreach ($productAddon->getAddonPrices()->toArray() as $savedPrice) {
            $isFound = false;
            foreach ($addonPrices as $addonPrice) {
                if ($addonPrice->getCurrency() === $savedPrice->getCurrency()) {
                    $isFound = true;
                }
            }
            if (!$isFound) {
                $this->entityManager->remove($savedPrice);
            }
        }

        /** @var AddonPrice $addonPrice */
        // Save/Update entries
        foreach ($addonPrices as $addonPrice) {
            $isNew = true;
            foreach ($productAddon->getAddonPrices()->toArray() as $savedPrice) {
                if ($addonPrice->getCurrency() === $savedPrice->getCurrency()) {
                    $savedPrice->setPrice($addonPrice->getPrice());
                    $isNew = false;
                }
            }
            if ($isNew) {
                $this->entityManager->persist($addonPrice);
                $productAddon->addAddonPrice($addonPrice);
            }
        }

        return new ApiAddon($productAddon, $this->productFactory, $locale);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteProductAddon($productId, $addonId)
    {
        $productAddon = $this->addonRepository->findOneBy(['product' => $productId, 'addon' => $addonId]);
        if ($productAddon) {
            $this->entityManager->remove($productAddon);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById($id, $flush = false)
    {
        $addon = $this->addonRepository->find($id);

        if (!$addon) {
            throw new EntityNotFoundException('SuluProductBundle:Addon', $id);
        }

        $this->entityManager->remove($addon);

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
