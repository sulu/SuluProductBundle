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

namespace Sulu\Product\Tests\Unit\Support;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Product\Application\Attribute\AttributeValueConverter;

/**
 * The form fragments `product_attribute_<type>` as the bundle ships them: one field named `value`,
 * unless a test gives the fields of a type itself.
 *
 * @internal
 */
final class ProductAttributeFragmentLoader implements FormMetadataLoaderInterface
{
    private const FIELD_TYPES = [
        'number' => 'number',
        'text' => 'text_line',
        'date' => 'date',
        'options' => 'single_select',
        'boolean' => 'single_select',
    ];

    /**
     * @param array<string, array<string, string>> $fields the field types by field name, by attribute type
     */
    public function __construct(
        private readonly array $fields = [],
    ) {
    }

    /**
     * @param array<string, array<string, string>> $fields the field types by field name, by attribute type
     */
    public static function converter(array $fields = []): AttributeValueConverter
    {
        return new AttributeValueConverter(new self($fields));
    }

    public function getMetadata(string $key, string $locale, array $metadataOptions): ?MetadataInterface
    {
        $type = \substr($key, \strlen('product_attribute_'));

        $fields = $this->fields[$type] ?? (isset(self::FIELD_TYPES[$type]) ? ['value' => self::FIELD_TYPES[$type]] : null);
        if (null === $fields) {
            return null;
        }

        $form = new FormMetadata();
        $form->setKey($key);
        foreach ($fields as $name => $fieldType) {
            $field = new FieldMetadata($name);
            $field->setType($fieldType);
            $form->addItem($field);
        }

        return $form;
    }
}
