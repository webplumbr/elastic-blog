<?php

namespace Webplumbr\BlogBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpFoundation\Request;
use Webplumbr\BlogBundle\Form\NewPostType;
use Webplumbr\BlogBundle\Form\PostType;
use Webplumbr\BlogBundle\Form\CommentType;
use Webplumbr\BlogBundle\Form\MetaType;
use Webplumbr\BlogBundle\Form\UserType;
use Webplumbr\BlogBundle\Form\NewUserType;

class AdminController extends Controller
{
    /**
     * @Route("/admin/", name="admin_home")
     * @Template()
     *
     */
    public function indexAction()
    {
        return $this->redirect($this->generateUrl('admin_dashboard'), 301);
    }

    /**
     * @Route("/admin/login", name="login_route")
     */
    public function loginAction(Request $request)
    {
        $session = $request->getSession();

        // get the login error if there is one
        if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(SecurityContextInterface::AUTHENTICATION_ERROR);
        } elseif (null !== $session && $session->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $error = $session->get(SecurityContextInterface::AUTHENTICATION_ERROR);
            $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);
        } else {
            $error = null;
        }

        // last username entered by the user
        $lastUsername = (null === $session) ? '' : $session->get(SecurityContextInterface::LAST_USERNAME);

        return $this->render(
            'WebplumbrBlogBundle:Admin:login.html.twig',
            array(
                // last username entered by the user
                'last_username' => $lastUsername,
                'error'         => $error,
            )
        );
    }

    /**
     * @Route("/admin/login-check", name="login_check")
     */
    public function loginCheckAction()
    {
        // this controller will not be executed,
        // as the route is handled by the Security system
    }


    /**
     * @Route("/admin/logout", name="logout")
     */
    public function logoutAction()
    {
        // this controller will not be executed,
        // as the route is handled by the Security system
    }

    /**
     * @Route("/admin/import/wordpress-blog", name="import_wordpress_blog")
     * @Template()
     *
     */
    public function importWordpressBlogAction(Request $request)
    {
        $warnings = $notices = array();

        if ($request->isMethod('post')) {
            //max execution time set to 30 minutes
            set_time_limit(1800);

            $file = $request->files->get('filename');
            if ($file instanceof UploadedFile && $file->getMimeType() !== 'text/xml') {

                $data = $this->get('blog_helper')->wordpressXMLToArrayConverter($file->getRealPath());

                $numOfPosts = count($data['posts']);

                if ($numOfPosts > 0) {

                    //clear existing index
                    if ($this->get('elasticsearch')->indexExists()) {
                        $this->get('elasticsearch')->deleteIndex();
                    }
                    //create an index
                    $this->get('elasticsearch')->createIndex();

                    //index meta info
                    $this->get('elasticsearch')->indexMetadata($data['meta']);

                    //index users
                    foreach ($data['users'] as $user) {
                        //starting indexing users
                        $this->get('elasticsearch')->indexUser($user);
                    }

                    foreach ($data['posts'] as $post) {
                        //starting indexing posts
                        $this->get('elasticsearch')->indexPost($post);
                    }

                    $notices[] = sprintf('Successfully indexed %s Posts', $numOfPosts);

                    //process comments
                    if (count($data['comments']) > 0) {
                        foreach ($data['comments'] as $comment) {
                            $this->get('elasticsearch')->indexComment($comment);
                        }
                    }

                    //fetch assets - triggers an asynchronous process
                    if (count($data['links']) > 0) {
                        try {
                            $this->get('retrieve_assets')->fetch($data['links']);
                        } catch (\RuntimeException $e) {
                            $warnings[] = $e->getMessage();
                        }
                    }

                } else {
                    $warnings[] = 'Unable to process any Posts';
                }

            } else {
                $warnings[] = 'Please upload XML file';
            }
        }
        return array('warnings' => $warnings, 'notices' => $notices);
    }

    /**
     * @Route("/admin/search", name="admin_search")
     *
     */
    public function dashboardSearchAction(Request $request)
    {
        return $this->redirect($this->generateUrl('listing_posts_by_query', array('query' => $request->get('query'))));
    }

    /**
     * @Route(
     *   "/admin/posts/list/query/{query}/page/{page}",
     *   name="listing_posts_by_query",
     *   requirements={"page" = "\d+"},
     *   defaults={"page" = 1}
     * )
     *
     */
    public function postListingsBySearchAction($query, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Admin:postListings', array('page' => $page), array('query' => rawurlencode($query)));
    }

    /**
     * @Route(
     *   "/admin/posts/list/tag/{tag}/page/{page}",
     *   name="listing_posts_by_tag",
     *   requirements={"page" = "\d+"},
     *   defaults={"page" = 1}
     * )
     *
     */
    public function postListingsByTagSearchAction($tag, $page)
    {
        return $this->forward('WebplumbrBlogBundle:Admin:postListings', array('page' => $page), array('tag' => $tag));
    }

    /**
     * @Route(
     *   "/admin/posts/list/{page}",
     *   name="listing_posts",
     *   requirements={"page" = "\d+"},
     *   defaults={"page" = 1}
     * )
     * @Template()
     *
     */
    public function postListingsAction($page)
    {
        $size = 10;
        $page = max($page, 1);

        //check for query parameters
        $query = $this->getRequest()->get('query');
        $tag   = $this->getRequest()->get('tag');

        if (!empty($query)) {
            $search = array(
                'multi_match' => array(
                    'query'  => rawurldecode($query),
                    'fields' => array('title^2', 'content'),
                    'type'   => 'phrase'
                ));
        } else if (!empty($tag)) {
            $search = array(
                'match' => array('tags' => $tag)
            );
        } else {
            $search = array('match_all' => array());
        }

        $posts = $this->get('elasticsearch')->getPosts($search, $page, $size);
        $uri   = $this->getRequest()->getUri();

        $templateVars = array('posts' => $posts);

        if ($page > 1) {
            $templateVars['prev_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', max($page - 1, 1), $uri) : sprintf('%s/%s', $uri, max($page - 1, 1));
        }

        if ($page < ceil($posts['total']/$size)) {
            $templateVars['next_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', $page + 1, $uri) : sprintf('%s/%s', $uri, $page + 1);
        }

        return $templateVars;
    }

    /**
     * @Route("/admin/tags/modify", name="modify_tags")
     *
     */
    public function tagModifyAction(Request $request)
    {
        if (!$request->getMethod() === 'POST') {
            return new Response('Method not allowed', 405);
        }

        $params = $request->request->all();

        switch ($params['op_type']) {
            case 'rename':
                $this->get('elasticsearch')->renameTag($params['old_tag_name'], $params['new_tag_name']);
                $message = 'Tag has been renamed';
                break;
            case 'delete':
                $this->get('elasticsearch')->deleteTag($params['old_tag_name']);
                $message = 'Tag has been deleted';
                break;
            case 'fetch':
                return $this->redirect($this->generateUrl('listing_posts_by_tag', array('tag' => $params['old_tag_name'])));
            case 'publish':
                $this->get('elasticsearch')->updatePostsStatusByTagName($params['old_tag_name'], 'publish');
                $message = 'All associated Posts have been published';
                break;
            case 'unpublish':
                $this->get('elasticsearch')->updatePostsStatusByTagName($params['old_tag_name'], 'draft');
                $message = 'All associated Posts have been unpublished';
                break;
            default:
                return new Response('Not Found', 404);
        }

        $this->get('session')->getFlashBag()->add('notice', $message);

        return $this->redirect($this->generateUrl('listing_tags'));
    }

    /**
     * @Route("/admin/tags/autocomplete", name="autocomplete_tags")
     *
     */
    public function tagAutocompleteAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            return new Response('Bad Request', 400);
        }

        $query = $request->get('term');

        $tags = $this->get('elasticsearch')->searchTags($query);

        return new JsonResponse($tags);
    }

    /**
     * @Route("/admin/tags/list", name="listing_tags")
     * @Template()
     *
     */
    public function tagListingsAction()
    {
        return array();
    }

    /**
     * @Route("/admin/posts/edit/{id}", name="edit_posts", requirements={"id" = "\d+"})
     * @Template()
     *
     */
    public function postEditAction($id)
    {
        $post = $this->get('elasticsearch')->fetchPostById($id);

        $form = $this->createForm(new PostType(), $post);

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->updatePost($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'Post has been updated');
            return $this->redirect($this->generateUrl('listing_posts'));
        } else {
            //var_dump($form->getErrors()); die();
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/admin/posts/new", name="new_post")
     * @Template()
     *
     */
    public function postNewAction()
    {
        $form = $this->createForm(new NewPostType());

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->addPost($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'Post has been added');
            return $this->redirect($this->generateUrl('listing_posts'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route(
     *   "/admin/comments/list/{page}",
     *   name="listing_comments",
     *   requirements={"page" = "\d+"},
     *   defaults={"page" = 1}
     * )
     * @Template()
     *
     */
    public function commentListingsAction($page)
    {
        $size = 10;
        $page = max($page, 1);

        $query = array('match_all' => array());
        $comments = $this->get('elasticsearch')->getComments($query, $page, $size);
        $uri   = $this->getRequest()->getUri();

        //set elapsed info
        foreach ($comments['comments'] as &$item) {
            $item['comment_date'] = $this->get('blog_helper')->getElapsedTime($item['comment_date']);
        }

        $templateVars = array('comments' => $comments);

        if ($page > 1) {
            $templateVars['prev_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', max($page - 1, 1), $uri) : sprintf('%s/%s', $uri, max($page - 1, 1));
        }

        if ($page < ceil($comments['total']/$size)) {
            $templateVars['next_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', $page + 1, $uri) : sprintf('%s/%s', $uri, $page + 1);
        }

        return $templateVars;
    }

    /**
     * @Route("/admin/comment/edit/{id}", name="edit_comment", requirements={"id" = "\d+"})
     * @Template()
     *
     */
    public function editCommentAction($id)
    {
        $comment = $this->get('elasticsearch')->fetchCommentById($id);

        $form = $this->createForm(new CommentType(), $comment);

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->updateComment($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'Comment has been updated');
            return $this->redirect($this->generateUrl('listing_comments'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/admin/comment/approve/{id}", name="approve_comment", requirements={"id" = "\d+"})
     *
     */
    public function approveCommentAction($id)
    {
        $this->get('elasticsearch')->approveComment($id);
        $this->get('session')->getFlashBag()->set('notice', 'Comment has been approved');
        return $this->redirect($this->generateUrl('listing_comments'));
    }

    /**
     * @Route("/admin/comment/mark-as-spam", name="mark_as_spam")
     *
     */
    public function markAsSpamAction()
    {
        $this->get('elasticsearch')->markUnapprovedCommentsAsSpam();
        $this->get('session')->getFlashBag()->set('notice', 'Unapproved comments have been marked as Spam');
        return $this->redirect($this->generateUrl('listing_comments'));
    }

    /**
     * @Route("/admin/spam/delete", name="delete_spam")
     *
     */
    public function deleteSpamAction()
    {
        $this->get('elasticsearch')->deleteCommentsMarkedAsSpam();
        $this->get('session')->getFlashBag()->set('notice', 'Spam comments have been deleted');
        return $this->redirect($this->generateUrl('listing_comments'));
    }

    /**
     * @Route("/admin/dashboard", name="admin_dashboard")
     * @Template()
     *
     */
    public function dashboardAction()
    {
        return array();
    }

    /**
     * @Route("/admin/settings", name="admin_settings")
     * @Template()
     *
     */
    public function settingsAction()
    {
        $meta = $this->get('elasticsearch')->getMetadata();

        $form = $this->createForm(new MetaType(), $meta);

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->updateMetadata($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'Settings has been updated');
            return $this->redirect($this->generateUrl('admin_settings'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/admin/user/edit/{id}", name="edit_user", requirements={"id" = "\d+"})
     * @Template()
     *
     */
    public function userEditAction($id)
    {
        $user = $this->get('elasticsearch')->fetchUserById($id);

        $form = $this->createForm(new UserType(), $user);

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->updateUser($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'User has been updated');
            return $this->redirect($this->generateUrl('listing_users'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route("/admin/user/new", name="new_user")
     * @Template()
     *
     */
    public function userNewAction()
    {
        $form = $this->createForm(new NewUserType());

        $form->handleRequest($this->getRequest());

        if ($form->isValid() && $this->getRequest()->getMethod() == 'POST') {
            $this->get('elasticsearch')->addUser($form->getData());
            $this->get('session')->getFlashBag()->set('notice', 'User has been added');
            return $this->redirect($this->generateUrl('listing_users'));
        }

        return array('form' => $form->createView());
    }

    /**
     * @Route(
     *   "/admin/users/list/{page}",
     *   name="listing_users",
     *   requirements={"page" = "\d+"},
     *   defaults={"page" = 1}
     * )
     * @Template()
     *
     */
    public function userListingsAction($page)
    {
        $size = 10;
        $page = max($page, 1);

        $search = array('match_all' => array());
        
        $users = $this->get('elasticsearch')->getUsers($search, $page, $size);
        $uri   = $this->getRequest()->getUri();

        $templateVars = array('users' => $users);

        if ($page > 1) {
            $templateVars['prev_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', max($page - 1, 1), $uri) : sprintf('%s/%s', $uri, max($page - 1, 1));
        }

        if ($page < ceil($users['total']/$size)) {
            $templateVars['next_uri'] = preg_match('/\d+$/', $uri) ? preg_replace('/\d+$/', $page + 1, $uri) : sprintf('%s/%s', $uri, $page + 1);
        }

        return $templateVars;
    }
}
