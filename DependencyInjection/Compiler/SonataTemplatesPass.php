<?php

/*
 * This file is part of the YesWeHack BugBounty backend
 *
 * (c) Romain Honel <romain.honel@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Picoss\SonataExtraAdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class SonataTemplatesPass.
 *
 * @author Romain Honel <romain.honel@gmail.com>
 */
class SonataTemplatesPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $extraTemplates = $container->getParameter('picoss_sonata_extra_admin.templates');

        $bundles = $container->getParameter('kernel.bundles');
        if (isset($bundles['SonataDoctrineORMAdminBundle'])) {
            $templates = array_merge_recursive($extraTemplates, $container->getParameter('sonata_doctrine_orm_admin.templates'));
            $container->setParameter('sonata_doctrine_orm_admin.templates', $templates);

            // define the templates
            $container->getDefinition('sonata.admin.builder.orm_list')
                ->replaceArgument(1, $templates['types']['list']);

            $container->getDefinition('sonata.admin.builder.orm_show')
                ->replaceArgument(1, $templates['types']['show']);
        }

        if (isset($bundles['SonataDoctrineMongoDBAdminBundle'])) {
            $templates = array_merge_recursive($extraTemplates, $container->getParameter('sonata_doctrine_mongodb_admin.templates'));
            $container->setParameter('sonata_doctrine_mongodb_admin.templates', $templates);

            // define the templates
            $container->getDefinition('sonata.admin.builder.doctrine_mongodb_list')
                ->replaceArgument(1, $templates['types']['list']);

            $container->getDefinition('sonata.admin.builder.doctrine_mongodb_show')
                ->replaceArgument(1, $templates['types']['show']);
        }

        if (isset($bundles['SonataDoctrineMongoDBAdminBundle'])) {
            $templates = array_merge_recursive($extraTemplates, $container->getParameter('sonata.admin.manager.doctrine_phpcr.templates'));
            $container->setParameter('sonata.admin.manager.doctrine_phpcr.templates', $templates);

            // define the templates
            $container->getDefinition('sonata.admin.builder.doctrine_phpcr_list')
                ->replaceArgument(1, $templates['types']['list']);

            $container->getDefinition('sonata.admin.builder.doctrine_phpcr_show')
                ->replaceArgument(1, $templates['types']['show']);
        }

        foreach ($container->findTaggedServiceIds('sonata.admin') as $id => $tags) {
            foreach ($tags as $attributes) {
                $definition = $container->getDefinition($id);
                $definition->addMethodCall('setTemplate', ['history', $extraTemplates['history']]);
                $definition->addMethodCall('setTemplate', ['history_revert', $extraTemplates['history_revert']]);
                $definition->addMethodCall('setTemplate', ['history_revision_timestamp', $extraTemplates['history_revision_timestamp']]);
                $definition->addMethodCall('setTemplate', ['trash', $extraTemplates['trash']]);
                $definition->addMethodCall('setTemplate', ['untrash', $extraTemplates['untrash']]);
                $definition->addMethodCall('setTemplate', ['untrash_all', $extraTemplates['untrash_all']]);
                $definition->addMethodCall('setTemplate', ['hard_delete', $extraTemplates['hard_delete']]);
                $definition->addMethodCall('setTemplate', ['hard_delete_all', $extraTemplates['hard_delete_all']]);
                $definition->addMethodCall('setTemplate', ['inner_trash_list_row', $extraTemplates['inner_trash_list_row']]);
                $definition->addMethodCall('setTemplate', ['button_trash', $extraTemplates['button_trash']]);
                $definition->addMethodCall('setTemplate', ['button_hard_delete_all', $extraTemplates['button_hard_delete_all']]);
                $definition->addMethodCall('setTemplate', ['button_untrash_all', $extraTemplates['button_untrash_all']]);
            }
        }
    }
}
