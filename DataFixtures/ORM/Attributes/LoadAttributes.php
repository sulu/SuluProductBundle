<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\DataFixtures\ORM\Attributes;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Sulu\Bundle\ProductBundle\Entity\Attribute;
use Sulu\Bundle\ProductBundle\Entity\AttributeTranslation;
use Sulu\Bundle\ProductBundle\Entity\AttributeType;
use Sulu\Bundle\ProductBundle\Entity\AttributeTypeRepository;
use Sulu\Bundle\ProductBundle\Entity\AttributeValue;
use Sulu\Bundle\ProductBundle\Entity\AttributeValueTranslation;
use Sulu\Bundle\ProductBundle\Traits\XMLFixtureLoaderTrait;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadAttributes implements FixtureInterface, ContainerAwareInterface
{
    use XMLFixtureLoaderTrait;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var string
     */
    private $nodePath = '/attributes/attribute';

    /**
     * @var AttributeTypeRepository
     */
    private $attributeTypeRepository;

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $this->attributeTypeRepository = $manager->getRepository('SuluProductBundle:AttributeType');

        $xmlFiles = $this->container->getParameter('sulu_product.fixtures.attributes');
        $projectRoot = $this->container->getParameter('kernel.root_dir') . '/../';

        foreach ($xmlFiles as $xmlFile) {
            $path = $projectRoot . $xmlFile;
            $this->createAttributes($this->loadElementsFromXmlFileWithPath($path, $this->nodePath), $manager);
        }
    }

    /**
     * @param \DOMNodeList $nodeList
     * @param ObjectManager $manager
     *
     * @throws EntityNotFoundException
     */
    protected function createAttributes(\DOMNodeList $nodeList, ObjectManager $manager)
    {
        /** @var \DOMNode $element */
        foreach ($nodeList as $element) {
            $attribute = new Attribute();
            $children = $element->childNodes;

            /** @var \DOMNode $child */
            foreach ($children as $child) {
                if (isset($child->nodeName) && $child->nodeName === 'key') {
                    $attribute->setKey($child->nodeValue);
                    continue;
                }
                if (isset($child->nodeName) && $child->nodeName === 'type') {
                    $type = $this->getType($child->nodeValue);

                    $attribute->setType($type);
                    continue;
                }

                /** @var \DOMNode $childNode */
                foreach ($child->childNodes as $childNode) {
                    switch ($childNode->nodeName) {
                        case 'name':
                            $locale = $childNode->attributes->getNamedItem('locale')->nodeValue;
                            $translation = $this->createAttributeTranslation(
                                $childNode->nodeValue,
                                $locale,
                                $attribute
                            );
                            $manager->persist($translation);

                            break;
                        case 'value':
                            $this->createAttributeValue($attribute, $childNode, $manager);

                            break;
                    }
                }
            }
            $manager->persist($attribute);
        }

        $manager->flush();
    }

    /**
     * @param Attribute $attribute
     * @param \DOMNode $node
     * @param ObjectManager $manager
     *
     * @return AttributeValue
     */
    protected function createAttributeValue(Attribute $attribute, \DOMNode $node, ObjectManager $manager)
    {
        $value = new AttributeValue();
        $value->setAttribute($attribute);

        $manager->persist($value);

        $attribute->addValue($value);

        foreach ($node->childNodes as $childNode) {
            $locale = $childNode->attributes->getNamedItem('locale')->nodeValue;
            $translation = $this->createAttributeValueTranslation(
                $childNode->nodeValue,
                $locale
            );
            $translation->setAttributeValue($value);
            $value->addTranslation($translation);

            $manager->persist($translation);
        }

        return $value;
    }

    /**
     * @param string $name
     * @param string $locale
     * @param Attribute $attribute
     *
     * @return AttributeTranslation
     */
    protected function createAttributeTranslation($name, $locale, Attribute $attribute)
    {
        $translation = new AttributeTranslation();
        $translation->setName($name);
        $translation->setLocale($locale);
        $translation->setAttribute($attribute);

        $attribute->addTranslation($translation);

        return $translation;
    }

    /**
     * @param string $name
     * @param string $locale
     *
     * @return AttributeValueTranslation
     */
    protected function createAttributeValueTranslation($name, $locale)
    {
        $translation = new AttributeValueTranslation();
        $translation->setName($name);
        $translation->setLocale($locale);

        return $translation;
    }

    /**
     * @param int $id
     *
     * @return AttributeType
     *
     * @throws EntityNotFoundException
     */
    protected function getType($id)
    {
        $type = $this->attributeTypeRepository->find($id);
        if (!$type) {
            throw new EntityNotFoundException(AttributeType::class, $id);
        }

        return $type;
    }
}
