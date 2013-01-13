<?php

namespace Briareos\ChatBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('briareos_chat');
        $rootNode
            ->children()
                ->scalarNode('picture_provider')->defaultValue('chat_subject.picture_provider')->end()
                ->scalarNode('presence_provider')->defaultValue('chat_subject.presence_provider')->end()
                ->scalarNode('default_container')->defaultValue('body')->end()
                ->arrayNode('templates')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('chat')->defaultValue('BriareosChatBundle:Chat:chat.html.twig')->end()
                        ->scalarNode('message')->defaultValue('BriareosChatBundle:Chat:message.html.twig')->end()
                        ->scalarNode('messages')->defaultValue('BriareosChatBundle:Chat:messages.html.twig')->end()
                        ->scalarNode('status')->defaultValue('BriareosChatBundle:Chat:status.html.twig')->end()
                        ->scalarNode('user')->defaultValue('BriareosChatBundle:Chat:user.html.twig')->end()
                        ->scalarNode('window')->defaultValue('BriareosChatBundle:Chat:window.html.twig')->end()
                    ->end()
                ->end()
                ->arrayNode('routes')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('cache')->defaultValue('briareos_chat_cache')->end()
                        ->scalarNode('activate')->defaultValue('briareos_chat_activate')->end()
                        ->scalarNode('close')->defaultValue('briareos_chat_close')->end()
                        ->scalarNode('send')->defaultValue('briareos_chat_send')->end()
                        ->scalarNode('ping')->defaultValue('briareos_chat_ping')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
