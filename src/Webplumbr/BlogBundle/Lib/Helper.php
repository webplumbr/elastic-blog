<?php
namespace Webplumbr\BlogBundle\Lib;

class Helper
{
    protected $hasher;

    public function setHasher(Hasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function wordpressXMLToArrayConverter($pathToXML)
    {
        $xml = simplexml_load_file($pathToXML);
        $ns  = $xml->getNamespaces(true);
        //ns usually contains four namespaces (wp, dc, content and excerpt)

        $posts = $comments = $users = $meta = array();

        if (isset($xml->channel) && count($xml->channel->item) > 0) {

            //extract meta data
            $meta['title']    = (string) $xml->channel->title;
            $meta['subtitle'] = (string) $xml->channel->description;
            $meta['url']      = (string) $xml->channel->children($ns['wp'])->base_blog_url;

            //iterate and extract users info
            foreach ($xml->channel->children($ns['wp'])->author as $author) {
                $users[] = array(
                    'user_id'      => (integer) $author->author_id,
                    'email'        => (string) $author->author_email,
                    'user_name'    => (string) $author->author_login,
                    'display_name' => (string) $author->author_display_name,
                    //initialize the imported users with a default password
                    'password'     => $this->hasher->getDefaultEncryptedHash(),
                    'status'       => 'active' //active or inactive
                );
            }

            foreach ($xml->channel->item as $node) {

                if ((string) $node->children($ns['wp'])->post_type === 'post') {

                    //to fix badly formed datetime
                    $postDate   = (string) $node->children($ns['wp'])->post_date_gmt;
                    $updateDate = (string) $node->children($ns['wp'])->post_date_gmt;

                    $postDate   = preg_match('/0000-00-00\s+00:00:00/', $postDate) ? '1970-01-01 00:00:00' : $postDate;
                    $updateDate = preg_match('/0000-00-00\s+00:00:00/', $updateDate) ? '1970-01-01 00:00:00' : $updateDate;

                    $post = array(
                        'title'          => (string) $node->title,
                        'post_date'      => $postDate,
                        'content'        => (string) $node->children($ns['content'])->encoded,
                        'post_id'        => (integer) $node->children($ns['wp'])->post_id,
                        'comment_status' => (string) $node->children($ns['wp'])->comment_status, //open or closed
                        'status'         => (string) $node->children($ns['wp'])->status, //publish or draft
                        'update_date'    => $updateDate,
                        'user_id'        => (integer) $this->getUserIdForUsername( (string) $node->children($ns['dc'])->creator , $users)
                    );

                    //collect tags
                    $tags = array();
                    foreach ($node->category as $cat) {
                        if ((string) $cat->attributes()->domain === 'post_tag') {
                            //turn spaces to hyphens
                            $tags[] = str_replace(' ', '-', (string) $cat->attributes()->nicename);
                        }
                    }

                    $post['tags'] = $tags;

                    $posts[] = $post;

                    //extract comments
                    foreach ($node->children($ns['wp'])->comment as $obj) {
                        $commenter = (string) $obj->comment_author;
                        $comment = array(
                            'commenter'    => empty($commenter) ? strtok((string) $obj->comment_email, '@') : $commenter,
                            'content'      => (string) $obj->comment_content,
                            'ip'           => (string) $obj->comment_author_IP,
                            'comment_date' => (string) $obj->comment_date_gmt,
                            'parent_id'    => (integer) $obj->comment_parent,
                            'comment_id'   => (integer) $obj->comment_id,
                            'post_id'      => (integer) $node->children($ns['wp'])->post_id,
                            //status types: approved, unapproved, spam
                            'status'       => (boolean) $obj->comment_approved ? 'approved' : 'unapproved'
                        );

                        $comments[] = $comment;
                    }
                }
            }
        }

        unset($xml); //clear memory

        return array('posts' => $posts, 'comments' => $comments, 'users' => $users, 'meta' => $meta);
    }

    public function getElapsedTime($timeOfInterest)
    {
        $interested = new \DateTime($timeOfInterest, new \DateTimeZone('UTC'));
        $now        = new \DateTime('now', new \DateTimeZone('UTC'));
        $diff       = $now->diff($interested);

        $text = '';
        if ($diff->y > 0) {
            $text .= sprintf('%s year%s ', $diff->y, $diff->y > 1 ? 's' : '');
        }

        if ($diff->m > 0) {
            $text .= sprintf('%s month%s ', $diff->m, $diff->m > 1 ? 's' : '');
        }

        if ($diff->d > 0 && $diff->m == 0 && $diff->y == 0) {
            $text .= sprintf('%s day%s ', $diff->d, $diff->d > 1 ? 's' : '');
        }

        if ($diff->h > 0 && $diff->m == 0 && $diff->y == 0) {
            $text .= sprintf('%s hour%s ', $diff->h, $diff->h > 1 ? 's' : '');
        }

        if ($diff->i > 0 && $diff->m == 0 && $diff->y == 0 && $diff->d == 0) {
            $text .= sprintf('%s minute%s ', $diff->i, $diff->i > 1 ? 's' : '');
        }

        if (!empty($text)) {
            $text .= 'ago';
        } else {
            $text = 'few seconds ago';
        }

        return $text;
    }

    private function getUserIdForUsername($username, array $users)
    {
        foreach ($users as $user) {
            if ($user['user_name'] === $username) {
                return $user['user_id'];
            }
        }

        return null;
    }
}