<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\ProductBundle\Api\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusRepository;

/**
 * Manager responsible for product delivery statuses
 * @package Sulu\Bundle\ProductBundle\Product
 */
class DeliveryStatusManager
{
    /**
     * @var DeliveryStatusRepository
     */
    private $deliveryStatusRepository;

    public function __construct(DeliveryStatusRepository $deliveryStatusRepository)
    {
        $this->deliveryStatusRepository = $deliveryStatusRepository;
    }

    /**
     * @param $locale
     * @return null|Status[]
     */
    public function findAll($locale)
    {
        $statuses = $this->deliveryStatusRepository->findAllByLocale($locale);

        array_walk(
            $statuses,
            function (&$status) use ($locale) {
                $status = new DeliveryStatus($status, $locale);
            }
        );

        return $statuses;
    }
}
