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

namespace Sulu\Product\Tests\Unit\Fixture;

use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\AdminBundle\Metadata\MetadataProviderInterface;

/**
 * Serves metadata from a map keyed the way the provider is queried. Records the
 * locale of every call so tests can assert what a subject asked for.
 *
 * @internal
 */
final class ArrayMetadataProvider implements MetadataProviderInterface
{
    /** @var list<array{key: string, locale: string, options: array<string, mixed>}> */
    private array $calls = [];

    private ?MetadataInterface $default = null;

    /**
     * @param array<string, MetadataInterface> $metadata
     */
    public function __construct(
        private array $metadata = [],
    ) {
    }

    public function set(string $key, MetadataInterface $metadata): self
    {
        $this->metadata[$key] = $metadata;

        return $this;
    }

    /**
     * Answers every key the map does not cover, for subjects that only care that
     * metadata came back at all.
     */
    public function setDefault(MetadataInterface $metadata): self
    {
        $this->default = $metadata;

        return $this;
    }

    public function getMetadata(string $key, string $locale, array $metadataOptions): MetadataInterface
    {
        $this->calls[] = ['key' => $key, 'locale' => $locale, 'options' => $metadataOptions];

        return $this->metadata[$key]
            ?? $this->default
            ?? throw new \RuntimeException(\sprintf('No metadata registered for "%s".', $key));
    }

    /**
     * @return list<array{key: string, locale: string, options: array<string, mixed>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<string>
     */
    public function requestedKeys(): array
    {
        return \array_column($this->calls, 'key');
    }
}
