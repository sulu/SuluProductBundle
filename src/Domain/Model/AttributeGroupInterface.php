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

use Sulu\Component\Persistence\Model\AuditableInterface;

interface AttributeGroupInterface extends AuditableInterface
{
    public const RESOURCE_KEY = 'attribute_groups';
    public const FORM_KEY = 'attribute_group_details';
    public const LIST_KEY = 'attribute_groups';

    public function getId(): int;

    public function getUuid(): ?string;

    public function setUuid(string $uuid): self;

    public function getExternalIdentifier(): ?string;

    public function setExternalIdentifier(?string $externalIdentifier): self;

    public function getTranslation(string $locale): ?AttributeGroupTranslationInterface;

    public function addTranslation(AttributeGroupTranslationInterface $translation): self;

    public function removeTranslation(AttributeGroupTranslationInterface $translation): self;

    /** @return AttributeGroupAttributeInterface[] */
    public function getGroupAttributes(): array;

    public function addGroupAttribute(AttributeGroupAttributeInterface $groupAttribute): self;

    public function removeGroupAttribute(AttributeGroupAttributeInterface $groupAttribute): self;
}
