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

namespace Sulu\Product\Infrastructure\Symfony\HttpKernel;

use Sulu\Bundle\HttpCacheBundle\ReferenceStore\ReferenceStore;
use Sulu\Bundle\PersistenceBundle\DependencyInjection\PersistenceExtensionTrait;
use Sulu\Bundle\PersistenceBundle\PersistenceBundleTrait;
use Sulu\Content\Infrastructure\Sulu\Preview\ContentObjectProvider;
use Sulu\Product\Application\AttributeType\AttributeTypeInterface;
use Sulu\Product\Application\AttributeType\JsonAttributeType;
use Sulu\Product\Application\AttributeType\NumberAttributeType;
use Sulu\Product\Application\AttributeType\OptionsAttributeType;
use Sulu\Product\Application\AttributeType\TextAttributeType;
use Sulu\Product\Application\Mapper\AttributeMapper;
use Sulu\Product\Application\Mapper\AttributeMapperInterface;
use Sulu\Product\Application\Mapper\ProductContentMapper;
use Sulu\Product\Application\Mapper\ProductDetailsMapper;
use Sulu\Product\Application\Mapper\ProductMapperInterface;
use Sulu\Product\Application\MessageHandler\ApplyWorkflowTransitionProductMessageHandler;
use Sulu\Product\Application\MessageHandler\CopyLocaleProductMessageHandler;
use Sulu\Product\Application\MessageHandler\CreateAttributeGroupMessageHandler;
use Sulu\Product\Application\MessageHandler\CreateAttributeMessageHandler;
use Sulu\Product\Application\MessageHandler\CreateProductMessageHandler;
use Sulu\Product\Application\MessageHandler\ModifyAttributeGroupMessageHandler;
use Sulu\Product\Application\MessageHandler\ModifyAttributeMessageHandler;
use Sulu\Product\Application\MessageHandler\ModifyProductMessageHandler;
use Sulu\Product\Application\MessageHandler\RemoveAttributeGroupMessageHandler;
use Sulu\Product\Application\MessageHandler\RemoveAttributeMessageHandler;
use Sulu\Product\Application\MessageHandler\RemoveProductMessageHandler;
use Sulu\Product\Application\MessageHandler\RemoveProductTranslationMessageHandler;
use Sulu\Product\Application\MessageHandler\RestoreProductVersionMessageHandler;
use Sulu\Product\Application\Webspace\WebspaceSettingsConfigurationResolver;
use Sulu\Product\Domain\Event\ProductCreatedEvent;
use Sulu\Product\Domain\Event\ProductModifiedEvent;
use Sulu\Product\Domain\Event\ProductRemovedEvent;
use Sulu\Product\Domain\Event\ProductRestoredEvent;
use Sulu\Product\Domain\Event\ProductTranslationAddedEvent;
use Sulu\Product\Domain\Event\ProductTranslationCopiedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRemovedEvent;
use Sulu\Product\Domain\Event\ProductTranslationRestoredEvent;
use Sulu\Product\Domain\Event\ProductWorkflowTransitionAppliedEvent;
use Sulu\Product\Domain\Model\Attribute;
use Sulu\Product\Domain\Model\AttributeGroup;
use Sulu\Product\Domain\Model\AttributeGroupAttribute;
use Sulu\Product\Domain\Model\AttributeGroupAttributeInterface;
use Sulu\Product\Domain\Model\AttributeGroupInterface;
use Sulu\Product\Domain\Model\AttributeGroupTranslation;
use Sulu\Product\Domain\Model\AttributeGroupTranslationInterface;
use Sulu\Product\Domain\Model\AttributeInterface;
use Sulu\Product\Domain\Model\AttributeOption;
use Sulu\Product\Domain\Model\AttributeOptionInterface;
use Sulu\Product\Domain\Model\AttributeOptionTranslation;
use Sulu\Product\Domain\Model\AttributeOptionTranslationInterface;
use Sulu\Product\Domain\Model\AttributeTranslation;
use Sulu\Product\Domain\Model\AttributeTranslationInterface;
use Sulu\Product\Domain\Model\Product;
use Sulu\Product\Domain\Model\ProductAttribute;
use Sulu\Product\Domain\Model\ProductAttributeInterface;
use Sulu\Product\Domain\Model\ProductDimensionContent;
use Sulu\Product\Domain\Model\ProductDimensionContentInterface;
use Sulu\Product\Domain\Model\ProductInterface;
use Sulu\Product\Domain\Model\ProductTranslation;
use Sulu\Product\Domain\Model\ProductTranslationInterface;
use Sulu\Product\Domain\Repository\AttributeGroupRepositoryInterface;
use Sulu\Product\Domain\Repository\AttributeRepositoryInterface;
use Sulu\Product\Domain\Repository\ProductRepositoryInterface;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeGroupRepository;
use Sulu\Product\Infrastructure\Doctrine\Repository\AttributeRepository;
use Sulu\Product\Infrastructure\Doctrine\Repository\ProductRepository;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\AttributeGroupAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductAdmin;
use Sulu\Product\Infrastructure\Sulu\Admin\ProductContentAdmin;
use Sulu\Product\Infrastructure\Sulu\Content\DataMapper\AdditionalWebspacesDataMapper;
use Sulu\Product\Infrastructure\Sulu\Content\Merger\AdditionalWebspacesMerger;
use Sulu\Product\Infrastructure\Sulu\Content\PageTreeProductSmartContentProvider;
use Sulu\Product\Infrastructure\Sulu\Content\ProductLinkProvider;
use Sulu\Product\Infrastructure\Sulu\Content\ProductSmartContentProvider;
use Sulu\Product\Infrastructure\Sulu\Content\ProductTeaserProvider;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\ProductSelectionPropertyResolver;
use Sulu\Product\Infrastructure\Sulu\Content\PropertyResolver\SingleProductSelectionPropertyResolver;
use Sulu\Product\Infrastructure\Sulu\Content\ResourceLoader\ProductResourceLoader;
use Sulu\Product\Infrastructure\Sulu\Content\Select\AttributeSelectService;
use Sulu\Product\Infrastructure\Sulu\Content\Select\AttributeTypeSelectService;
use Sulu\Product\Infrastructure\Sulu\HttpCache\EventSubscriber\ProductCacheInvalidationSubscriber;
use Sulu\Product\Infrastructure\Sulu\Reference\ProductReferenceRefresher;
use Sulu\Product\Infrastructure\Sulu\Route\ProductRouteDefaultsProvider;
use Sulu\Product\Infrastructure\Sulu\Search\AdminProductIndexListener;
use Sulu\Product\Infrastructure\Sulu\Search\AdminProductReindexProvider;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\AdminProductReindexProviderEnhancerInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexContentEnhancer;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexExcerptEnhancer;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexProviderEnhancerInterface;
use Sulu\Product\Infrastructure\Sulu\Search\Visitor\WebsiteProductReindexTaxonomyEnhancer;
use Sulu\Product\Infrastructure\Sulu\Search\WebsiteProductIndexListener;
use Sulu\Product\Infrastructure\Sulu\Search\WebsiteProductReindexProvider;
use Sulu\Product\Infrastructure\Sulu\Sitemap\ProductsSitemapProvider;
use Sulu\Product\Infrastructure\Sulu\Trash\ProductTrashItemHandler;
use Sulu\Product\Infrastructure\Symfony\Twig\ProductTwigExtension;
use Sulu\Product\UserInterface\Controller\Admin\AttributeController;
use Sulu\Product\UserInterface\Controller\Admin\AttributeGroupController;
use Sulu\Product\UserInterface\Controller\Admin\ProductContentController;
use Sulu\Product\UserInterface\Controller\Admin\ProductDetailsController;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @codeCoverageIgnore
 */
