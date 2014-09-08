<?php
/*
 * This file is part of the Sulu CMF.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Controller;

use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\WebsiteBundle\Navigation\NavigationMapperInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * This Controller is responsible for the rendering of the product
 * @package Sulu\Bundle\ProductBundle\Controller
 */
class ProductWebsiteController
{
    /**
     * @var EngineInterface
     */
    private $templating;

    /**
     * @var ProductManagerInterface
     */
    private $productManager;

    /**
     * @var RequestAnalyzerInterface
     */
    private $requestAnalyzer;

    /**
     * @var string
     */
    private $productTemplate;

    public function __construct(
        EngineInterface $templating,
        ProductManagerInterface $productManager,
        RequestAnalyzerInterface $requestAnalyzer,
        NavigationMapperInterface $navigationMapper,
        $productTemplate
    ) {
        $this->templating = $templating;
        $this->productManager = $productManager;
        $this->requestAnalyzer = $requestAnalyzer;

        $this->navigationMapper = $navigationMapper;
        $this->productTemplate = $productTemplate;
    }

    public function displayAction($id)
    {
        $navigation = $this->navigationMapper->getRootNavigation(
            $this->requestAnalyzer->getCurrentWebspace()->getKey(),
            $this->requestAnalyzer->getCurrentLocalization()->getLocalization(),
            1,
            false
        );

        $product = $this->productManager->findByIdAndLocale(
            $id,
            $this->requestAnalyzer->getCurrentLocalization()->getLocalization()
        );

        if ($product) {
            return $this->templating->renderResponse(
                $this->productTemplate,
                array('product' => $product, 'navigation' => $navigation)
            );
        }

        throw new NotFoundHttpException(sprintf('The product ID "%s" has not been found', $id));
    }
} 
