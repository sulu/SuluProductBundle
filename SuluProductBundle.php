<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Sulu\Bundle\PersistenceBundle\PersistenceBundleTrait;

class SuluProductBundle extends Bundle
{
    use PersistenceBundleTrait;

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $this->buildPersistence(
            array(
                'Sulu\Bundle\ProductBundle\Entity\ProductInterface' => 'sulu.model.product.class',
            ),
            $container
        );
    }
}
