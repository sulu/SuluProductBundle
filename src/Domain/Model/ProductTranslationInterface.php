<?php

declare(strict_types=1);

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Domain\Model;

interface ProductTranslationInterface
{
    public function getId(): int;

    public function getLocale(): string;

    public function setLocale(string $locale): self;

    public function getName(): string;

    public function setName(string $name): self;

    public function getProduct(): ProductInterface;
}
