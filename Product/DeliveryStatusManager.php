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

use Sulu\Bundle\ProductBundle\Api\DeliveryStatus;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatus as DeliveryStatusEntity;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusRepository;
use Sulu\Bundle\ProductBundle\Entity\DeliveryStatusTranslation;

/**
 * Manager responsible for product delivery statuses.
 */
class DeliveryStatusManager
{
    /**
     * @var DeliveryStatusRepository
     */
    private $deliveryStatusRepository;

    /**
     * DeliveryStatusManager constructor.
     *
     * @param DeliveryStatusRepository $deliveryStatusRepository
     */
    public function __construct(DeliveryStatusRepository $deliveryStatusRepository)
    {
        $this->deliveryStatusRepository = $deliveryStatusRepository;
    }

    /**
     * Returns all delivery statuses by given locale.
     *
     * @param string $locale
     *
     * @return null|DeliveryStatus[]
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

    /**
     * Returns DeliveryStatusTranslation by given DeliveryStatus-id and locale.
     *
     * @param int $deliveryStatusId
     * @param string $locale
     *
     * @return null|DeliveryStatusTranslation
     */
    public function retrieveTranslationByIdAndLocale($deliveryStatusId, $locale)
    {
        /** @var DeliveryStatusEntity $deliveryStatus */
        $deliveryStatus = $this->deliveryStatusRepository->find($deliveryStatusId);

        if (!$deliveryStatus || $deliveryStatus->getTranslations()->count() < 1) {
            return null;
        }

        $deliveryStatusTranslation = $deliveryStatus->getTranslations()->first();

        /** @var DeliveryStatusTranslation $translation */
        foreach ($deliveryStatus->getTranslations() as $translation) {
            if ($translation->getLocale() === $locale) {
                $deliveryStatusTranslation = $translation;
                break;
            }
        }

        return $deliveryStatusTranslation;
    }
}
