<?php

namespace Sulu\Bundle\ProductBundle\Controller;

use FOS\RestBundle\Routing\ClassResourceInterface;
use Hateoas\Representation\CollectionRepresentation;
use Sulu\Bundle\ProductBundle\Product\CurrencyManager;
use Sulu\Component\Rest\RestController;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CurrencyController extends RestController implements ClassResourceInterface, SecuredControllerInterface
{
    protected static $entityName = 'SuluProductBundle:Currency';
    protected static $entityKey = 'currencies';

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function cgetAction(Request $request)
    {
        $list = new CollectionRepresentation(
            $this->getManager()->findAll($this->getLocale($request)),
            self::$entityKey
        );

        $view = $this->view($list);

        return $this->handleView($view);
    }

    /**
     * @return CurrencyManager
     */
    protected function getManager()
    {
        return $this->get('sulu_product.currency_manager');
    }

    /**
     * {@inheritDoc}
     */
    public function getSecurityContext()
    {
        return 'sulu.product.products';
    }
}
