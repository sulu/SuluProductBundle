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

namespace Sulu\Product\Tests\Unit\Infrastructure\Symfony\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Infrastructure\Symfony\Twig\ProductAttributeTwigExtension;

#[CoversClass(ProductAttributeTwigExtension::class)]
class ProductAttributeTwigExtensionTest extends TestCase
{
    private ProductAttributeTwigExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ProductAttributeTwigExtension();
    }

    /**
     * @param array{key: string, label: string} $group
     *
     * @return array{key: string, label: string, type: string, value: mixed, formattedValue: string, position: int, group: array{key: string, label: string}}
     */
    private function attribute(string $key, int $position, array $group): array
    {
        return [
            'key' => $key,
            'label' => \ucfirst($key),
            'type' => 'text',
            'value' => 'v',
            'formattedValue' => 'v',
            'position' => $position,
            'group' => $group,
        ];
    }

    public function testGroupsAttributesByTheirGroup(): void
    {
        $technical = ['key' => '1', 'label' => 'Technische Daten'];
        $mechanical = ['key' => '2', 'label' => 'Mechanische Daten'];

        $groups = $this->extension->groupAttributes([
            'impedance' => $this->attribute('impedance', 1, $technical),
            'weight' => $this->attribute('weight', 1, $mechanical),
        ]);

        self::assertSame(['1', '2'], \array_column($groups, 'key'));
        self::assertSame('Technische Daten', $groups[0]['label']);
        self::assertSame(['impedance'], \array_keys($groups[0]['attributes']));
        self::assertSame(['weight'], \array_keys($groups[1]['attributes']));
    }

    public function testGroupsSortNumericallyByKey(): void
    {
        $groups = $this->extension->groupAttributes([
            'a' => $this->attribute('a', 1, ['key' => '10', 'label' => 'Ten']),
            'b' => $this->attribute('b', 1, ['key' => '9', 'label' => 'Nine']),
        ]);

        self::assertSame(['9', '10'], \array_column($groups, 'key'));
    }

    public function testAttributesSortByPositionWithinAGroup(): void
    {
        $group = ['key' => '1', 'label' => 'Technische Daten'];

        $groups = $this->extension->groupAttributes([
            'housing' => $this->attribute('housing', 2, $group),
            'weight' => $this->attribute('weight', 1, $group),
        ]);

        self::assertSame(['weight', 'housing'], \array_keys($groups[0]['attributes']));
    }

    public function testEmptyInputYieldsNoGroups(): void
    {
        self::assertSame([], $this->extension->groupAttributes([]));
    }

    public function testFilterIsRegistered(): void
    {
        $names = \array_map(
            static fn (\Twig\TwigFilter $filter): string => $filter->getName(),
            $this->extension->getFilters(),
        );

        self::assertContains('sulu_product_attribute_groups', $names);
    }
}
