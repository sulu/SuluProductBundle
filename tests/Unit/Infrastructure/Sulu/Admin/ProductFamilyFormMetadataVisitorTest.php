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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\OptionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyFormMetadataVisitor;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(ProductFamilyFormMetadataVisitor::class)]
class ProductFamilyFormMetadataVisitorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<AttributeGroupRepositoryInterface> */
    private ObjectProphecy $attributeGroupRepository;

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    protected function setUp(): void
    {
        $this->attributeGroupRepository = $this->prophesize(AttributeGroupRepositoryInterface::class);
        $this->translator = $this->prophesize(TranslatorInterface::class);
        // echo back the injected attribute name so label assertions stay focused on the visitor
        $this->translator->trans(Argument::cetera())->will(
            static function(array $args): string {
                /** @var array<string, string> $parameters */
                $parameters = $args[1] ?? [];
                /** @var string $id */
                $id = $args[0];

                return $parameters['%attributeName%'] ?? $id;
            },
        );
    }

    private function visitor(): ProductFamilyFormMetadataVisitor
    {
        return new ProductFamilyFormMetadataVisitor(
            $this->attributeGroupRepository->reveal(),
            $this->translator->reveal(),
        );
    }

    private function attributeWithId(int $id, string $key, string $name): Attribute
    {
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, $id);
        $attribute->setKey($key);
        $attribute->addTranslation(new AttributeTranslation($attribute, 'en', $name));

        return $attribute;
    }

    public function testIgnoresOtherFormKeys(): void
    {
        $this->attributeGroupRepository->findAll()->shouldNotBeCalled();

        $formMetadata = new FormMetadata();
        $formMetadata->setKey('something_else');

        $this->visitor()->visitFormMetadata($formMetadata, 'en');

        $this->assertSame([], $formMetadata->getItems());
    }

    public function testAddsSectionPerGroupWithToggleFields(): void
    {
        $group = new AttributeGroup();
        (new \ReflectionProperty(AttributeGroup::class, 'id'))->setValue($group, 3);
        $group->addTranslation(new AttributeGroupTranslation($group, 'en', 'Dimensions'));
        $attribute = $this->attributeWithId(7, 'width', 'Width');
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attribute));

        $this->attributeGroupRepository->findAll()->willReturn([$group]);

        $formMetadata = new FormMetadata();
        $formMetadata->setKey(ProductFamilyInterface::FORM_KEY);

        $this->visitor()->visitFormMetadata($formMetadata, 'en');

        $items = $formMetadata->getItems();
        $this->assertArrayHasKey('attribute_group_3', $items);
        $section = $items['attribute_group_3'];
        $this->assertInstanceOf(SectionMetadata::class, $section);
        $this->assertSame('Dimensions', $section->getLabel('en'));

        $sectionItems = $section->getItems();
        $this->assertArrayHasKey('attributes/width/enabled', $sectionItems);
        $this->assertArrayHasKey('attributes/width/required', $sectionItems);

        $enabled = $sectionItems['attributes/width/enabled'];
        $this->assertInstanceOf(FieldMetadata::class, $enabled);
        $this->assertSame('checkbox', $enabled->getType());
        $this->assertSame('Width', $enabled->getLabel('en'));
        $this->assertTogglerOption($enabled);

        $required = $sectionItems['attributes/width/required'];
        $this->assertInstanceOf(FieldMetadata::class, $required);
        $this->assertSame('checkbox', $required->getType());
        $this->assertSame('!attributes["width"].enabled', $required->getDisabledCondition());
        $this->assertTogglerOption($required);

        $this->assertFalse($formMetadata->isCacheable());
    }

    public function testFallsBackToAttributeKeyWhenTranslationMissing(): void
    {
        $group = new AttributeGroup();
        (new \ReflectionProperty(AttributeGroup::class, 'id'))->setValue($group, 1);
        $attribute = new Attribute(new AttributeGroup());
        (new \ReflectionProperty(Attribute::class, 'id'))->setValue($attribute, 4);
        $attribute->setKey('depth');
        $group->addGroupAttribute(new AttributeGroupAttribute($group, $attribute));

        $this->attributeGroupRepository->findAll()->willReturn([$group]);

        $formMetadata = new FormMetadata();
        $formMetadata->setKey(ProductFamilyInterface::FORM_KEY);

        $this->visitor()->visitFormMetadata($formMetadata, 'en');

        $section = $formMetadata->getItems()['attribute_group_1'];
        $this->assertInstanceOf(SectionMetadata::class, $section);
        $this->assertSame('', $section->getLabel('en'));
        $this->assertSame('depth', $section->getItems()['attributes/depth/enabled']->getLabel('en'));
    }

    private function assertTogglerOption(FieldMetadata $field): void
    {
        $option = $field->findOption('type');
        $this->assertInstanceOf(OptionMetadata::class, $option);
        $this->assertSame('toggler', $option->getValue());
    }
}
