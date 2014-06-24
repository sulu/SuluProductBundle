<?php

namespace Sulu\Bundle\ProductBundle\Controller;

use Sulu\Bundle\ProductBundle\Entity\Status;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class TemplateController extends Controller
{
    /**
     * Returns Template for product list
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function productListAction()
    {
        return $this->render('SuluProductBundle:Template:product.list.html.twig');
    }

    /**
     * Returns Template for product list
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function productFormAction()
    {
        /** @var Status[] $statuses */
        $statuses = $this->getDoctrine()
            ->getRepository('Sulu\Bundle\ProductBundle\Entity\Status')
            ->findAll();

        $statusTitles = array();
        foreach ($statuses as $status) {
            $statusTitles[] = array(
                'id' => $status->getId(),
                'name' => $status->getId() // TODO use real language
            );
        }

        return $this->render('SuluProductBundle:Template:product.form.html.twig', array('status' => $statusTitles));
    }

    /**
     * Returns Template for product import
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function productImportAction()
    {
        return $this->render('SuluProductBundle:Template:product.import.html.twig');
    }
}