final class SuluProductBundle extends AbstractBundle
{
    use PersistenceBundleTrait;
    use PersistenceExtensionTrait;

    /**
     * @internal this method is not part of the public API and should only be called by the Symfony framework classes
     */
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode() // @phpstan-ignore-line
            ->children()
                ->arrayNode('default_main_webspace')
                    ->useAttributeAsKey('locale')
                    ->beforeNormalization()
                        ->ifString()
                        ->then(function($v) {
                            return ['default' => $v];
                        })
                    ->end()
                    ->prototype('scalar')->end()
                ->end()
                ->arrayNode('default_additional_webspaces')
                    ->beforeNormalization()
                        ->ifTrue(function($v) {
                            if (!\is_array($v)) {
                                return false;
                            }

                            return \count(\array_filter(\array_keys($v), 'is_string')) <= 0;
                        })
                        ->then(function($v) {
                            return ['default' => $v];
                        })
                    ->end()
                    ->prototype('array')->useAttributeAsKey('locale')->prototype('scalar')->end()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('objects')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('product')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')->defaultValue(Product::class)->end()
                            ->end()
                        ->end()
                        ->arrayNode('product_content')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('model')->defaultValue(ProductDimensionContent::class)->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<string, mixed> $config
     *
     * @internal this method is not part of the public API and should only be called by the Symfony framework classes
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array<string, array{model: class-string, repository?: class-string}> $objects */
        $objects = $config['objects'] ?? [];
        $this->configurePersistence($objects, $builder);

