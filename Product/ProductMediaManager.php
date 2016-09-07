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

use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineFieldDescriptor;
use Sulu\Component\Rest\ListBuilder\Doctrine\FieldDescriptor\DoctrineJoinDescriptor;

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
     * @param MediaManagerInterface $mediaManager
     * @param $productEntityName
     */
    public function __construct(MediaManagerInterface $mediaManager, $productEntityName)
    {
        $this->mediaManager = $mediaManager;
        $this->productEntityName = $productEntityName;
    }

    /**
     * Returns the field descriptors for product media.
     *
     * @return DoctrineFieldDescriptor[]
     */
    public function getFieldDescriptors()
    {
        if (!$this->fieldDescriptors) {
            $this->initFieldDescriptors();
        }

        return $this->fieldDescriptors;
    }

    /**
     * Initializes field descriptors for product media.
     */
    protected function initFieldDescriptors()
    {
        $this->fieldDescriptors = [];
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
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
            ],
            true,
            false
        );

        $this->fieldDescriptors['thumbnails'] = new DoctrineFieldDescriptor(
            'id',
            'thumbnails',
            self::$mediaEntityName,
            'media.media.thumbnails',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
            ],
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
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
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
        $this->fieldDescriptors['size'] = new DoctrineFieldDescriptor(
            'size',
            'size',
            self::$fileVersionEntityName,
            'media.media.size',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
            ],
            false,
            true,
            'bytes'
        );

        $this->fieldDescriptors['changed'] = new DoctrineFieldDescriptor(
            'changed',
            'changed',
            self::$fileVersionEntityName,
            'public.changed',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
            ],
            true,
            false,
            'date'
        );

        $this->fieldDescriptors['created'] = new DoctrineFieldDescriptor(
            'created',
            'created',
            self::$fileVersionEntityName,
            'public.created',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
            ],
            true,
            false,
            'date'
        );

        $this->fieldDescriptors['title'] = new DoctrineFieldDescriptor(
            'title',
            'title',
            self::$fileVersionMetaEntityName,
            'public.title',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
                self::$fileVersionMetaEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionMetaEntityName,
                    self::$fileVersionEntityName . '.meta'
                ),
            ],
            false,
            true,
            'title'
        );

        $this->fieldDescriptors['description'] = new DoctrineFieldDescriptor(
            'description',
            'description',
            self::$fileVersionMetaEntityName,
            'media.media.description',
            [
                self::$mediaEntityName => new DoctrineJoinDescriptor(
                    self::$mediaEntityName,
                    $this->productEntityName . '.media',
                    null,
                    DoctrineJoinDescriptor::JOIN_METHOD_INNER
                ),
                self::$fileEntityName => new DoctrineJoinDescriptor(
                    self::$fileEntityName,
                    self::$mediaEntityName . '.files'
                ),
                self::$fileVersionEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionEntityName,
                    self::$fileEntityName . '.fileVersions',
                    self::$fileVersionEntityName . '.version = ' . self::$fileEntityName . '.version'
                ),
                self::$fileVersionMetaEntityName => new DoctrineJoinDescriptor(
                    self::$fileVersionMetaEntityName,
                    self::$fileVersionEntityName . '.meta'
                ),
            ]
        );
    }
}
