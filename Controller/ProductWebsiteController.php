<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Controller;

use Sulu\Bundle\ProductBundle\Product\ProductManagerInterface;
use Sulu\Bundle\WebsiteBundle\Resolver\RequestAnalyzerResolverInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * This Controller is responsible for the rendering of the product.
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
     * @var RequestAnalyzerResolverInterface
     */
    private $requestAnalyzerResolver;

    /**
     * @var string
     */
    private $productTemplate;

    public function __construct(
        EngineInterface $templating,
        ProductManagerInterface $productManager,
        RequestAnalyzerInterface $requestAnalyzer,
        RequestAnalyzerResolverInterface $requestAnalyzerResolver,
        $productTemplate
    ) {
        $this->templating = $templating;
        $this->productManager = $productManager;
        $this->requestAnalyzer = $requestAnalyzer;
        $this->requestAnalyzerResolver = $requestAnalyzerResolver;
        $this->productTemplate = $productTemplate;
    }

    public function displayAction($id)
    {
        $product = $this->productManager->findByIdAndLocale(
            $id,
            $this->requestAnalyzer->getCurrentLocalization()->getLocalization()
        );

        if ($product) {
            return $this->templating->renderResponse(
                $this->productTemplate,
                array_merge(
                    ['product' => $product],
                    $this->requestAnalyzerResolver->resolve($this->requestAnalyzer)
                )
            );
        }

        throw new NotFoundHttpException(sprintf('The product ID "%s" has not been found', $id));
    }
}
