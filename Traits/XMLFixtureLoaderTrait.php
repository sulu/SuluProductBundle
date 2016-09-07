<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Traits;

trait XMLFixtureLoaderTrait
{
    /**
     * @param string $xmlFile
     * @param string $nodePath
     *
     * @return \DOMNodeList
     */
    protected function loadElementsFromXmlFileWithPath($xmlFile, $nodePath)
    {
        $xml = file_get_contents($xmlFile);
        // Remove whitespaces between closing tag and opening tag.
        $xml = preg_replace('/>\s+</', '><', $xml);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $xpath = new \DOMXpath($doc);
        $elements = $xpath->query($nodePath);

        return $elements;
    }
}
