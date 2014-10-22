<?php

use Symfony\Component\Config\Loader\LoaderInterface;
use Sulu\Component\HttpKernel\SuluKernel;

class AppKernel extends SuluKernel
{
    public function __construct($environment, $debug)
    {
        parent::__construct($environment, $debug);
        $this->setContext(SuluKernel::CONTEXT_ADMIN);
    }

    public function registerBundles()
    {
        $bundles = array(
            // Dependencies
            new \Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new \Symfony\Bundle\TwigBundle\TwigBundle(),
            new \Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new \Symfony\Bundle\MonologBundle\MonologBundle(),
            new \Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new \JMS\SerializerBundle\JMSSerializerBundle(),
            new \FOS\RestBundle\FOSRestBundle(),
            new \Bazinga\Bundle\HateoasBundle\BazingaHateoasBundle(),
            new \Liip\ThemeBundle\LiipThemeBundle(),


            // Sulu
            new \Sulu\Bundle\CoreBundle\SuluCoreBundle(),
            new \Sulu\Bundle\AdminBundle\SuluAdminBundle(),
            new \Sulu\Bundle\ProductBundle\SuluProductBundle(),
            new \Doctrine\Bundle\PHPCRBundle\DoctrinePHPCRBundle(),
            new \Sulu\Bundle\TestBundle\SuluTestBundle(),
            new \Sulu\Bundle\ContactBundle\SuluContactBundle(),
            new \Sulu\Bundle\TagBundle\SuluTagBundle(),
            new \Sulu\Bundle\WebsiteBundle\SuluWebsiteBundle(),
            new \Sulu\Bundle\CategoryBundle\SuluCategoryBundle(),
            new \Sulu\Bundle\MediaBundle\SuluMediaBundle(),
        );

        return $bundles;
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config.yml');
    }
}
