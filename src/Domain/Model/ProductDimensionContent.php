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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sulu\Content\Domain\Model\AuditableTrait;
use Sulu\Content\Domain\Model\AuthorTrait;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;
use Sulu\Content\Domain\Model\DimensionContentTrait;
use Sulu\Content\Domain\Model\ExcerptTrait;
use Sulu\Content\Domain\Model\RoutableTrait;
use Sulu\Content\Domain\Model\SeoTrait;
use Sulu\Content\Domain\Model\ShadowTrait;
use Sulu\Content\Domain\Model\TaxonomyTrait;
use Sulu\Content\Domain\Model\TemplateTrait;
use Sulu\Content\Domain\Model\WebspaceTrait;
use Sulu\Content\Domain\Model\WorkflowTrait;

class ProductDimensionContent implements ProductDimensionContentInterface
{
    use AuditableTrait;
    use AuthorTrait;
    use DimensionContentTrait;
    use ExcerptTrait;
    use TaxonomyTrait;
    use RoutableTrait;
    use SeoTrait;
    use ShadowTrait;
    use TemplateTrait {
        TemplateTrait::setTemplateData as parentSetTemplateData;
    }
    use WebspaceTrait;
    use WorkflowTrait;

    protected int $id;

    protected ProductInterface $product;

    protected ?string $title = null;

    private bool $customizeWebspaceSettings = false;

    /**
     * @var Collection<int, ProductDimensionContentAdditionalWebspace>
     */
    protected Collection $additionalWebspaces;

    protected ?string $code = null;

    protected ?string $externalIdentifier = null;

    protected ?ProductFamilyInterface $productFamily = null;

    /**
     * @var Collection<int, ProductAttributeValueInterface>
     */
    protected Collection $attributes;

    protected ?string $status = null;

    /**
     * @var array<string, mixed>
     */
    protected array $detailsData = [];

    public function __construct(ProductInterface $product)
    {
        $this->product = $product;
        $this->additionalWebspaces = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->created = new \DateTimeImmutable();
        $this->changed = new \DateTimeImmutable();
    }

    /**
     * @return ProductInterface
     */
    public function getResource(): ContentRichEntityInterface
    {
        return $this->product;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function setTemplateData(array $templateData): void
    {
        if (\array_key_exists('title', $templateData)
            && \is_string($templateData['title'])
        ) {
            $this->title = $templateData['title'];
        }

        $this->parentSetTemplateData($templateData);
    }

    public static function getTemplateType(): string
    {
        return ProductInterface::TEMPLATE_TYPE;
    }

    public static function isRouteMandatory(): bool
    {
        return false;
    }

    public static function getResourceKey(): string
    {
        return ProductDimensionContentInterface::RESOURCE_KEY;
    }

    public function getCustomizeWebspaceSettings(): bool
    {
        return $this->customizeWebspaceSettings;
    }

    public function setCustomizeWebspaceSettings(bool $customizeWebspaceSettings): static
    {
        $this->customizeWebspaceSettings = $customizeWebspaceSettings;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAdditionalWebspaces(): array
    {
        return \array_values(\array_map(
            fn ($webspace) => $webspace->getAdditionalWebspace(),
            $this->additionalWebspaces->toArray(),
        ));
    }

    /**
     * @param string[] $additionalWebspaces
     */
    public function setAdditionalWebspaces(array $additionalWebspaces): static
    {
        $existingAdditionalWebspaces = [];
        foreach ($this->additionalWebspaces as $existingAdditionalWebspace) {
            $existingAdditionalWebspaces[$existingAdditionalWebspace->getAdditionalWebspace()] = $existingAdditionalWebspace;
        }

        foreach ($additionalWebspaces as $additionalWebspace) {
            if (!\array_key_exists($additionalWebspace, $existingAdditionalWebspaces)) {
                $this->additionalWebspaces->add($this->createAdditionalWebspace($additionalWebspace));
            }
            unset($existingAdditionalWebspaces[$additionalWebspace]);
        }

        foreach ($existingAdditionalWebspaces as $additionalWebspace) {
            $this->additionalWebspaces->removeElement($additionalWebspace);
        }

        return $this;
    }

    public function addAdditionalWebspace(string $additionalWebspace): static
    {
        if (!$this->hasAdditionalWebspace($additionalWebspace)) {
            $this->additionalWebspaces->add($this->createAdditionalWebspace($additionalWebspace));
        }

        return $this;
    }

    public function hasAdditionalWebspace(string $additionalWebspace): bool
    {
        foreach ($this->additionalWebspaces as $webspace) {
            if ($webspace->getAdditionalWebspace() === $additionalWebspace) {
                return true;
            }
        }

        return false;
    }

    private function createAdditionalWebspace(string $additionalWebspace): ProductDimensionContentAdditionalWebspace
    {
        return new ProductDimensionContentAdditionalWebspace(
            $additionalWebspace,
            $this,
        );
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getExternalIdentifier(): ?string
    {
        return $this->externalIdentifier;
    }

    public function setExternalIdentifier(?string $externalIdentifier): static
    {
        $this->externalIdentifier = $externalIdentifier;

        return $this;
    }

    public function getProductFamily(): ?ProductFamilyInterface
    {
        return $this->productFamily;
    }

    public function setProductFamily(ProductFamilyInterface $productFamily): static
    {
        $this->productFamily = $productFamily;

        return $this;
    }

    /**
     * @return Collection<int, ProductAttributeValueInterface>
     */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttributeValueInterface $attribute): static
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
        }

        return $this;
    }

    public function removeAttribute(ProductAttributeValueInterface $attribute): static
    {
        $this->attributes->removeElement($attribute);

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getShortDescription(): ?string
    {
        $value = $this->detailsData['shortDescription'] ?? null;

        return \is_string($value) ? $value : null;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->setDetailsValue('shortDescription', $shortDescription);

        return $this;
    }

    public function getImage(): ?int
    {
        $value = $this->detailsData['image'] ?? null;

        return \is_int($value) ? $value : null;
    }

    public function setImage(?int $image): static
    {
        $this->setDetailsValue('image', $image);

        return $this;
    }

    /**
     * @return array<int, int>|null
     */
    public function getDocuments(): ?array
    {
        $value = $this->detailsData['documents'] ?? null;
        if (!\is_array($value)) {
            return null;
        }

        return \array_values(\array_filter($value, \is_int(...)));
    }

    /**
     * @param array<int, int>|null $documents
     */
    public function setDocuments(?array $documents): static
    {
        $this->setDetailsValue('documents', $documents);

        return $this;
    }

    private function setDetailsValue(string $key, mixed $value): void
    {
        if (null === $value) {
            unset($this->detailsData[$key]);

            return;
        }

        $this->detailsData[$key] = $value;
    }
}
