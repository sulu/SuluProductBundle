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

namespace Sulu\Product\Tests\Unit\Controller\Admin;

use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\UserInterface\Controller\Admin\MeasurementUnitController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeasurementUnitControllerTest extends TestCase
{
    private function createController(): MeasurementUnitController
    {
        return new MeasurementUnitController(new MeasurementRegistry());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        $data = \json_decode($content, true);
        $this->assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    public function testGetSecurityContextReturnsAttributesContext(): void
    {
        $this->assertSame('sulu.product.attributes', $this->createController()->getSecurityContext());
    }

    public function testGetLocaleUsesQueryParameter(): void
    {
        $request = new Request(['locale' => 'de']);

        $this->assertSame('de', $this->createController()->getLocale($request));
    }

    public function testGetLocaleUsesRequestLocaleAsFallback(): void
    {
        $request = new Request();
        $request->setLocale('fr');

        $this->assertSame('fr', $this->createController()->getLocale($request));
    }

    public function testCgetActionFiltersByFamilyAndReturnsSymbolsAsTitles(): void
    {
        $request = new Request(['measurementFamily' => 'length']);
        $response = $this->createController()->cgetAction($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = $this->decode((string) $response->getContent());
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        /** @var array<string, mixed> $embedded */
        $units = $embedded['measurement_units'];
        $this->assertIsArray($units);

        /** @var list<array{id: string, title: string}> $units */
        $byId = [];
        foreach ($units as $unit) {
            $byId[$unit['id']] = $unit['title'];
        }

        // The displayed title is the bare symbol, no localized name.
        $this->assertSame('mm', $byId['MILLIMETER']);
        $this->assertSame('m', $byId['METER']);
        $this->assertSame('km', $byId['KILOMETER']);
        $this->assertArrayNotHasKey('KILOGRAM', $byId);
    }

    public function testCgetActionWithoutFamilyReturnsAllUnits(): void
    {
        $request = new Request();
        $response = $this->createController()->cgetAction($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = $this->decode((string) $response->getContent());
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        /** @var array<string, mixed> $embedded */
        $units = $embedded['measurement_units'];
        $this->assertIsArray($units);

        /** @var list<array{id: string, title: string}> $units */
        $titles = \array_column($units, 'title', 'id');
        $this->assertSame('mm', $titles['MILLIMETER']);
        $this->assertSame('kg', $titles['KILOGRAM']);
    }

    public function testCgetActionSearchMatchesAgainstSymbol(): void
    {
        $request = new Request(['measurementFamily' => 'length', 'search' => 'mm']);
        $response = $this->createController()->cgetAction($request);

        $data = $this->decode((string) $response->getContent());
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        /** @var array<string, mixed> $embedded */
        $units = $embedded['measurement_units'];
        $this->assertIsArray($units);

        /** @var list<array{id: string, title: string}> $units */
        $titles = \array_column($units, 'title');

        // Only the millimeter symbol contains "mm"; cm/m/km are filtered out.
        $this->assertSame(['mm'], $titles);
    }

    public function testGetActionReturnsSymbolForKnownUnit(): void
    {
        $request = new Request();
        $response = $this->createController()->getAction($request, 'KILOGRAM');

        $this->assertSame(200, $response->getStatusCode());

        $data = $this->decode((string) $response->getContent());
        $this->assertSame('KILOGRAM', $data['id']);
        $this->assertSame('kg', $data['title']);
    }

    public function testGetActionThrowsNotFoundForUnknownUnit(): void
    {
        $request = new Request();

        $this->expectException(NotFoundHttpException::class);

        $this->createController()->getAction($request, 'NON_EXISTENT_UNIT');
    }
}
