<?php

declare(strict_types=1);

namespace Sulu\Product\Domain\Model;

use Sulu\Component\Persistence\Model\AuditableInterface;
use Sulu\Content\Domain\Model\ContentRichEntityInterface;

/**
 * @extends ContentRichEntityInterface<ProductDimensionContentInterface>
 */
interface ProductInterface extends AuditableInterface, ContentRichEntityInterface
{
    public const TEMPLATE_TYPE = 'product';
    public const RESOURCE_KEY = 'products';
    public const FORM_KEY = 'product_details';
    public const LIST_KEY = 'products';

    /**
     * @internal
     */
    public function getId(): string;

    public function getUuid(): string;

    public function getCode(): ?string;

    public function setCode(?string $code): self;

    public function getCurrentLocale(): ?string;

    public function setCurrentLocale(string $locale): self;

    public function getTranslation(?string $locale = null): ?ProductTranslationInterface;

    public function addTranslation(ProductTranslationInterface $translation): self;

    public function removeTranslation(ProductTranslationInterface $translation): self;

}
