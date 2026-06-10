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

namespace Sulu\Product\Tests\Unit\Infrastructure\Sulu\Content\Select;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Infrastructure\Sulu\Content\Select\AttributeSelectService;

#[CoversClass(AttributeSelectService::class)]
class AttributeSelectServiceTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EntityManagerInterface> */
    private ObjectProphecy $entityManager;

    /** @var ObjectProphecy<EntityRepository<Attribute>> */
    private ObjectProphecy $entityRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        /** @var ObjectProphecy<EntityRepository<Attribute>> $entityRepository */
        $entityRepository = $this->prophesize(EntityRepository::class);
        $this->entityRepository = $entityRepository;
        $this->entityManager->getRepository(Attribute::class)
            ->willReturn($this->entityRepository->reveal());
    }

    public function testGetValuesReturnsUuidAndTranslatedName(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('some-uuid');
        $attribute->setKey('color');
        $translation = new AttributeTranslation($attribute, 'en', 'Color');
        $attribute->addTranslation($translation);

        $this->entityRepository->findAll()->willReturn([$attribute]);

        $service = new AttributeSelectService($this->entityManager->reveal());
        $values = $service->getValues('en');

        $this->assertCount(1, $values);
        $this->assertSame('some-uuid', $values[0]['name']);
        $this->assertSame('Color', $values[0]['title']);
    }

    public function testGetValuesFallsBackToUuidWhenNoTranslation(): void
    {
        $attribute = new Attribute(new AttributeGroup());
        $attribute->setUuid('fallback-uuid');
        $attribute->setKey('size');

        $this->entityRepository->findAll()->willReturn([$attribute]);

        $service = new AttributeSelectService($this->entityManager->reveal());
        $values = $service->getValues('en');

        $this->assertSame('fallback-uuid', $values[0]['name']);
        $this->assertSame('fallback-uuid', $values[0]['title']);
    }

    public function testGetValuesReturnsEmptyArrayWhenNoAttributes(): void
    {
        $this->entityRepository->findAll()->willReturn([]);

        $service = new AttributeSelectService($this->entityManager->reveal());
        $this->assertSame([], $service->getValues('en'));
    }
}
