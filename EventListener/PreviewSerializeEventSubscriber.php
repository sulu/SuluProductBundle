<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\EventListener;

use JMS\Serializer\EventDispatcher\Events;
use JMS\Serializer\EventDispatcher\EventSubscriberInterface;
use JMS\Serializer\EventDispatcher\ObjectEvent;
use Sulu\Bundle\ContactBundle\Entity\AccountRepository;
use Sulu\Bundle\ProductBundle\Entity\ProductTranslation;
use Sulu\Component\Security\Authentication\UserInterface;
use Sulu\Component\Security\Authentication\UserRepositoryInterface;

/**
 * Event subscriber that is used for product preview.
 */
class PreviewSerializeEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var UserRepositoryInterface
     */
    private $userRepository;

    /**
     * @var AccountRepository
     */
    private $accountRepository;

    /**
     * @param UserRepositoryInterface $userRepository
     * @param AccountRepository $accountRepository
     */
    public function __construct(UserRepositoryInterface $userRepository, AccountRepository $accountRepository)
    {
        $this->userRepository = $userRepository;
        $this->accountRepository = $accountRepository;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            [
                'event' => Events::POST_SERIALIZE,
                'format' => 'json',
                'method' => 'onPostSerialize',
            ],
            [
                'event' => Events::POST_DESERIALIZE,
                'format' => 'json',
                'method' => 'onPostDeSerialize',
            ],
        ];
    }

    /**
     * Deserialization event for product preview for handling additional data.
     *
     * @param ObjectEvent $event
     */
    public function onPostDeSerialize(ObjectEvent $event)
    {
        if (!$this->isValidPreviewContext($event)) {
            return;
        }

        /** @var ProductTranslation $object */
        $object = $event->getObject();
        $context = $event->getContext();
        $product = $object->getProduct();

        $data = $context->attributes->get('data')->get();

        if (isset($data['creator'])) {
            $creator = $this->userRepository->find($data['creator']['id']);
            if ($creator) {
                $product->setCreator($creator);
            }
        }
        if (isset($data['changer'])) {
            $changer = $this->userRepository->find($data['changer']['id']);
            if ($changer) {
                $product->setChanger($changer);
            }
        }
        if (isset($data['supplier'])) {
            $supplier = $this->accountRepository->find($data['supplier']['id']);
            if ($supplier) {
                $product->setSupplier($supplier);
            }
        }
    }

    /**
     * Serialization event for product preview for serializing additional data.
     *
     * @param ObjectEvent $event
     */
    public function onPostSerialize(ObjectEvent $event)
    {
        if (!$this->isValidPreviewContext($event)) {
            return;
        }

        /** @var ProductTranslation $object */
        $object = $event->getObject();
        $visitor = $event->getVisitor();
        $product = $object->getProduct();

        $creator = $product->getCreator();
        if ($creator) {
            $visitor->addData('creator', $this->getUserData($creator));
        }
        $changer = $product->getChanger();
        if ($changer) {
            $visitor->addData('changer', $this->getUserData($changer));
        }
        $supplier = $product->getSupplier();
        if ($supplier) {
            $supplierData = [
                'id' => $supplier->getId(),
                'name' => $supplier->getName(),
            ];
            $visitor->addData('supplier', $supplierData);
        }
    }

    /**
     * Returns user data for serialization.
     *
     * @param UserInterface $user
     *
     * @return array
     */
    protected function getUserData(UserInterface $user)
    {
        return [
            'id' => $user->getId(),
            'name' => $user->getFullName(),
        ];
    }

    /**
     * Returns if ProductTranslation is processed and serialization-context contains group preview.
     *
     * @param ObjectEvent $event
     *
     * @return bool
     */
    protected function isValidPreviewContext(ObjectEvent $event)
    {
        $object = $event->getObject();

        // Only process when object is Product.
        if (!$object instanceof ProductTranslation) {
            return false;
        }

        // Only continue if context group is set to preview.
        $context = $event->getContext();
        $groups = $context->attributes->get('groups');
        if (!$groups || !in_array('preview', $groups->get())) {
            return false;
        }

        return true;
    }
}
