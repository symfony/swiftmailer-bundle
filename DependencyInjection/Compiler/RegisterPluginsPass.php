<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * RegisterPluginsPass registers Swiftmailer plugins.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RegisterPluginsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->findDefinition('swiftmailer.mailer') || !$container->getParameter('swiftmailer.mailers')) {
            return;
        }

        $mailers = $container->getParameter('swiftmailer.mailers');
        foreach ($mailers as $name => $mailer) {
            $plugins = $this->findSortedByPriorityTaggedServiceIds($container, sprintf('swiftmailer.%s.plugin', $name));
            $transport = sprintf('swiftmailer.mailer.%s.transport', $name);
            $definition = $container->findDefinition($transport);
            foreach ($plugins as $id => $args) {
                $definition->addMethodCall('registerPlugin', array(new Reference($id)));
            }
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $serviceId
     * @return array
     */
    protected function findSortedByPriorityTaggedServiceIds(ContainerBuilder $container, $serviceId)
    {
        $taggedServices = $container->findTaggedServiceIds($serviceId);
        uasort(
            $taggedServices,
            function ($a, $b) {
                $a = isset($a[0]['priority']) ? $a[0]['priority'] : 0;
                $b = isset($b[0]['priority']) ? $b[0]['priority'] : 0;
                return $a > $b ? -1 : 1;
            }
        );
        return $taggedServices;
    }
}