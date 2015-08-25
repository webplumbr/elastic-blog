<?php

namespace Webplumbr\BlogBundle\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class KernelExceptionListener
{
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $excp = $event->getException();

        $response = new Response;
        $response->setContent('Oops! Something has gone wrong...');

        if ($excp instanceof HttpExceptionInterface) {
            $response->setStatusCode($excp->getStatusCode());
            $response->headers->replace($excp->getHeaders());
        } else {
            $response->setStatusCode(500);
        }

        $event->setResponse($response);
    }
}