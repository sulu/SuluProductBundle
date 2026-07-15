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

/**
 * @internal
 */
final class MeasurementUnitController implements SecuredControllerInterface
{
    public function __construct(private MeasurementRegistry $registry)
    {
    }

    public function cgetAction(Request $request): Response
    {
        $measurementFamily = $request->query->getString('measurementFamily', '');
        $search = \strtolower($request->query->getString('search', ''));

        $families = '' !== $measurementFamily
            ? [$measurementFamily]
            : \array_map(static fn ($family): string => $family->getKey(), $this->registry->getFamilies());

        $items = [];
        foreach ($families as $family) {
            foreach ($this->registry->getUnits($family) as $unit) {
                $symbol = $unit->getSymbol();
                if ('' !== $search && !\str_contains(\strtolower($symbol), $search)) {
                    continue;
                }
                $items[] = ['id' => $unit->getKey(), 'title' => $symbol];
            }
        }

        $total = \count($items);
        $representation = new PaginatedRepresentation($items, 'measurement_units', 1, \max($total, 1), $total);

        return new JsonResponse($representation->toArray());
    }

    public function getAction(Request $request, string $id): Response
    {
        $unit = $this->registry->findUnit($id);
        if (null !== $unit) {
            return new JsonResponse(['id' => $unit->getKey(), 'title' => $unit->getSymbol()]);
        }

        throw new NotFoundHttpException(\sprintf('Measurement unit "%s" not found.', $id));
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
