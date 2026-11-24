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

namespace Sulu\Product\Infrastructure\Sulu\Admin;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataVisitorInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TagMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\TypedFormMetadataVisitorInterface;
use Sulu\Content\Application\ContentDataMapper\DataMapper\TemplateDataMapper;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductInterface;

/**
 * Applies the `sulu_product.route` configuration to the route field of the product forms, and adds
 * the same field invisibly to the product templates, where RoutableDataMapper reads it.
 *
 * @internal
 */
class ProductRouteFormMetadataVisitor implements FormMetadataVisitorInterface, TypedFormMetadataVisitorInterface
{
    private const FORM_KEYS = [ProductInterface::FORM_KEY, ProductInterface::FORM_KEY_VARIANT];

    private const FIELD_NAME = 'url';

    private const RESOURCE_LOCATOR_TAG = 'sulu.rlp';

    private const SEARCH_FIELD_ROLE = 'url';

    /**
     * @param array<string, scalar|null> $params
     */
    public function __construct(
        private readonly string $type,
        private readonly array $params,
    ) {
    }

    public function visitFormMetadata(FormMetadata $formMetadata, string $locale, array $metadataOptions = []): void
    {
        if (!\in_array($formMetadata->getKey(), self::FORM_KEYS, true)) {
            return;
        }

        $routeField = $formMetadata->getItems()[self::FIELD_NAME] ?? null;
        if (!$routeField instanceof FieldMetadata) {
            return;
        }

        $this->applyRouteConfig($routeField);

        if (ProductInterface::FORM_KEY_VARIANT !== $formMetadata->getKey()) {
            return;
        }

        $routeField->setRequired(true);
    }

    public function visitTypedFormMetadata(TypedFormMetadata $formMetadata, string $key, string $locale, array $metadataOptions = []): void
    {
        if (ProductDimensionContent::getTemplateType() !== $key) {
            return;
        }

        foreach ($formMetadata->getForms() as $form) {
            $routeField = $form->getItems()[self::FIELD_NAME] ?? null;

            if (!$routeField instanceof FieldMetadata) {
                $routeField = new FieldMetadata(self::FIELD_NAME);
                $routeField->setVisibleCondition('false');
                $this->addTag($routeField, self::RESOURCE_LOCATOR_TAG);
                $this->addTag($routeField, TagMetadata::SEARCH_FIELD_TAG, ['role' => self::SEARCH_FIELD_ROLE]);
                $form->addItem($routeField);
            }

            $this->applyRouteConfig($routeField);
            $this->addTag($routeField, TemplateDataMapper::SKIP_TAG);
        }
    }

    private function applyRouteConfig(FieldMetadata $routeField): void
    {
        $routeField->setType($this->type);

        foreach ($this->params as $name => $value) {
            $option = new OptionMetadata();
            $option->setName($name);
            $option->setValue(\is_float($value) ? (string) $value : $value);

            $routeField->addOption($option);
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    private function addTag(FieldMetadata $routeField, string $name, array $attributes = []): void
    {
        foreach ($routeField->getTags() as $tag) {
            if ($tag->getName() === $name) {
                return;
            }
        }

        $tag = new TagMetadata();
        $tag->setName($name);
        $tag->setAttributes($attributes);
        $routeField->addTag($tag);
    }
}
