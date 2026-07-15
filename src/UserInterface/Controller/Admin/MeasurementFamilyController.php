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

namespace Sulu\Product\UserInterface\Controller\Admin;

use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Security\SecuredControllerInterface;
use Sulu\Product\Domain\Measurement\MeasurementRegistry;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
final class MeasurementFamilyController implements SecuredControllerInterface
{
    public function __construct(
        private MeasurementRegistry $registry,
        private TranslatorInterface $translator,
    ) {
    }

    public function cgetAction(Request $request): Response
    {
        $locale = $this->getLocale($request);
        $search = \strtolower($request->query->getString('search', ''));

        $items = [];
        foreach ($this->registry->getFamilies() as $family) {
            $familyKey = $family->getKey();
            $title = $this->translator->trans('sulu_product.measurement_family_' . $familyKey, [], 'admin', $locale);
            if ('' !== $search && !\str_contains(\strtolower($title), $search)) {
                continue;
            }
            $items[] = ['id' => $familyKey, 'title' => $title];
        }

        $total = \count($items);
        $representation = new PaginatedRepresentation($items, 'measurement_families', 1, \max($total, 1), $total);

        return new JsonResponse($representation->toArray());
    }

    public function getAction(Request $request, string $id): Response
    {
        $locale = $this->getLocale($request);

        $enabledFamilyKeys = \array_map(
            static fn ($family): string => $family->getKey(),
            $this->registry->getFamilies(),
        );

        if (!\in_array($id, $enabledFamilyKeys, true)) {
            throw new NotFoundHttpException(\sprintf('Measurement family "%s" not found.', $id));
        }

        $title = $this->translator->trans('sulu_product.measurement_family_' . $id, [], 'admin', $locale);

        return new JsonResponse(['id' => $id, 'title' => $title]);
    }

    public function getSecurityContext(): string
    {
        return AttributeAdmin::SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): string
    {
        return $request->query->getString('locale', $request->getLocale());
    }
}