        /** @var array<string, string> $defaultMainWebspace */
        $defaultMainWebspace = $config['default_main_webspace'] ?? [];
        /** @var array<string, array<string>> $defaultAdditionalWebspaces */
        $defaultAdditionalWebspaces = $config['default_additional_webspaces'] ?? [];
        $builder->setParameter('sulu_product.default_main_webspace', $defaultMainWebspace);
        $builder->setParameter('sulu_product.default_additional_webspaces', $defaultAdditionalWebspaces);

        $services = $container->services();

        // Define autoconfigure interfaces
        $builder->registerForAutoconfiguration(AttributeTypeInterface::class)
            ->addTag('sulu_product.attribute_type');

        $builder->registerForAutoconfiguration(ProductMapperInterface::class)
            ->addTag('sulu_product.product_mapper');

        $builder->registerForAutoconfiguration(AttributeMapperInterface::class)
            ->addTag('sulu_product.attribute_mapper');

        // Built-in attribute types
        $services->set('sulu_product.attribute_type_number')
            ->class(NumberAttributeType::class)
            ->tag('sulu_product.attribute_type');

        $services->set('sulu_product.attribute_type_text')
            ->class(TextAttributeType::class)
            ->tag('sulu_product.attribute_type');

        $services->set('sulu_product.attribute_type_json')
            ->class(JsonAttributeType::class)
            ->tag('sulu_product.attribute_type');

        $services->set('sulu_product.attribute_type_options')
            ->class(OptionsAttributeType::class)
            ->tag('sulu_product.attribute_type');

