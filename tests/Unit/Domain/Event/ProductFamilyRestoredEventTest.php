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
use Sulu\Product\Domain\Event\ProductFamilyRestoredEvent;
use Sulu\Product\Domain\Model\ProductFamily;
use Sulu\Product\Domain\Model\ProductFamilyInterface;
use Sulu\Product\Domain\Model\ProductFamilyTranslation;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductFamilyAdmin;

#[CoversClass(ProductFamilyRestoredEvent::class)]
class ProductFamilyRestoredEventTest extends TestCase
{
    public function testGetters(): void
    {
        $productFamily = $this->createProductFamily();
        $event = new ProductFamilyRestoredEvent($productFamily, ['defaultLocale' => 'en']);

        $this->assertSame($productFamily, $event->getProductFamily());
        $this->assertSame('restored', $event->getEventType());
        $this->assertSame(['defaultLocale' => 'en'], $event->getEventPayload());
        $this->assertSame(ProductFamilyInterface::RESOURCE_KEY, $event->getResourceKey());
        $this->assertSame('productFamily-uuid', $event->getResourceId());
        $this->assertSame('Name EN', $event->getResourceTitle());
        $this->assertSame('en', $event->getResourceTitleLocale());
        $this->assertSame(ProductFamilyAdmin::SECURITY_CONTEXT, $event->getResourceSecurityContext());
    }

    public function testResourceTitleIsNullWithoutDefaultLocale(): void
    {
        $event = new ProductFamilyRestoredEvent(new ProductFamily(), []);

        $this->assertNull($event->getResourceTitle());
        $this->assertNull($event->getResourceTitleLocale());
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
