<?php
namespace Webplumbr\BlogBundle\Twig;

class TruncateExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('truncate', array($this, 'truncateFilter')),
        );
    }

    public function truncateFilter($text)
    {
        //ensure the supplied text is truncated at the right spot
        //meaning - do NOT split in the middle of a tag
        $length = strlen($text);
        if ($length < 255) {
            return $text;
        }

        return substr(strip_tags($text), 0, 255);
    }

    public function getName()
    {
        return 'truncate_extension';
    }
}