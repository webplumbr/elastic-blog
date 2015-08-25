<?php

namespace Webplumbr\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Webplumbr\BlogBundle\Form\BlogCommentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class DefaultController extends Controller
{
    /**
     * @Route("/{page}", name="blog_home", requirements={"page": "\d+"}, defaults={"page": 1})
     * @Template()
     */
    public function homeAction($page)
    {
        return $this->redirect($this->generateUrl('blog_index', array('page' => $page)), 301);
    }

    /**
     * @Route("/page/{page}", name="blog_index", requirements={"page": "\d+"}, defaults={"page": 1})
     * @Template()
     */
    public function indexAction($page)
    {
        $size = 10;
        $page = max($page, 1);

        //check for query params (from wordpress route forwards)
        $tag    = $this->getRequest()->get('tag');
        $author = $this->getRequest()->get('author');
        $year   = $this->getRequest()->get('year');
        $month  = $this->getRequest()->get('month');
        $postId = $this->getRequest()->get('post_id');
        //when a user searches for by a keyword or phrase
        $term   = $this->getRequest()->get('query');

        if (!empty($tag)) {
            $query = array('match' => array('tags' => $tag));
        } else if (!empty($postId)) {
            $query = array('match' => array('post_id' => $postId));
        } else if (!empty($author)) {
            $query = array('match' => array('author' => $author));
        } else if (!empty($year) && !empty($month)) {
            $start = sprintf('%s-%s-01 00:00:00', $year, $month);
            $end   = sprintf('%s-%s-%s 23:59:59', $year, $month, date('t', strtotime("$year-$month-01")));
            $query = array('range' => array('post_date' => array('lte' => $end, 'gte' => $start, 'format' => 'yyyy-MM-dd HH:mm:ss')));
        } else if (!empty($term)) {
            $query = array(
                'multi_match' => array(
                    'query'  => rawurldecode($term),
                    'fields' => array('title^2', 'content'),
                    'type'   => 'phrase'
                ));
        } else {
            $query = array('match_all' => array());
        }

        $posts = $this->get('elasticsearch')->getPublishedPosts($query, $page, $size);

        foreach ($posts['posts'] as &$item) {
            $item['content']   = \Parsedown::instance()->setBreaksEnabled(true)->text($item['content']);
            $item['post_date'] = $this->get('blog_helper')->getElapsedTime($item['post_date']);
        }

        //fetch date stats
        $tags = $this->get('elasticsearch')->getTagCollection();

        $postsByMonth = $this->get('elasticsearch')->getPostsByMonth();

        $uri = $this->getRequest()->getUri();

        if (!preg_match('/\/$/i', $uri)) {
            $uri .= '/';
        }

        $templateVars = array(
            'posts' => $posts,
            'tags'  => $tags,
            'posts_by_month' => $postsByMonth
        );

        if ($page > 1) {
            $templateVars['prev_uri'] = preg_match('/\d+\/$/', $uri) ? preg_replace('/\d+\/$/', max($page - 1, 1), $uri) : sprintf('%s%s', $uri, max($page - 1, 1));
        }

        if ($page < ceil($posts['total']/$size)) {
            $templateVars['next_uri'] = preg_match('/\d+\/$/', $uri) ? preg_replace('/\d+\/$/', $page + 1, $uri) : sprintf('%s%s', $uri, $page + 1);
        }

        return $templateVars;
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/{postId}", name="archives", requirements={"postId": "\d+"})
     * @Template()
     *
     */
    public function archivesAction($postId)
    {
        $query = array('match' => array('post_id' => $postId));
        $posts = $this->get('elasticsearch')->getPublishedPosts($query);

        if ($posts['total'] == 0) {
            return new Response('Not found', 404);
        }

        $post  = array();
        if (isset($posts['posts']) && count($posts['posts'])) {
            $post = array_shift($posts['posts']);
            $post['post_date'] = $this->get('blog_helper')->getElapsedTime($post['post_date']);
            $post['content']   = \Parsedown::instance()->setBreaksEnabled(true)->text($post['content']);
        }

        $relatedPosts = $this->get('elasticsearch')->getRelatedPosts($post);
        $tags         = $this->get('elasticsearch')->getTagCollection();
        $postsByMonth = $this->get('elasticsearch')->getPostsByMonth();
        $comments     = $this->get('elasticsearch')->getCommentsByPostId($postId);

        if (isset($comments['comments']) && count($comments['comments'])) {
            foreach ($comments['comments'] as &$comment) {
                $comment['content']      = \Parsedown::instance()->setBreaksEnabled(true)->text($comment['content']);
                $comment['comment_date'] = $this->get('blog_helper')->getElapsedTime($comment['comment_date']);
            }
        }

        //comment Form
        $newComment = array('post_id' => $postId, 'commenter' => null, 'content' => null, 'ip' => $this->getRequest()->getClientIp());
        $form = $this->createForm(new BlogCommentType(), $newComment);
        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->addComment($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'Thanks! Your comment has been submitted');
            return $this->redirect($this->generateUrl('archives', array('postId' => $postId)));
        }

        return array(
            'post'           => $post,
            'related'        => $relatedPosts,
            'tags'           => $tags,
            'posts_by_month' => $postsByMonth,
            'comments'       => $comments,
            'form'           => $form->createView()
        );
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/author/{name}/{page}", requirements={"name": "\w+", "page": "\d+"}, defaults={"page": 1})
     *
     */
    public function postByAuthorRefAction($name, $page)
    {
        return $this->redirect($this->generateUrl('post_by_author', array('page' => $page, 'name' => $name)), 301);
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/author/{name}/page/{page}", name="post_by_author", requirements={"name": "\w+"}, defaults={"page": 1})
     *
     */
    public function postByAuthorAction($name, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Default:index', array('page' => $page), array('author' => $name));
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/tag/{name}/{page}", requirements={"tag": "\w+", "page": "\d+"}, defaults={"page": 1})
     *
     */
    public function postByTagRefAction($name, $page)
    {
        return $this->redirect($this->generateUrl('post_by_tag', array('page' => $page, 'tag' => $name)), 301);
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/tag/{name}/page/{page}", name="post_by_tag", requirements={"tag": "\w+"}, defaults={"page": 1})
     *
     */
    public function postByTagAction($name, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Default:index', array('page' => $page), array('tag' => $name));
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/date/{year}/{month}/{page}", requirements={"year": "\d{4}", "month": "\d{2}", "page" : "\d+"}, defaults={"page": 1})
     *
     */
    public function postByDateRefAction($year, $month, $page)
    {
        return $this->redirect($this->generateUrl('post_by_date', array('page' => $page, 'year' => $year, 'month' => $month)), 301);
    }

    /**
     * To tackle Wordpress routes
     * @Route("/archives/date/{year}/{month}/page/{page}", name="post_by_date", requirements={"year": "\d{4}", "month": "\d{2}"}, defaults={"page": 1})
     *
     */
    public function postByDateAction($year, $month, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Default:index', array('page' => $page), array('year' => $year, 'month' => $month));
    }

    /**
     * To tackle Wordpress routes
     * @Route("/page/{page}", name="page", requirements={"page": "\d+"})
     *
     */
    public function pageAction($page)
    {
        return $this->forward('WebplumbrBlogBundle:Default:index', array('page' => $page));
    }

    /**
     * @Route("/about", name="about")
     * @Template()
     *
     */
    public function aboutAction()
    {
        $tags         = $this->get('elasticsearch')->getTagCollection();
        $postsByMonth = $this->get('elasticsearch')->getPostsByMonth();

        return array('tags' => $tags, 'posts_by_month' => $postsByMonth);
    }

    /**
     * @Route("/posts/search", name="posts_search")
     *
     */
    public function postsSearchAction(Request $request)
    {
        return $this->redirect($this->generateUrl('posts_by_query', array('query' => $request->get('query'))));
    }

    /**
     * @Route("/posts/{query}/page/{page}", name="posts_by_query", defaults={"page": 1})
     *
     */
    public function postsByQueryAction($query, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Default:index', array('page' => $page), array('query' => rawurlencode($query)));
    }
}
