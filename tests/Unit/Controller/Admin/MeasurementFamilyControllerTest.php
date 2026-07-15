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
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\UserInterface\Controller\Admin\MeasurementFamilyController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class MeasurementFamilyControllerTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TranslatorInterface> */
    private ObjectProphecy $translator;

    protected function setUp(): void
    {
        $this->translator = $this->prophesize(TranslatorInterface::class);
        // Return the translation key itself so titles are deterministic.
        $this->translator->trans(Argument::type('string'), [], 'admin', Argument::type('string'))
            ->willReturnArgument(0);
    }

    private function createController(): MeasurementFamilyController
    {
        return new MeasurementFamilyController(new MeasurementRegistry(), $this->translator->reveal());
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

    public function testCgetActionReturnsTranslatedFamilies(): void
    {
        $request = new Request(['locale' => 'en']);
        $response = $this->createController()->cgetAction($request);

        $this->assertSame(200, $response->getStatusCode());

        $data = $this->decode((string) $response->getContent());
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        /** @var array<string, mixed> $embedded */
        $families = $embedded['measurement_families'];
        $this->assertIsArray($families);

        /** @var list<array{id: string, title: string}> $families */
        $titles = \array_column($families, 'title', 'id');
        $this->assertSame('sulu_product.measurement_family_length', $titles['length']);
        $this->assertSame('sulu_product.measurement_family_weight', $titles['weight']);
    }

    public function testCgetActionSearchFiltersFamilies(): void
    {
        $request = new Request(['locale' => 'en', 'search' => 'area']);
        $response = $this->createController()->cgetAction($request);

        $data = $this->decode((string) $response->getContent());
        $embedded = $data['_embedded'];
        $this->assertIsArray($embedded);
        /** @var array<string, mixed> $embedded */
        $families = $embedded['measurement_families'];
        $this->assertIsArray($families);

        /** @var list<array{id: string, title: string}> $families */
        $ids = \array_column($families, 'id');
        $this->assertSame(['area'], $ids);
    }

    public function testGetActionReturnsTranslatedFamily(): void
    {
        $request = new Request(['locale' => 'en']);
        $response = $this->createController()->getAction($request, 'weight');

        $this->assertSame(200, $response->getStatusCode());

        $data = $this->decode((string) $response->getContent());
        $this->assertSame('weight', $data['id']);
        $this->assertSame('sulu_product.measurement_family_weight', $data['title']);
    }

    public function testGetActionThrowsNotFoundForUnknownFamily(): void
    {
        $request = new Request(['locale' => 'en']);

        $this->expectException(NotFoundHttpException::class);

        $this->createController()->getAction($request, 'non-existent-family');
    }
}
