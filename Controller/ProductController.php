<?php

namespace Sulu\Bundle\Product\BaseBundle\Controller;

use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\Put;
use Sulu\Component\Rest\RestController;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends RestController implements ClassResourceInterface
{

    /**
     * returns all fields that can be used by list
     * @Get("products/fields")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getFieldsAction()
    {
        $columns = array();

        $columns[0] = array('id' => 'key',
                            'translation' => 'products.key',
                            'disabled' => true
        );
        $columns[1] = array('id' => 'contractual_article',
                            'translation' => 'products.contractual-article',
                            'disabled' => true
        );
        $columns[2] = array('id' => 'name',
                            'translation' => 'products.name'
        );
        $columns[3] = array('id' => 'article_number',
                            'translation' => 'products.article-number'
        );
        $columns[4] = array('id' => 'manufacturer',
                            'translation' => 'products.manufacturer',
                            'disabled' => true
        );
        $columns[5] = array('id' => 'categories',
                            'translation' => 'products.categories',
                            'disabled' => true
        );
        $columns[6] = array('id' => 'last_imported',
                            'translation' => 'products.last-imported',
                            'disabled' => true
        );

        $response = new Response(json_encode($columns));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * returns all products
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function cgetAction()
    {
        if ($this->getRequest()->get('flat') == 'true') {
            // flat structure
            $rows = array();
            $rows[0] = array('key' => '6478958997',
                             'contractual_article' => 'true',
                             'name' => 'Flachkopfwinkelschleifer WEF 9-125',
                             'article_number' => 'B00G4JK32',
                             'manufacturer' => 'Metabo',
                             'categories' => 'cat1, cat2',
                             'last_imported' => '23/01/1987');
            $rows[1] = array('key' => '1278958997',
                            'contractual_article' => 'true',
                            'name' => 'Flachkopfwinkelschleifer WEF 9-126',
                            'article_number' => 'AD0G4JK32',
                            'manufacturer' => 'Metabo',
                            'categories' => 'cat1, cat2',
                            'last_imported' => '23/01/1987');
            $rows[2] = array('key' => '3278958997',
                            'contractual_article' => 'false',
                            'name' => 'Winkelschleifer WEF 9-125',
                            'article_number' => 'X00G4JK32',
                            'manufacturer' => 'Metabo',
                            'categories' => 'cat1, cat2',
                            'last_imported' => '23/01/1987');
            $rows[3] = array('key' => '4578958997',
                            'contractual_article' => 'true',
                            'name' => 'Schleifer WEF 9-125',
                            'article_number' => 'B0654JK32',
                            'manufacturer' => 'Metabo',
                            'categories' => 'cat1, cat2',
                            'last_imported' => '23/01/1987');
            $rows[4] = array('key' => '7878958997',
                            'contractual_article' => 'false',
                            'name' => 'Bohrer WEF 9-125',
                            'article_number' => 'BATG4JK32',
                            'manufacturer' => 'Metabo',
                            'categories' => 'cat1, cat2',
                            'last_imported' => '23/01/1987');
            $rows[5] = array('key' => '9878958997',
                            'contractual_article' => 'true',
                            'name' => 'Flachkopfwinkelbohrer XAB 9-12',
                            'article_number' => 'B0IUZJK32',
                            'manufacturer' => 'Metabo',
                            'categories' => 'cat1, cat2',
                            'last_imported' => '23/01/1987');
        }
        $response = $this->createHalResponse($rows);
        $response['_links']['filter'] = '/admin/productbase/api/products?flat=true&fields={fieldsList}';
        $view = $this->view($response, 200);
        return $this->handleView($view);
    }

    /**
     * persists a setting
     * @Put("products/fields")
     */
    public function putFieldsAction()
    {
        return $this->responsePersistSettings();
    }
}
