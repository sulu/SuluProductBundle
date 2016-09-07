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

use Sulu\Bundle\ProductBundle\Api\Status;
use Sulu\Bundle\ProductBundle\Entity\StatusRepository;

/**
 * Manager responsible for product statuses.
 */
class StatusManager
{
    /**
     * @var StatusRepository
     */
    private $statusRepository;

    public function __construct(StatusRepository $statusRepository)
    {
        $this->statusRepository = $statusRepository;
    }

    /**
     * @param $locale
     *
     * @return null|Status[]
     */
    public function findAll($locale)
    {
        $statuses = $this->statusRepository->findAllByLocale($locale);

        array_walk(
            $statuses,
            function (&$status) use ($locale) {
                $status = new Status($status, $locale);
            }
        );

        return $statuses;
    }
}
