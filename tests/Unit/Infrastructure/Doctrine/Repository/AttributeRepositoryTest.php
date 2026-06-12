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

namespace Sulu\Product\Tests\Unit\Infrastructure\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeRepository;

#[CoversClass(AttributeRepository::class)]
class AttributeRepositoryTest extends TestCase
{
    public function testCreateQueryBuilderAppliesOnlySupportedFilters(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Attribute::class)
            ->willReturn($entityRepository);

        $entityRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('attribute')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnCallback(function(string $where) use ($queryBuilder): QueryBuilder {
                $this->assertSame('attribute.key = :key', $where);

                return $queryBuilder;
            });

        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->willReturnCallback(function(string $name, string $value) use ($queryBuilder): QueryBuilder {
                $this->assertSame('key', $name);
                $this->assertSame('length', $value);

                return $queryBuilder;
            });

        $repository = new AttributeRepository($entityManager);

        $this->assertSame($queryBuilder, $repository->createQueryBuilder([
            'key' => 'length',
            'type' => 'text',
        ]));
    }
}