        // Message Handler services
        $services->set('sulu_product.create_product_handler')
            ->class(CreateProductMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                tagged_iterator('sulu_product.product_mapper'),
                new Reference('sulu_activity.domain_event_collector'),
                new Reference('security.token_storage', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.modify_product_handler')
            ->class(ModifyProductMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                tagged_iterator('sulu_product.product_mapper'),
                new Reference('sulu_activity.domain_event_collector'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.remove_product_handler')
            ->class(RemoveProductMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_activity.domain_event_collector'),
                new Reference('sulu_trash.trash_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.remove_product_translation_handler')
            ->class(RemoveProductTranslationMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_activity.domain_event_collector'),
                new Reference('sulu_trash.trash_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.apply_workflow_transition_product_handler')
            ->class(ApplyWorkflowTransitionProductMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_workflow'),
                new Reference('sulu_activity.domain_event_collector'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.copy_locale_product_handler')
            ->class(CopyLocaleProductMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_copier'),
                new Reference('sulu_activity.domain_event_collector'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.restore_product_version_handler')
            ->class(RestoreProductVersionMessageHandler::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_copier'),
                new Reference('sulu_activity.domain_event_collector'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.product_content_mapper')
            ->class(ProductContentMapper::class)
            ->args([
                new Reference('sulu_content.content_persister'),
            ])
            ->tag('sulu_product.product_mapper');

        $services->set('sulu_product.product_details_mapper')
            ->class(ProductDetailsMapper::class)
            ->tag('sulu_product.product_mapper');

        $services->set('sulu_product.additional_webspaces_data_mapper')
            ->class(AdditionalWebspacesDataMapper::class)
            ->args([
                new Reference('sulu_product.webspace_settings_configuration_resolver'),
                new Reference('sulu_core.webspace.webspace_manager'),
            ])
            ->tag('sulu_content.data_mapper');

        $services->set('sulu_product.additional_webspaces_merger')
            ->class(AdditionalWebspacesMerger::class)
            ->tag('sulu_content.merger', ['priority' => 12]);

        $services->set('sulu_product.webspace_settings_configuration_resolver')
            ->class(WebspaceSettingsConfigurationResolver::class)
            ->args([
                '%sulu_product.default_main_webspace%',
                '%sulu_product.default_additional_webspaces%',
                new Reference('sulu_core.webspace.webspace_manager'),
            ]);

        $services->set('sulu_product.product_admin')
            ->class(ProductAdmin::class)
            ->args([
                new Reference('sulu_admin.view_builder_factory'),
                new Reference('sulu_security.security_checker'),
                new Reference('sulu.core.localization_manager'),
                new Reference('sulu_activity.activity_list_view_builder_factory'),
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu.admin');

        $services->set('sulu_product.product_content_admin')
            ->class(ProductContentAdmin::class)
            ->args([
                new Reference('sulu_content.content_view_builder_factory'),
                new Reference('sulu_security.security_checker'),
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu.admin');

        $services->set('sulu_product.attribute_admin')
            ->class(AttributeAdmin::class)
            ->args([
                new Reference('sulu_admin.view_builder_factory'),
                new Reference('sulu_security.security_checker'),
                new Reference('sulu.core.localization_manager'),
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu.admin');

        $services->set('sulu_product.attribute_type_select_service')
            ->class(AttributeTypeSelectService::class)
            ->public()
            ->args([
                tagged_iterator('sulu_product.attribute_type'),
                new Reference('translator'),
            ]);

        $services->set('sulu_product.attribute_repository')
            ->class(AttributeRepository::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
            ]);

        $services->alias(AttributeRepositoryInterface::class, 'sulu_product.attribute_repository');

        $services->set('sulu_product.attribute_mapper')
            ->class(AttributeMapper::class)
            ->args([
                new Reference('sulu_product.attribute_repository'),
            ])
            ->tag('sulu_product.attribute_mapper');

        $services->set('sulu_product.create_attribute_handler')
            ->class(CreateAttributeMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_repository'),
                tagged_iterator('sulu_product.attribute_mapper'),
                new Reference('sulu_product.attribute_group_repository'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.modify_attribute_handler')
            ->class(ModifyAttributeMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_repository'),
                tagged_iterator('sulu_product.attribute_mapper'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.remove_attribute_handler')
            ->class(RemoveAttributeMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_repository'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.admin_attribute_details_controller')
            ->class(AttributeController::class)
            ->public()
            ->args([
                new Reference('sulu_product.attribute_repository'),
                new Reference('sulu_message_bus'),
                new Reference('sulu_core.list_builder.field_descriptor_factory'),
                new Reference('sulu_core.doctrine_list_builder_factory'),
                new Reference('sulu_core.doctrine_rest_helper'),
            ])
            ->tag('sulu.context', ['context' => 'admin']);

        $services->set('sulu_product.attribute_group_repository')
            ->class(AttributeGroupRepository::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
            ]);

        $services->alias(AttributeGroupRepositoryInterface::class, 'sulu_product.attribute_group_repository');

        $services->set('sulu_product.create_attribute_group_handler')
            ->class(CreateAttributeGroupMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_group_repository'),
                new Reference('sulu_product.attribute_repository'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.modify_attribute_group_handler')
            ->class(ModifyAttributeGroupMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_group_repository'),
                new Reference('sulu_product.attribute_repository'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.remove_attribute_group_handler')
            ->class(RemoveAttributeGroupMessageHandler::class)
            ->args([
                new Reference('sulu_product.attribute_group_repository'),
                new Reference('sulu_product.attribute_repository'),
            ])
            ->tag('messenger.message_handler');

        $services->set('sulu_product.attribute_group_admin')
            ->class(AttributeGroupAdmin::class)
            ->args([
                new Reference('sulu_admin.view_builder_factory'),
                new Reference('sulu_security.security_checker'),
                new Reference('sulu.core.localization_manager'),
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu.admin');

        $services->set('sulu_product.admin_attribute_group_details_controller')
            ->class(AttributeGroupController::class)
            ->public()
            ->args([
                new Reference('sulu_product.attribute_group_repository'),
                new Reference('sulu_message_bus'),
                new Reference('sulu_core.list_builder.field_descriptor_factory'),
                new Reference('sulu_core.doctrine_list_builder_factory'),
                new Reference('sulu_core.doctrine_rest_helper'),
            ])
            ->tag('sulu.context', ['context' => 'admin']);

        $services->set('sulu_product.attribute_select_service')
            ->class(AttributeSelectService::class)
            ->public()
            ->args([
                new Reference('doctrine.orm.entity_manager'),
            ]);

        $services->set('sulu_product.product_repository')
            ->class(ProductRepository::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_content.dimension_content_query_enhancer'),
            ]);

        $services->alias(ProductRepositoryInterface::class, 'sulu_product.product_repository');
        $services->alias(ProductRepository::class, 'sulu_product.product_repository');

        $services->set('sulu_product.admin_product_content_controller')
            ->class(ProductContentController::class)
            ->public()
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_message_bus'),
                new Reference('serializer'),
                new Reference('sulu_content.content_manager'),
                new Reference('sulu_core.list_builder.field_descriptor_factory'),
                new Reference('sulu_core.doctrine_list_builder_factory'),
                new Reference('sulu_core.doctrine_rest_helper'),
            ])
            ->tag('sulu.context', ['context' => 'admin']);

        $services->set('sulu_product.admin_product_details_controller')
            ->class(ProductDetailsController::class)
            ->public()
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_message_bus'),
                new Reference('sulu_core.list_builder.field_descriptor_factory'),
                new Reference('sulu_core.doctrine_list_builder_factory'),
                new Reference('sulu_core.doctrine_rest_helper'),
            ])
            ->tag('sulu.context', ['context' => 'admin']);

        $services->set('sulu_product.single_product_selection_property_resolver')
            ->class(SingleProductSelectionPropertyResolver::class)
            ->tag('sulu_content.property_resolver');

        $services->set('sulu_product.product_selection_property_resolver')
            ->class(ProductSelectionPropertyResolver::class)
            ->tag('sulu_content.property_resolver');

        $services->set('sulu_product.product_resource_loader')
            ->class(ProductResourceLoader::class)
            ->args([
                new Reference('sulu_product.product_repository'),
            ])
            ->tag('sulu_content.resource_loader', ['type' => ProductResourceLoader::RESOURCE_LOADER_KEY]);

        $services->set('sulu_product.product_preview_provider')
            ->class(ContentObjectProvider::class)
            ->args([
                new Reference('sulu_admin.metadata_provider_registry'),
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_content.content_aggregator'),
                new Reference('sulu_content.content_data_mapper'),
                '%sulu.model.product.class%',
                ProductAdmin::SECURITY_CONTEXT,
            ])
            ->tag('sulu.context', ['context' => 'admin'])
            ->tag('sulu_preview.object_provider', ['provider-key' => ProductDimensionContentInterface::RESOURCE_KEY]);

        $services->set('sulu_product.product_teaser_provider')
            ->class(ProductTeaserProvider::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_aggregator'),
                new Reference('sulu_content.content_enhancer'),
                new Reference('translator'),
                new Reference('sulu_admin.teaser_tag_property_extractor'),
            ])
            ->tag('sulu.teaser.provider', ['alias' => ProductInterface::RESOURCE_KEY]);

        $services->set('sulu_product.product_link_provider')
            ->class(ProductLinkProvider::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_route.route_generator'),
                new Reference('sulu_core.webspace.request_analyzer'),
                new Reference('sulu_http_cache.reference_store'),
                new Reference('translator'),
                '%sulu.model.product_content.class%',
            ])
            ->tag('sulu.link.provider', ['alias' => 'product']);

        // Smart Content services
        $services->set('sulu_product.product_smart_content_provider')
            ->class(ProductSmartContentProvider::class)
            ->args([
                new Reference('sulu_content.dimension_content_query_enhancer'),
                new Reference('sulu_admin.smart_content_query_enhancer'),
                new Reference('doctrine.orm.entity_manager'),
            ])
        ->tag('sulu_content.smart_content_provider', ['type' => ProductInterface::RESOURCE_KEY]);

        $services->set('sulu_product.page_tree_product_smart_content_provider')
            ->class(PageTreeProductSmartContentProvider::class)
            ->args([
                new Reference('sulu_content.dimension_content_query_enhancer'),
                new Reference('sulu_admin.smart_content_query_enhancer'),
                new Reference('doctrine.orm.entity_manager'),
            ])
            ->tag('sulu_content.smart_content_provider', ['type' => PageTreeProductSmartContentProvider::PROVIDER_TYPE]);

        $services->set('sulu_product.product_reference_store')
            ->class(ReferenceStore::class)
            ->tag('sulu_website.reference_store', ['alias' => ProductInterface::RESOURCE_KEY]);

        // Twig Extensions
        $services->set('sulu_product.product_twig_extension')
            ->class(ProductTwigExtension::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_aggregator'),
                new Reference('sulu_core.webspace.request_analyzer'),
                new Reference('sulu_http_cache.reference_store'),
                new Reference('sulu_content.content_resolver'),
            ])
            ->tag('twig.extension');

        // Reference services
        $services->set('sulu_product.product_reference_refresher')
            ->class(ProductReferenceRefresher::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_reference.reference_repository'),
                new Reference('sulu_content.content_view_resolver'),
                new Reference('sulu_content.content_merger'),
            ])
            ->tag('sulu_reference.refresher');

        // Cache Invalidation
        $services->set('sulu_product.product_cache_invalidation_subscriber')
            ->class(ProductCacheInvalidationSubscriber::class)
            ->args([
                new Reference('sulu_http_cache.cache_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('sulu_route.route_repository'),
                new Reference('sulu_content.content_aggregator'),
                new Reference('sulu_route.route_generator'),
                new Reference('sulu_core.webspace.webspace_manager'),
            ])
            ->tag('kernel.event_subscriber');

        // Sitemap
        $services->set('sulu_product.products_sitemap_provider')
            ->class(ProductsSitemapProvider::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                new Reference('sulu_core.webspace.webspace_manager'),
                '%kernel.environment%',
                '%sulu_product.default_main_webspace%',
                '%sulu_product.default_additional_webspaces%',
            ])
            ->tag('sulu.sitemap.provider');

        // Trash services
        /** @var array<string, class-string> $bundles */
        $bundles = $builder->getParameter('kernel.bundles');
        if (isset($bundles['SuluTrashBundle'])) {
            $services->set('sulu_product.trash_item_handler')
                ->class(ProductTrashItemHandler::class)
                ->args([
                    new Reference('sulu_trash.trash_item_repository'),
                    new Reference('sulu_product.product_repository'),
                    new Reference('sulu_content.content_normalizer'),
                    new Reference('sulu_content.content_merger'),
                    tagged_iterator('sulu_product.product_mapper'),
                    new Reference('sulu_activity.domain_event_collector'),
                ])
                ->tag('sulu_trash.store_trash_item_handler')
                ->tag('sulu_trash.restore_trash_item_handler')
                ->tag('sulu_trash.restore_configuration_provider');
        }

        $services->set('sulu_product.product_route_defaults_provider')
            ->class(ProductRouteDefaultsProvider::class)
            ->args([
                new Reference('sulu_product.product_repository'),
                new Reference('sulu_content.content_aggregator'),
                new Reference('sulu_admin.metadata_provider_registry'),
                new Reference('sulu_http_cache.cache_lifetime.resolver'),
            ])
            ->tag('sulu_route.route_defaults_provider', ['resource_key' => ProductDimensionContentInterface::RESOURCE_KEY]);

        $services->set('sulu_product.admin_product_index_listener')
            ->class(AdminProductIndexListener::class)
            ->args([
                new Reference('sulu_message_bus'),
            ])
            ->tag('kernel.event_listener', ['event' => ProductCreatedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductModifiedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductRemovedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductRestoredEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductTranslationRestoredEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductTranslationAddedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductTranslationRemovedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductTranslationCopiedEvent::class, 'method' => 'onProductChanged']);

        $services->set('sulu_product.admin_product_reindex_provider')
            ->class(AdminProductReindexProvider::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                tagged_iterator('sulu_product.admin_product_reindex_provider_enhancer'),
            ])
            ->tag('cmsig_seal.reindex_provider');

        $services->set('sulu_product.website_product_index_listener')
            ->class(WebsiteProductIndexListener::class)
            ->args([
                new Reference('sulu_message_bus'),
            ])
            ->tag('kernel.event_listener', ['event' => ProductWorkflowTransitionAppliedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductRemovedEvent::class, 'method' => 'onProductChanged'])
            ->tag('kernel.event_listener', ['event' => ProductTranslationRemovedEvent::class, 'method' => 'onProductChanged']);

        $builder->registerForAutoconfiguration(AdminProductReindexProviderEnhancerInterface::class)
            ->addTag('sulu_product.admin_product_reindex_provider_enhancer');

        $builder->registerForAutoconfiguration(WebsiteProductReindexProviderEnhancerInterface::class)
            ->addTag('sulu_product.website_product_reindex_provider_enhancer');

        $services->set('sulu_product.website_product_reindex_content_enhancer')
            ->class(WebsiteProductReindexContentEnhancer::class)
            ->args([
                new Reference('sulu_admin.form_metadata_provider'),
            ])
            ->tag('sulu_product.website_product_reindex_provider_enhancer');

        $services->set('sulu_product.website_product_reindex_excerpt_enhancer')
            ->class(WebsiteProductReindexExcerptEnhancer::class)
            ->tag('sulu_product.website_product_reindex_provider_enhancer');

        $services->set('sulu_product.website_product_reindex_taxonomy_enhancer')
            ->class(WebsiteProductReindexTaxonomyEnhancer::class)
            ->tag('sulu_product.website_product_reindex_provider_enhancer');

        $services->set('sulu_product.website_product_reindex_provider')
            ->class(WebsiteProductReindexProvider::class)
            ->args([
                new Reference('doctrine.orm.entity_manager'),
                tagged_iterator('sulu_product.website_product_reindex_provider_enhancer'),
            ])
            ->tag('cmsig_seal.reindex_provider');
    }

    /**
     * @internal this method is not part of the public API and should only be called by the Symfony framework classes
     */
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('sulu_admin')) {
            $builder->prependExtensionConfig(
                'sulu_admin',
                [
                    'lists' => [
                        'directories' => [
                            \dirname(__DIR__, 4) . '/config/lists',
                        ],
                    ],
                    'forms' => [
                        'directories' => [
                            \dirname(__DIR__, 4) . '/config/forms',
                        ],
                    ],
                    'templates' => [
                        ProductInterface::TEMPLATE_TYPE => [
                            'directories' => [
                                'app' => '%kernel.project_dir%/config/templates/products',
                            ],
                        ],
                    ],
                    'resources' => [
                        'products' => [
                            'routes' => [
                                'list' => 'sulu_product.get_products',
                                'detail' => 'sulu_product.get_product',
                            ],
                        ],
                        ProductDimensionContentInterface::RESOURCE_KEY => [
                            'routes' => [
                                'detail' => 'sulu_product.get_product_content',
                            ],
                        ],
                        'products_versions' => [
                            'routes' => [
                                'list' => 'sulu_product.get_product_versions',
                                'detail' => 'sulu_product.get_product_content',
                            ],
                        ],
                        'attributes' => [
                            'routes' => [
                                'list' => 'sulu_product.get_attributes',
                                'detail' => 'sulu_product.get_attribute',
                            ],
                        ],
                        'attribute_groups' => [
                            'routes' => [
                                'list' => 'sulu_product.get_attribute_groups',
                                'detail' => 'sulu_product.get_attribute_group',
                            ],
                        ],
                    ],
                    'field_type_options' => [
                        'selection' => [
                            'product_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => 'products',
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => 'products',
                                        'display_properties' => ['title', 'routePath'],
                                        'icon' => 'su-newspaper',
                                        'label' => 'sulu_product.selection_label',
                                        'overlay_title' => 'sulu_product.selection_overlay_title',
                                    ],
                                ],
                            ],
                        ],
                        'single_selection' => [
                            'single_product_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => 'products',
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => 'products',
                                        'display_properties' => ['title'],
                                        'empty_text' => 'sulu_product.no_product_selected',
                                        'icon' => 'su-newspaper',
                                        'overlay_title' => 'sulu_product.single_selection_overlay_title',
                                    ],
                                ],
                            ],
                            'single_attribute_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => 'attributes',
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => 'attributes',
                                        'display_properties' => ['name'],
                                        'empty_text' => 'sulu_product.no_attribute_selected',
                                        'icon' => 'su-tag',
                                        'overlay_title' => 'sulu_product.select_attribute',
                                    ],
                                ],
                            ],
                            'single_attribute_group_selection' => [
                                'default_type' => 'list_overlay',
                                'resource_key' => 'attribute_groups',
                                'types' => [
                                    'list_overlay' => [
                                        'adapter' => 'table',
                                        'list_key' => 'attribute_groups',
                                        'display_properties' => ['name'],
                                        'empty_text' => 'sulu_product.no_attribute_group_selected',
                                        'icon' => 'su-tag',
                                        'overlay_title' => 'sulu_product.attribute_groups',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            );
        }

        if ($builder->hasExtension('doctrine')) {
            $builder->prependExtensionConfig(
                'doctrine',
                [
                    'orm' => [
                        'mappings' => [
                            'SuluProduct' => [
                                'type' => 'xml',
                                'prefix' => 'Sulu\Product\Domain\Model',
                                'dir' => \dirname(__DIR__, 4) . '/config/doctrine/Product',
                                'alias' => 'SuluProduct',
                                'is_bundle' => false,
                                'mapping' => true,
                            ],
                        ],
                    ],
                ],
            );
        }

        if ($builder->hasExtension('sulu_search')) {
            $suluSearchConfigs = $builder->getExtensionConfig('sulu_search');

            foreach ($suluSearchConfigs as $suluSearchConfig) {
                if (isset($suluSearchConfig['website']) && \is_array($suluSearchConfig['website']) && isset($suluSearchConfig['website']['indexes'])) {
                    $builder->prependExtensionConfig(
                        'sulu_search',
                        [
                            'website' => [
                                'indexes' => [
                                    ProductInterface::RESOURCE_KEY => ProductInterface::RESOURCE_KEY . '_published',
                                ],
                            ],
                        ],
                    );
                }
            }
        }

        if ($builder->hasExtension('sulu_search')) {
            $builder->prependExtensionConfig(
                'sulu_search',
                [
                    'admin' => [
                        'resources' => [
                            ProductInterface::RESOURCE_KEY => [
                                'name' => 'sulu_product.products',
                                'icon' => 'su-newspaper',
                                'route' => [
                                    'name' => ProductAdmin::EDIT_TABS_VIEW . '.details',
                                    'resultToRoute' => [
                                        'resourceId' => 'id',
                                        'locale' => 'locale',
                                    ],
                                ],
                                'securityContext' => ProductAdmin::SECURITY_CONTEXT,
                            ],
                        ],
                    ],
                ],
            );
        }
    }

    /**
     * @internal this method is not part of the public API and should only be called by the Symfony framework classes
     */
    public function getPath(): string
    {
        return \dirname(__DIR__, 4); // target the root of the library where config, src, ... is located
    }

    /**
     * @internal this method is not part of the public API and should only be called by the Symfony framework classes
     */
    public function build(ContainerBuilder $container): void
    {
        $this->buildPersistence([
            ProductInterface::class => 'sulu.model.product.class',
            ProductDimensionContentInterface::class => 'sulu.model.product_content.class',
            ProductTranslationInterface::class => ProductTranslation::class,
            ProductAttributeInterface::class => ProductAttribute::class,
            AttributeInterface::class => Attribute::class,
            AttributeTranslationInterface::class => AttributeTranslation::class,
            AttributeOptionInterface::class => AttributeOption::class,
            AttributeOptionTranslationInterface::class => AttributeOptionTranslation::class,
            AttributeGroupInterface::class => AttributeGroup::class,
            AttributeGroupTranslationInterface::class => AttributeGroupTranslation::class,
            AttributeGroupAttributeInterface::class => AttributeGroupAttribute::class,
        ], $container);
    }
}
