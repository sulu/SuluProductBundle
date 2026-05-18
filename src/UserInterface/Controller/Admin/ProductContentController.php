<?php

declare(strict_types=1);

namespace Sulu\Product\UserInterface\Controller\Admin;

use Sulu\Product\Application\Message\ApplyWorkflowTransitionProductMessage;
use Sulu\Product\Application\Message\CopyLocaleProductMessage;
use Sulu\Product\Application\Message\ModifyProductMessage;
use Sulu\Product\Application\Message\RestoreProductVersionMessage;
use Sulu\Product\Domain\Exception\ProductNotFoundException;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Component\Rest\Exception\EntityNotFoundException;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilder;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptorInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Content\Application\ContentManager\ContentManagerInterface;
use Sulu\Content\Domain\Exception\ContentNotFoundException;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Infrastructure\Doctrine\DimensionContentQueryEnhancer;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
final class ProductContentController implements SecuredControllerInterface
{
    use HandleTrait;

    public function __construct(
        private ProductRepositoryInterface $productRepository,
        MessageBusInterface $messageBus,
        private NormalizerInterface $normalizer,
        private ContentManagerInterface $contentManager,
        private FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private DoctrineListBuilderFactoryInterface $listBuilderFactory,
        private RestHelperInterface $restHelper,
    ) {
        $this->messageBus = $messageBus;
    }

    public function getVersionsAction(Request $request, string $id): JsonResponse
    {
        $locale = $request->query->get('locale');

        /** @var DoctrineFieldDescriptorInterface[] $fieldDescriptors */
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors('products_versions');
        /** @var DoctrineListBuilder $listBuilder */
        $listBuilder = $this->listBuilderFactory->create(ProductInterface::class);
        $listBuilder->setParameter('locale', $locale);
        $listBuilder->setParameter('id', $id);
        $listBuilder->setIdField($fieldDescriptors['id']);
        $listBuilder->sort($fieldDescriptors['version'], 'DESC');
        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        $result = $listBuilder->execute();
        $listRepresentation = new PaginatedRepresentation(
            $result,
            'products_versions',
            $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            $listBuilder->count(),
        );

        return new JsonResponse(
            $this->normalizer->normalize($listRepresentation->toArray(), 'json'),
        );
    }

    public function getAction(Request $request, string $id): Response
    {
        $dimensionAttributes = [
            'locale' => $request->query->getString('locale', $request->getLocale()),
            'stage' => DimensionContentInterface::STAGE_DRAFT,
        ];

        try {
            $product = $this->productRepository->getOneBy(
                ['uuid' => $id],
                [
                    ProductRepositoryInterface::SELECT_PRODUCT_CONTENT => [
                        'dimensionAttributes' => $dimensionAttributes,
                        'selects' => [DimensionContentQueryEnhancer::GROUP_SELECT_CONTENT_ADMIN => true],
                    ],
                ],
            );
        } catch (ProductNotFoundException $e) {
            $exception = new EntityNotFoundException($e->getModel(), $id, $e);
            return new JsonResponse($exception->toArray(), 404);
        }

        try {
            $dimensionContent = $this->contentManager->resolve($product, $dimensionAttributes);
        } catch (ContentNotFoundException) {
            return new JsonResponse(['template' => ProductInterface::TEMPLATE_TYPE]);
        }

        $normalizedContent = $this->contentManager->normalize($dimensionContent);

        return new JsonResponse($this->normalizer->normalize(
            $normalizedContent,
            'json',
            ['sulu_admin' => true, 'sulu_admin_product' => true, 'sulu_admin_product_content' => true],
        ));
    }

    public function putAction(Request $request, string $id): Response
    {
        $message = new ModifyProductMessage(['uuid' => $id], $this->getData($request));
        $this->handle(new Envelope($message, [new EnableFlushStamp()]));
        $this->handleAction($request, $id);

        return $this->getAction($request, $id);
    }

    public function postTriggerAction(Request $request, string $id): Response
    {
        $this->handleAction($request, $id);
        return $this->getAction($request, $id);
    }

    public function getSecurityContext(): string
    {
        return ProductAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(Request $request): array
    {
        return \array_replace(
            $request->request->all(),
            ['locale' => $this->getLocale($request)],
        );
    }

    private function handleAction(Request $request, string $uuid): void
    {
        $action = $request->query->get('action');

        if (!$action || 'draft' === $action) {
            return;
        }

        if ('copy_locale' === $action) {
            $message = new CopyLocaleProductMessage(
                ['uuid' => $uuid],
                (string) $request->query->get('src'),
                (string) $request->query->get('dest'),
            );
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
            return;
        }

        if ('restore' === $action) {
            $version = (int) $request->query->get('version');
            if (!$version) {
                throw new \InvalidArgumentException('The "version" query parameter is required for restoring a version.');
            }
            $message = new RestoreProductVersionMessage(
                ['uuid' => $uuid],
                $version,
                $this->getLocale($request),
                $request->query->all(),
            );
            $this->handle(new Envelope($message, [new EnableFlushStamp()]));
            return;
        }

        $message = new ApplyWorkflowTransitionProductMessage(
            ['uuid' => $uuid],
            $this->getLocale($request),
            $action,
        );
        $this->handle(new Envelope($message, [new EnableFlushStamp()]));
    }
}
