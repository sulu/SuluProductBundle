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

namespace Sulu\Product\Tests\Unit\Application\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Application\Mcp\ProjectLocales;
use Sulu\Product\Domain\Exception\UnknownLocaleException;
use Sulu\Product\Tests\Unit\Fixture\ProjectLocalesFactory;

#[CoversClass(ProjectLocales::class)]
#[CoversClass(UnknownLocaleException::class)]
final class ProjectLocalesTest extends TestCase
{
    public function testAKnownLocalePasses(): void
    {
        ProjectLocalesFactory::create(['en', 'de'])->assertExists('de');

        $this->expectNotToPerformAssertions();
    }

    public function testAnUnknownLocaleNamesTheValidOnes(): void
    {
        try {
            ProjectLocalesFactory::create(['en', 'de'])->assertExists('xx');
            $this->fail('Expected an UnknownLocaleException.');
        } catch (UnknownLocaleException $e) {
            $this->assertSame('The locale "xx" is not configured for this installation.', $e->getMessage());
            $this->assertSame('Use one of these locales: "en", "de".', $e->getHint());
        }
    }
}
