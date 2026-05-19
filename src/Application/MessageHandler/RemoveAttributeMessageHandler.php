<?php

declare(strict_types=1);

namespace Sulu\Product\Application\MessageHandler;

use Sulu\Product\Application\Message\RemoveAttributeMessage;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;

final class RemoveAttributeMessageHandler
{
    public function __construct(private AttributeRepositoryInterface $attributeRepository)
    {
    }

    public function __invoke(RemoveAttributeMessage $message): void
    {
        $identifier = $message->getIdentifier();

        $attribute = $this->attributeRepository->findOneBy(['uuid' => $identifier['uuid'] ?? null]);

        if (null === $attribute) {
            throw new \RuntimeException(\sprintf('Attribute with uuid "%s" not found.', $identifier['uuid'] ?? ''));
        }

        $this->attributeRepository->remove($attribute);
    }
}
