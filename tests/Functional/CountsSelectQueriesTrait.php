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

namespace Sulu\Product\Tests\Functional;

use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;

/**
 * Counts the SELECT statements the application ran, on every database platform.
 *
 * Reads the debug data holder of DoctrineBundle instead of a server status variable,
 * which exists on MySQL only.
 */
trait CountsSelectQueriesTrait
{
    private function countSelectQueries(): int
    {
        $container = self::getContainer();
        if (!$container->has('doctrine.debug_data_holder')) {
            self::markTestSkipped('Counting queries needs the debug data holder of DoctrineBundle 2.7 or later.');
        }

        /** @var DebugDataHolder $debugDataHolder */
        $debugDataHolder = $container->get('doctrine.debug_data_holder');

        /** @var list<array{sql: string}> $queries */
        $queries = $debugDataHolder->getData()['default'] ?? [];

        return \count(\array_filter(
            $queries,
            static fn (array $query): bool => \str_starts_with(\ltrim($query['sql']), 'SELECT'),
        ));
    }
}
