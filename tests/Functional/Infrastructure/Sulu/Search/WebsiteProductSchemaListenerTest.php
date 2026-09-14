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

namespace Sulu\Product\Tests\Functional\Infrastructure\Sulu\Search;

use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\Exception\DocumentNotFoundException;
use Sulu\Bundle\TestBundle\Testing\SuluTestCase;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Product\Application\Message\CreateAttributeMessage;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A rebuild is observable through a document that was removed from the index: only a full
 * reindex brings it back.
 */
class WebsiteProductSchemaListenerTest extends SuluTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = $this->createAuthenticatedClient(
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
        );
    }

    public function testANewNumberAttributeRebuildsTheWebsiteIndex(): void
    {
        $productId = $this->publishedProductWithoutDocument();

        $this->createAttribute('weight', AttributeInterface::TYPE_NUMBER);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $this->assertSame($productId, $engine->getDocument('website', 'products__' . $productId . '__en')['resourceId']);
    }

    public function testANewTextAttributeLeavesTheWebsiteIndexAlone(): void
    {
        $productId = $this->publishedProductWithoutDocument();

        $this->createAttribute('note', AttributeInterface::TYPE_TEXT);

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $this->expectException(DocumentNotFoundException::class);
        $engine->getDocument('website', 'products__' . $productId . '__en');
    }

    /**
     * @return string the product id
     */
    private function publishedProductWithoutDocument(): string
    {
        self::purgeDatabase();

        $this->client->request('POST', '/admin/api/product-families.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'name' => 'Schema Family',
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $familyId = $this->responseId();

        $this->client->request('POST', '/admin/api/products.json?locale=en', [], [], [], \json_encode([
            'locale' => 'en',
            'title' => 'Schema Product',
            'url' => '/schema-product',
            'productFamily' => $familyId,
            'type' => ProductInterface::TYPE_PRODUCT,
        ]) ?: null);
        $this->assertHttpStatusCode(201, $this->client->getResponse());
        $productId = $this->responseId();

        $this->client->request('POST', '/admin/api/products/' . $productId . '.json?locale=en&action=publish');
        $this->assertHttpStatusCode(200, $this->client->getResponse());

        /** @var EngineInterface $engine */
        $engine = self::getContainer()->get('cmsig_seal.engine.default');
        $documentId = 'products__' . $productId . '__en';
        $this->assertSame($productId, $engine->getDocument('website', $documentId)['resourceId']);
        $engine->deleteDocument('website', $documentId);

        return $productId;
    }

    private function createAttribute(string $key, string $type): void
    {
        $container = self::getContainer();
        /** @var AttributeGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(AttributeGroupRepositoryInterface::class);
        $group = $groupRepository->create();
        $groupRepository->save($group);
        self::getEntityManager()->flush();

        /** @var MessageBusInterface $messageBus */
        $messageBus = $container->get('sulu_message_bus');
        $messageBus->dispatch(new Envelope(new CreateAttributeMessage([
            'locale' => 'en',
            'key' => $key,
            'type' => $type,
            'name' => \ucfirst($key),
            'group' => (string) $group->getUuid(),
        ]), [new EnableFlushStamp()]));
    }

    private function responseId(): string
    {
        $data = \json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $id = $data['id'];
        $this->assertIsString($id);

        return $id;
    }
}
