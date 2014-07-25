<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Routing;

use Sulu\Component\Webspace\Analyzer\RequestAnalyzer;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Symfony\Cmf\Component\Routing\RouteProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ProductRouteProvider implements RouteProviderInterface
{
    /**
     * @var RequestAnalyzer
     */
    private $requestAnalyzer;

    public function __construct(RequestAnalyzerInterface $requestAnalayzer)
    {
        $this->requestAnalyzer = $requestAnalayzer;
    }

    /**
     * {@inheritDoc}
     */
    public function getRouteCollectionForRequest(Request $request)
    {
        $collection = new RouteCollection();

        return $collection;
    }

    /**
     * {@inheritDoc}
     */
    public function getRouteByName($name)
    {
        // TODO: Implement getRouteByName() method.
    }

    /**
     * {@inheritDoc}
     */
    public function getRoutesByNames($names)
    {
        // TODO: Implement getRoutesByNames() method.
    }
}
