<?php

/*
 * This file is part of Sulu.
 *
 * (c) Sulu GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Product\Domain\Model;

use Doctrine\Common\Collections\Collection;
use Sulu\Content\Domain\Model\AuditableInterface;
use Sulu\Content\Domain\Model\AuthorInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\ExcerptInterface;
use Sulu\Content\Domain\Model\RoutableInterface;
use Sulu\Content\Domain\Model\SeoInterface;
use Sulu\Content\Domain\Model\ShadowInterface;
use Sulu\Content\Domain\Model\TaxonomyInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Content\Domain\Model\WebspaceInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;

/**
 * @extends DimensionContentInterface<ProductInterface>
 */
interface ProductDimensionContentInterface extends DimensionContentInterface, ExcerptInterface, TaxonomyInterface, SeoInterface, TemplateInterface, RoutableInterface, WorkflowInterface, ShadowInterface, WebspaceInterface, AuthorInterface, AuditableInterface
{
    public const DEFAULT_STATUS = 'available';

    public function getTitle(): ?string;

    public function setTitle(?string $title): static;

    public function getCustomizeWebspaceSettings(): bool;

    public function setCustomizeWebspaceSettings(bool $customizeWebspaceSettings): static;

    /**
     * @return string[]
     */
    public function getAdditionalWebspaces(): array;

    /**
     * @param string[] $additionalWebspaces
     */
    public function setAdditionalWebspaces(array $additionalWebspaces): static;

    public function addAdditionalWebspace(string $additionalWebspace): static;

    public function hasAdditionalWebspace(string $additionalWebspace): bool;

    public function getCode(): ?string;

    public function setCode(?string $code): static;

    public function getExternalIdentifier(): ?string;

    public function setExternalIdentifier(?string $externalIdentifier): static;

    public function getProductFamily(): ?ProductFamilyInterface;

    public function setProductFamily(ProductFamilyInterface $productFamily): static;

    /**
     * @return Collection<int, ProductAttributeValueInterface>
     */
    public function getAttributes(): Collection;

    public function addAttribute(ProductAttributeValueInterface $attribute): static;

    public function removeAttribute(ProductAttributeValueInterface $attribute): static;

    /**
     * @return ProductAssociationInterface[]
     */
    public function getAssociations(): array;

    public function addAssociation(ProductAssociationInterface $association): void;

    public function removeAssociation(ProductAssociationInterface $association): void;

    /**
     * @return ProductAssociationInterface[]
     */
    public function getAssociationsByType(string $type): array;

    public function getStatus(): string;

    public function setStatus(string $status): static;

    /**
     * @return array<string, mixed>
     */
    public function getDetailsData(): array;

    /**
     * @param array<string, mixed> $detailsData
     */
    public function setDetailsData(array $detailsData): static;
}
