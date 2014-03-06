<?php

namespace Sulu\Bundle\Product\BaseBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TemplateController extends Controller
{

    /**
     * Returns Template for product list
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function productListAction()
    {
        return $this->render('SuluProductBaseBundle:Template:product.list.html.twig');
    }

    /**
     * Returns Template for product import
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function productImportAction()
    {
        return $this->render('SuluProductBaseBundle:Template:product.import.html.twig');
    }
}
