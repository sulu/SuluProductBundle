<?php

/*
 * This file is part of Sulu.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Bundle\ProductBundle\Product;

use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\ProductBundle\Entity\ProductInterface;
use Sulu\Bundle\ProductBundle\Product\Exception\ProductException;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;

/**
 * This service is responsible for managing product media relations.
 */
class ProductMediaManager implements ProductMediaManagerInterface
{
    protected static $mediaEntityName = 'SuluMediaBundle:Media';
    protected static $collectionEntityName = 'SuluMediaBundle:Collection';
    protected static $fileVersionEntityName = 'SuluMediaBundle:FileVersion';
    protected static $fileEntityName = 'SuluMediaBundle:File';
    protected static $fileVersionMetaEntityName = 'SuluMediaBundle:FileVersionMeta';

    protected $fieldDescriptors = null;

    /**
     * @var MediaManagerInterface
     */
    protected $mediaManager;

    /**
     * @var string
     */
    protected $productEntityName;

    /**
     * @var MediaRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @param MediaManagerInterface $mediaManager
     * @param MediaRepositoryInterface $mediaRepository
     * @param string $productEntityName
     */
    public function __construct(
        MediaManagerInterface $mediaManager,
        MediaRepositoryInterface $mediaRepository,
        $productEntityName
    ) {
        $this->mediaManager = $mediaManager;
        $this->mediaRepository = $mediaRepository;
        $this->productEntityName = $productEntityName;
    }

    /**
     * {@inheritdoc}
     */
    public function save(ProductInterface $product, array $mediaIds)
    {
        foreach ($product->getMedia() as $media) {
            $product->removeMedia($media);
        }

        $mediaEntities = $this->mediaRepository->findById($mediaIds);
        $existingMediaIds = $this->retrieveIdsOfArray($mediaEntities);

        // Check if all given media ids do exist.
        $this->checkMediaIds($mediaIds, $existingMediaIds);

        foreach ($mediaEntities as $mediaEntity) {
            $product->addMedia($mediaEntity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ProductInterface $product, array $mediaIds)
    {
        $mediaEntities = $this->mediaRepository->findById($mediaIds);
        $existingMediaIds = $this->retrieveIdsOfArray($mediaEntities);

        // Check if all given media ids do exist.
        $this->checkMediaIds($mediaIds, $existingMediaIds);

        foreach ($mediaEntities as $mediaEntity) {
            if (!$product->getMedia()->contains($mediaEntity)) {
                throw new ProductException(
                    sprintf(
                        'Product with id %s does not contain media with id',
                        $product->getId(),
                        $mediaEntity->getId()
                    )
                );
            }

            $product->removeMedia($mediaEntity);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFieldDescriptors($locale)
    {
        if (!$this->fieldDescriptors) {
            $this->initFieldDescriptors($locale);
        }

        return $this->fieldDescriptors;
    }

    /**
     * Initializes field descriptors for product media.
     *
     * @param string $locale
     */
    protected function initFieldDescriptors($locale)
    {
        $this->fieldDescriptors = [];

        $mediaJoin = [
            self::$mediaEntityName => new DoctrineJoinDescriptor(
                self::$mediaEntityName,
                $this->productEntityName . '.media',
                null,
                DoctrineJoinDescriptor::JOIN_METHOD_INNER
            ),
        ];
        $fileVersionJoin = array_merge(
            $mediaJoin,
            [
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
            ]
        );
        $fileVersionMetaJoin = array_merge(
            $fileVersionJoin,
            [
                self::$fileVersionMetaEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionMetaEntityName,
                    self::$fileVersionEntityName . '.meta',
                    self::$fileVersionMetaEntityName . '.locale = \'' . $locale . '\''
                ),
            ]
        );

        $this->fieldDescriptors['product'] = new DoctrineFieldDescriptor(
            'id',
            'product',
            $this->productEntityName,
            null,
            [],
            true,
            false
        );

        $this->fieldDescriptors['id'] = new DoctrineFieldDescriptor(
            'id',
            'id',
            self::$mediaEntityName,
            'public.id',
            $mediaJoin,
            true,
            false
        );

        $this->fieldDescriptors['thumbnails'] = new DoctrineFieldDescriptor(
            'id',
            'thumbnails',
            self::$mediaEntityName,
            'media.media.thumbnails',
            $mediaJoin,
            false,
            true,
            'thumbnails',
            '',
            '',
            false
        );

        $this->fieldDescriptors['name'] = new DoctrineFieldDescriptor(
            'name',
            'name',
            self::$fileVersionEntityName,
            'public.name',
            $fileVersionJoin
        );

        $this->fieldDescriptors['size'] = new DoctrineFieldDescriptor(
            'size',
            'size',
            self::$fileVersionEntityName,
            'media.media.size',
            $fileVersionJoin,
            false,
            true,
            'bytes'
        );

        $this->fieldDescriptors['changed'] = new DoctrineFieldDescriptor(
            'changed',
            'changed',
            self::$fileVersionEntityName,
            'public.changed',
            $fileVersionJoin,
            true,
            false,
            'date'
        );

        $this->fieldDescriptors['created'] = new DoctrineFieldDescriptor(
            'created',
            'created',
            self::$fileVersionEntityName,
            'public.created',
            $fileVersionJoin,
            true,
            false,
            'date'
        );

        $this->fieldDescriptors['title'] = new DoctrineFieldDescriptor(
            'title',
            'title',
            self::$fileVersionMetaEntityName,
            'public.title',
            $fileVersionMetaJoin,
            false,
            true,
            'title'
        );

        $this->fieldDescriptors['description'] = new DoctrineFieldDescriptor(
            'description',
            'description',
            self::$fileVersionMetaEntityName,
            'media.media.description',
            $fileVersionMetaJoin
        );
    }

    /**
     * @param array $givenMediaIds
     * @param array $existingMediaIds
     *
     * @throws ProductException
     */
    private function checkMediaIds(array $givenMediaIds, array $existingMediaIds)
    {
        $difference = array_diff($givenMediaIds, $existingMediaIds);
        if (count($difference)) {
            throw new ProductException(sprintf('The media ids %s were not found', implode(',', $difference)));
        }
    }

    /**
     * Returns all ids of a collection of entities.
     *
     * @param array $entities
     *
     * @return array
     */
    private function retrieveIdsOfArray(array $entities)
    {
        return array_map(
            function ($entity) {
                return $entity->getId();
            },
            $entities
        );
    }
}
