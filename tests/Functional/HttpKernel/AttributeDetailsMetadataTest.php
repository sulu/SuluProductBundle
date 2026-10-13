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

namespace Sulu\Product\Tests\Functional\HttpKernel;

use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeDisplayFormatFormMetadataVisitor;

#[CoversClass(AttributeDisplayFormatFormMetadataVisitor::class)]
class AttributeDetailsMetadataTest extends SuluTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        \restore_exception_handler();
    }

    public function testTheDisplayFormatShowsForTheTypesThatHaveOne(): void
    {
        $client = $this->createAuthenticatedClient([], ['HTTP_ACCEPT' => 'application/json']);

        $client->request('GET', '/admin/metadata/form/attribute_details');
        $this->assertHttpStatusCode(200, $client->getResponse());

        /** @var array{form: array<string, array{visibleCondition?: string|null}>} $metadata */
        $metadata = \json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame(
            'type == "number" || type == "text" || type == "date" || type == "range"',
            $metadata['form']['config/displayFormat']['visibleCondition'] ?? null,
        );
    }
}
