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

namespace Sulu\Product\Tests\Functional\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use Sulu\Bundle\ActivityBundle\Domain\Model\ActivityInterface;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Content\Domain\Repository\WorkflowTransitionRequestRepositoryInterface;
use Sulu\Content\Domain\Value\WorkflowTransitionRequest\WorkflowTransitionRequestPlaceEnum;
use Sulu\Product\Domain\Model\ProductInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * A publish that skips the review of an open request is logged as such, and the request says so too.
 */
#[CoversNothing]
class ProductPublishWithoutReviewActivityTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient([], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json']);

        self::purgeDatabase();
    }

    public function testPublishWhileTheRequestIsPendingStoresAnActivityThatMarksTheBypass(): void
    {
        $id = $this->createProduct();
        $this->transition($id, 'request_for_review');

        $this->transition($id, 'publish');

        $activities = $this->findActivities('workflow_transition.publish_without_review');
        $this->assertCount(1, $activities);
        $this->assertSame(ProductInterface::RESOURCE_KEY, $activities[0]->getResourceKey());
        $this->assertSame($id, $activities[0]->getResourceId());
        $this->assertSame('en', $activities[0]->getResourceLocale());
        $this->assertSame('Bypassed', $activities[0]->getResourceTitle());
        $this->assertSame(self::getTestUser()->getId(), $activities[0]->getUser()?->getId());
        $this->assertSame([], $this->findActivities('workflow_transition.publish'), 'The bypass replaces the plain publish activity.');

        $request = self::getContainer()->get(WorkflowTransitionRequestRepositoryInterface::class)->getOneBy([
            'resourceKey' => ProductInterface::RESOURCE_KEY,
            'resourceId' => $id,
        ]);
        $this->assertSame(WorkflowTransitionRequestPlaceEnum::BYPASSED, $request->getPlace());
    }

    public function testPublishWithoutARequestStoresThePlainPublishActivity(): void
    {
        $id = $this->createProduct();

        $this->transition($id, 'publish');

        $this->assertCount(1, $this->findActivities('workflow_transition.publish'));
        $this->assertSame([], $this->findActivities('workflow_transition.publish_without_review'));
    }

    /**
     * The activity list renders `sulu_activity.description.<resourceKey>.<type>`, and a missing key
     * shows the raw key instead of failing.
     */
    public function testTheActivitySaysThatTheProductWasPublishedWithoutReview(): void
    {
        $id = $this->createProduct();
        $this->transition($id, 'request_for_review');
        $this->transition($id, 'publish');

        $activity = $this->findActivities('workflow_transition.publish_without_review')[0];

        foreach ([
            'en' => 'has published the product "Bypassed" without review',
            'de' => 'hat das Produkt "Bypassed" ohne Review veröffentlicht',
        ] as $locale => $expected) {
            $this->assertSame(
                '{user} ' . $expected,
                self::getContainer()->get('translator')->trans(
                    \sprintf('sulu_activity.description.%s.%s', $activity->getResourceKey(), $activity->getType()),
                    ['{userFullName}' => '{user}', '{resourceTitle}' => 'Bypassed'],
                    'admin',
                    $locale,
                ),
            );
        }
    }

    private function createProduct(): string
    {
        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], (string) \json_encode([
            'locale' => 'en', 'name' => 'Family', 'key' => 'family', 'description' => null,
        ]));
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        /** @var array{id: string} $family */
        $family = \json_decode((string) $this->client->getResponse()->getContent(), true);

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], (string) \json_encode([
            'locale' => 'en',
            'title' => 'Bypassed',
            'url' => '/bypassed',
            'productFamily' => $family['id'],
            'type' => ProductInterface::TYPE_PRODUCT,
        ]));
        $this->assertHttpStatusCode(201, $this->client->getResponse());

        /** @var array{id: string} $product */
        $product = \json_decode((string) $this->client->getResponse()->getContent(), true);

        return $product['id'];
    }

    private function transition(string $id, string $action): void
    {
        $this->client->request('POST', \sprintf('/admin/api/products/%s.json?locale=en&action=%s', $id, $action));
        $this->assertHttpStatusCode(200, $this->client->getResponse());
    }

    /**
     * @return list<ActivityInterface>
     */
    private function findActivities(string $type): array
    {
        self::getEntityManager()->clear();

        /** @var list<ActivityInterface> $activities */
        $activities = self::getEntityManager()->getRepository(ActivityInterface::class)->findBy(['type' => $type]);

        return $activities;
    }
}
