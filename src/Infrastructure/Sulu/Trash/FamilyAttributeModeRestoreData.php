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

namespace Sulu\Product\Infrastructure\Sulu\Trash;

use Sulu\Product\Domain\Model\ProductFamilyAttributeInterface;

/**
 * Reads the mode of a family attribute from trash restore data; items stored with the old
 * "variantSpecific" boolean map to variantSpecific/shared.
 *
 * @internal
 */
final class FamilyAttributeModeRestoreData
{
    /**
     * @param array<string, mixed> $data
     */
    public static function mode(array $data): string
    {
        $mode = $data['mode'] ?? null;
        if (\is_string($mode) && \in_array($mode, ProductFamilyAttributeInterface::MODES, true)) {
            return $mode;
        }

        return true === ($data['variantSpecific'] ?? false)
            ? ProductFamilyAttributeInterface::MODE_VARIANT_SPECIFIC
            : ProductFamilyAttributeInterface::MODE_SHARED;
    }
}
