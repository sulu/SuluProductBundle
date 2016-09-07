<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Component\Security\Authentication\UserInterface;

class ProductLocaleManager
{
    /**
     * @var array
     */
    private $configuration;

    /**
     * @param array $configuration
     */
    public function __construct(array $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return string
     */
    public function getFallbackLocale()
    {
        return $this->configuration['fallback_locale'];
    }

    /**
     * Returns true if the given locale was configured.
     *
     * @param string $locale
     *
     * @return bool
     */
    public function isLocaleConfigured($locale)
    {
        return in_array($locale, $this->configuration['locales']);
    }

    /**
     * Function returns the locale that should be used by default.
     * If request-locale is set, then use this one.
     * Else If users locale matches any of the given locales, that one is taken
     * as default.
     * If locale does not match exactly, the users language is compared as well.
     * If there are no matches at all, the default-locale as defined in the config
     * is returned.
     *
     * @param UserInterface $user
     * @param null|string $requestLocale
     *
     * @return string
     */
    public function retrieveLocale(UserInterface $user, $requestLocale = null)
    {
        // Use request locale if defined.
        if ($requestLocale && is_string($requestLocale)) {
            return $requestLocale;
        }

        $languageMatch = null;
        $userLanguage = strstr($user->getLocale(), '_', true);

        foreach ($this->configuration['locales'] as $locale) {
            // If locale matches users locale, the exact matching was found.
            if ($user->getLocale() == $locale) {
                return $locale;
            }

            // Check if users language (without locale) matches.
            if ($userLanguage == $locale) {
                $languageMatch = $locale;
            }
        }

        if ($languageMatch) {
            return $languageMatch;
        }

        return $this->configuration['fallback_locale'];
    }
}
