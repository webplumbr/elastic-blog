<?php

namespace Webplumbr\BlogBundle\Lib;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RetrieveAssets
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function fetch(array $links)
    {
        $path  = $this->container->get('kernel')->locateResource('@WebplumbrBlogBundle/Resources/public/uploads/');
        $links = $this->screenLinks($links);

        $tempFile = $path. 'asset-links';
        file_put_contents($tempFile, implode("\n", $links));
        //start an asynchronous process to retrieve assets
        $builder = new ProcessBuilder();
        $builder->setPrefix('wget')
                ->setArguments(
                    array(
                        sprintf('--input-file=%s', $tempFile),
                        sprintf('--directory-prefix=%s', $path),
                        '--timeout=10'
                    ));

        $process = $builder->getProcess();
        $process->setTimeout(1800);
        //ideally start() should do
        //for some reason, start() does NOT seem to fetch assets
        //hence resorting to blocking run() call
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    private function screenLinks(array $links)
    {
        $list = array();
        foreach ($links as $link) {
            if (preg_match('/\.[a-z]{3,4}$/', $link)) {
                $list[] = $link;
            }
        }

        return $list;
    }
}