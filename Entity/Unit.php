<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Entity;

use Doctrine\Common\Collections\Collection;

class Unit
{
    // The id for the unit type PIECE which is the default type.
    const PIECE_ID = 1;

    /**
     * @var int
     */
    private $id;

    /**
     * @var Collection
     */
    private $translations;

    /**
     * @var Collection
     */
    private $mappings;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->translations = new \Doctrine\Common\Collections\ArrayCollection();
    }

    /**
     * @param int $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param UnitTranslation $translations
     *
     * @return self
     */
    public function addTranslation(UnitTranslation $translations)
    {
        $this->translations[] = $translations;

        return $this;
    }

    /**
     * @param UnitTranslation $translations
     */
    public function removeTranslation(UnitTranslation $translations)
    {
        $this->translations->removeElement($translations);
    }

    /**
     * @return Collection
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * @param UnitMapping $mappings
     *
     * @return self
     */
    public function addMapping(UnitMapping $mappings)
    {
        $this->mappings[] = $mappings;

        return $this;
    }

    /**
     * @param UnitMapping $mappings
     */
    public function removeMapping(UnitMapping $mappings)
    {
        $this->mappings->removeElement($mappings);
    }

    /**
     * @return Collection
     */
    public function getMappings()
    {
        return $this->mappings;
    }

    /**
     * Returns the translation for the given locale.
     *
     * @param string $locale
     *
     * @return Translation
     */
    public function getTranslation($locale)
    {
        $translation = null;

        // Use first translation as a fallback.
        if (count($this->translations) > 0) {
            $translation = $this->translations[0];
        }

        /** @var UnitTranslation $translationData */
        foreach ($this->translations as $translationData) {
            if ($translationData->getLocale() == $locale) {
                $translation = $translationData;
                break;
            }
        }

        return $translation;
    }
}
