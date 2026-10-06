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

namespace Sulu\Product\Tests\Unit\Domain\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Event\ProductFamilyCreatedEvent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

#[CoversClass(ProductFamilyCreatedEvent::class)]
class ProductFamilyCreatedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $productFamily = $this->createProductFamily();
        $event = new ProductFamilyCreatedEvent($productFamily, 'de', ['name' => 'Name DE']);

        $this->assertSame($productFamily, $event->getProductFamily());
        $this->assertSame('created', $event->getEventType());
        $this->assertSame(['name' => 'Name DE'], $event->getEventPayload());
        $this->assertSame(ProductFamilyInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('productFamily-uuid', $event->getResourceId());
        $this->assertSame('de', $event->getResourceLocale());
        $this->assertSame('Name DE', $event->getResourceTitle());
        $this->assertSame('de', $event->getResourceTitleLocale());
        $this->assertSame(ProductFamilyAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutTranslationInLocale(): void
    {
        $event = new ProductFamilyCreatedEvent($this->createProductFamily(), 'fr', []);

        $this->assertNull($event->getResourceTitle());
    }

    private function createProductFamily(): ProductFamilyInterface
    {
        $productFamily = new ProductFamily('productFamily-uuid');
        $productFamily->setDefaultLocale('en');
        $productFamily->addTranslation(new ProductFamilyTranslation($productFamily, 'en', 'Name EN'));
        $productFamily->addTranslation(new ProductFamilyTranslation($productFamily, 'de', 'Name DE'));

        return $productFamily;
    }
}
