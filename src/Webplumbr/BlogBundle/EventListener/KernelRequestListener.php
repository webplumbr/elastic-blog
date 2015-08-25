<?php

namespace Webplumbr\BlogBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Elasticsearch\Common\Exceptions\ElasticsearchException;

class KernelRequestListener
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        //inject blog title and subtitle here
        try {
            $meta = $this->container->get('elasticsearch')->getMetadata();
        } catch (ElasticsearchException $e) {
            //@todo ignore until a plan is in place
            $meta = array();
        }
        $this->container->get('twig')->addGlobal('blog_title', isset($meta['title']) ? $meta['title'] : 'My blog title');
        $this->container->get('twig')->addGlobal('blog_subtitle', isset($meta['subtitle']) ? $meta['subtitle'] : 'My blog subtitle');
    }
}