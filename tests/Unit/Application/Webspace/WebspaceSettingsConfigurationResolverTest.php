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

namespace Sulu\Product\Tests\Unit\Application\Webspace;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Sulu\Product\Application\Webspace\WebspaceSettingsConfigurationResolver;

class WebspaceSettingsConfigurationResolverTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<WebspaceManagerInterface> */
    private ObjectProphecy $webspaceManager;

    protected function setUp(): void
    {
        $this->webspaceManager = $this->prophesize(WebspaceManagerInterface::class);
    }

    public function testResolveMainWebspaceWithLocaleSpecificConfig(): void
    {
        $defaultMainWebspace = ['en' => 'sulu-io-en', 'de' => 'sulu-io-de', 'default' => 'sulu-io-default'];
        $defaultAdditionalWebspaces = [];
        $resolver = new WebspaceSettingsConfigurationResolver($defaultMainWebspace, $defaultAdditionalWebspaces, $this->webspaceManager->reveal());
        $this->assertSame('sulu-io-en', $resolver->getDefaultMainWebspaceForLocale('en'));
        $this->assertSame('sulu-io-de', $resolver->getDefaultMainWebspaceForLocale('de'));
    }

    public function testResolveMainWebspaceWithDefaultConfig(): void
    {
        $defaultMainWebspace = ['en' => 'sulu-io-en', 'default' => 'sulu-io-default'];
        $resolver = new WebspaceSettingsConfigurationResolver($defaultMainWebspace, [], $this->webspaceManager->reveal());
        $this->assertSame('sulu-io-default', $resolver->getDefaultMainWebspaceForLocale('fr'));
    }

    public function testResolveMainWebspaceWithNoConfig(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver([], [], $this->webspaceManager->reveal());
        $webspace = new Webspace();
        $webspace->setName('sulu-io');
        $webspace->setKey('sulu-io');
        $webspaceCollection = new WebspaceCollection(['default' => $webspace]);
        $this->webspaceManager->getWebspaceCollection()->willReturn($webspaceCollection);
        $this->assertSame('sulu-io', $resolver->getDefaultMainWebspaceForLocale('en'));
    }

    public function testResolveMainWebspaceWithNoConfigMultipleWebspaces(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver([], [], $this->webspaceManager->reveal());
        $webspace = new Webspace();
        $webspace->setName('sulu-io');
        $webspace->setKey('sulu-io');
        $webspace2 = new Webspace();
        $webspace2->setName('blog');
        $webspace2->setKey('blog');
        $webspaceCollection = new WebspaceCollection(['default' => $webspace, 'additional' => $webspace2]);
        $this->webspaceManager->getWebspaceCollection()->willReturn($webspaceCollection);
        $this->expectException(\Exception::class);
        $resolver->getDefaultMainWebspaceForLocale('en');
    }

    public function testResolveMainWebspaceWithNullLocale(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver(['default' => 'sulu-io-default'], [], $this->webspaceManager->reveal());
        $this->assertSame('sulu-io-default', $resolver->getDefaultMainWebspaceForLocale('default'));
    }

    public function testResolveAdditionalWebspacesWithLocaleSpecificConfig(): void
    {
        $defaultAdditionalWebspaces = ['en' => ['sulu-io', 'example-com'], 'de' => ['sulu-io', 'beispiel-de'], 'default' => ['sulu-io']];
        $resolver = new WebspaceSettingsConfigurationResolver([], $defaultAdditionalWebspaces, $this->webspaceManager->reveal());
        $this->assertSame(['sulu-io', 'example-com'], $resolver->getDefaultAdditionalWebspacesForLocale('en'));
        $this->assertSame(['sulu-io', 'beispiel-de'], $resolver->getDefaultAdditionalWebspacesForLocale('de'));
    }

    public function testResolveAdditionalWebspacesWithDefaultConfig(): void
    {
        $defaultAdditionalWebspaces = ['en' => ['sulu-io', 'example-com'], 'default' => ['sulu-io']];
        $resolver = new WebspaceSettingsConfigurationResolver([], $defaultAdditionalWebspaces, $this->webspaceManager->reveal());
        $this->assertSame(['sulu-io'], $resolver->getDefaultAdditionalWebspacesForLocale('fr'));
    }

    public function testResolveAdditionalWebspacesWithNoConfig(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver([], [], $this->webspaceManager->reveal());
        $this->assertSame([], $resolver->getDefaultAdditionalWebspacesForLocale('en'));
    }

    public function testResolveAdditionalWebspacesWithNullLocale(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver([], ['default' => ['sulu-io', 'example-com']], $this->webspaceManager->reveal());
        $this->assertSame(['sulu-io', 'example-com'], $resolver->getDefaultAdditionalWebspacesForLocale('default'));
    }

    public function testResolveMainWebspaceWithStringConfig(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver(['default' => 'sulu-io'], [], $this->webspaceManager->reveal());
        $this->assertSame('sulu-io', $resolver->getDefaultMainWebspaceForLocale('en'));
        $this->assertSame('sulu-io', $resolver->getDefaultMainWebspaceForLocale('de'));
    }

    public function testResolveAdditionalWebspacesWithArrayConfig(): void
    {
        $resolver = new WebspaceSettingsConfigurationResolver([], ['default' => ['sulu-io', 'example-com']], $this->webspaceManager->reveal());
        $this->assertSame(['sulu-io', 'example-com'], $resolver->getDefaultAdditionalWebspacesForLocale('en'));
        $this->assertSame(['sulu-io', 'example-com'], $resolver->getDefaultAdditionalWebspacesForLocale('de'));
    }
}
