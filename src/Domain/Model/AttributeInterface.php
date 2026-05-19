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

interface AttributeInterface
{
    public const TYPE_NUMBER = 'number';
    public const TYPE_TEXT = 'text';
    public const TYPE_JSON = 'json';
    public const TYPE_OPTIONS = 'options';

    public const RESOURCE_KEY = 'attributes';
    public const FORM_KEY = 'attribute_details';
    public const LIST_KEY = 'attributes';

    public function getId(): int;

    public function getUuid(): ?string;

    public function setUuid(string $uuid): self;

    public function getKey(): string;

    public function setKey(string $key): self;

    public function getType(): string;

    public function setType(string $type): self;

    public function setCurrentLocale(string $locale): self;

    public function getTranslation(?string $locale = null): ?AttributeTranslationInterface;

    public function addTranslation(AttributeTranslationInterface $translation): self;

    public function removeTranslation(AttributeTranslationInterface $translation): self;

    /** @return AttributeOptionInterface[] */
    public function getOptions(): array;

    public function getOption(string $key): ?AttributeOptionInterface;

    public function addOption(AttributeOptionInterface $option): self;

    public function removeOption(AttributeOptionInterface $option): self;
}
