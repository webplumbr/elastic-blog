<?php
namespace Webplumbr\BlogBundle\Lib;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;

/**
 * Class ElasticSearch
 * A wrapper for Elasticsearch Client
 *
 * @package Webplumbr\BlogBundle\Lib
 */
class ElasticSearch
{
    protected $client;
    protected $index;
    protected $type;
    protected $hasher;

    const TYPE_POST    = 'posts';
    const TYPE_COMMENT = 'comments';
    const TYPE_META    = 'meta';
    const TYPE_USER    = 'users';

    public function __construct(array $params)
    {
        $this->client = ClientBuilder::create()
                            ->setHosts(array(
                                sprintf('%s:%s', $params['host'], $params['port'])
                            ))
                            ->setConnectionPool($params['connectionPoolClass'], $params)
                            ->build();
    }

    public function setIndex($indexName)
    {
        $this->index = $indexName;
    }

    public function setHasher(Hasher $hasher)
    {
        $this->hasher = $hasher;
    }

    public function getHasher()
    {
        return $this->hasher;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function getClient()
    {
        return $this->client;
    }

    public function indexExists()
    {
        return $this->client->indices()->exists(array(
            'index' => $this->getIndex()
        ));
    }

    public function deleteIndex()
    {
        return $this->getClient()->indices()->delete(array('index' => $this->getIndex()));
    }

    public function createIndex()
    {
        return $this->getClient()->indices()->create(array(
            'index' => $this->getIndex(),
            'body' => array(
                'settings' => array(
                    //considering the fact we index less number of blog posts
                    'number_of_shards'   => 1,
                    //@todo - more than one replica specification fails - to be fixed
                    'number_of_replicas' => 1
                ),
                'mappings' => array(
                    self::TYPE_POST => array(
                        'properties' => array(
                            'post_id'        => array('type' => 'integer'),
                            'user_id'        => array('type' => 'integer'),
                            'title'          => array('type' => 'string'),
                            'content'        => array('type' => 'string'),
                            'post_date'      => array(
                                'type'   => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss'
                            ),
                            'update_date'    => array(
                                'type'   => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss'
                            ),
                            'comment_status' => array('type' => 'string'),
                            'tags'           => array('type' => 'string', 'index' => 'not_analyzed')
                        )
                    ),
                    self::TYPE_COMMENT => array(
                        'properties' => array(
                            'comment_id'   => array('type' => 'integer'),
                            'commenter'    => array('type' => 'string'),
                            'content'      => array('type' => 'string'),
                            'ip'           => array('type' => 'string'),
                            'comment_date' => array(
                                'type'   => 'date',
                                'format' => 'yyyy-MM-dd HH:mm:ss'
                            ),
                            'status'       => array('type' => 'string'),
                            'post_id'      => array('type' => 'integer'),
                            'parent_id'    => array('type' => 'integer')
                        )
                    ),
                    self::TYPE_META => array(
                        'properties' => array(
                            'title'    => array('type' => 'string'),
                            'subtitle' => array('type' => 'string'),
                            'url'      => array('type' => 'string')
                        )
                    ),
                    self::TYPE_USER => array(
                        'properties' => array(
                            'user_id'      => array('type' => 'integer'),
                            'user_name'    => array('type' => 'string', 'index' => 'not_analyzed'),
                            'display_name' => array('type' => 'string'),
                            'email'        => array('type' => 'string', 'index' => 'not_analyzed'),
                            'password'     => array('type' => 'string', 'index' => 'not_analyzed'),
                            'status'       => array('type' => 'string')
                        )
                    ),
                ),
                //refer: https://www.found.no/foundation/text-analysis-part-1/
                'analysis' => array(
                    'analyzer' => array(
                            'default' => array(
                                'type' => 'custom',
                                'char_filter' => array('html_strip'),
                                'tokenizer' => 'standard',
                                'filter' => array('lowercase', 'stop', 'snowball')
                            )
                        )
                )
            )
        ));
    }

    public function indexMetadata(array $meta)
    {
        return $this->getClient()->index(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_META,
            'body'  => $meta
        ));
    }

    public function indexPost(array $post)
    {
        return $this->getClient()->index(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => $post
        ));
    }

    public function indexComment(array $comment)
    {
        return $this->getClient()->index(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_COMMENT,
            'body'  => $comment
        ));
    }

    public function indexUser(array $user)
    {
        return $this->getClient()->index(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_USER,
            'body'  => $user
        ));
    }

    public function getMetadata()
    {
        $result = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_META,
            'body'  => array(
                'query' => array('match_all' => array())
            )
        ));

        if (!isset($result['hits']['hits'][0])) {
            return array();
        }

        $item = $result['hits']['hits'][0]['_source'];
        //pass on elasticsearch Id
        $item['id']   = $result['hits']['hits'][0]['_id'];

        return $item;
    }

    public function updateMetadata(array $meta)
    {
        //grab the elasticsearch Id of the document to be updated
        $id = $meta['id'];
        //unset the field
        unset($meta['id']);

        return $this->getClient()->update(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_META,
            'id'    => $id,
            'body'  => array('doc' => $meta),
        ));
    }

    public function getComments(array $query, $page=1, $size=10, array $filter=array())
    {
        $params = array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_COMMENT,
            'body'  => array(
                'sort'  => array('comment_date' => array('order' => 'desc')),
                'from'  => ($page - 1) * $size,
                'size'  => $size
            )
        );

        if (count($filter)) {
            $params['body']['query'] = array(
                'filtered' => array(
                    'filter' => $filter,
                    'query'  => $query
                ));
        } else {
            $params['body']['query'] = $query;
        }

        $comments = $this->getClient()->search($params);

        $list = array('total' => $comments['hits']['total'], 'comments' => array());

        if (count($comments['hits']['hits']) == 0) {
            return $list;
        }

        foreach ($comments['hits']['hits'] as $comment) {
            $list['comments'][] = array(
                'comment_id'     => $comment['_source']['comment_id'],
                'commenter'      => $comment['_source']['commenter'],
                'content'        => $comment['_source']['content'],
                'ip'             => $comment['_source']['ip'],
                'comment_date'   => $comment['_source']['comment_date'],
                'status'         => $comment['_source']['status'],
                'post_id'        => $comment['_source']['post_id'],
                'parent_id'      => $comment['_source']['parent_id']
            );
        }

        return $list;
    }

    public function getPublishedPosts(array $query, $page=1, $size=10)
    {
        $filter = array('term' => array('status' => 'publish'));
        return $this->getPosts($query, $page, $size, $filter);
    }

    public function getPosts(array $query, $page=1, $size=10, array $filter=array())
    {
        $params = array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => array(
                'sort'  => array('post_date' => array('order' => 'desc')),
                'from'  => ($page - 1) * $size,
                'size'  => $size
            )
        );

        if (count($filter)) {
            $params['body']['query'] = array(
                'filtered' => array(
                    'filter' => $filter,
                    'query'  => $query
                ));
        } else {
            $params['body']['query'] = $query;
        }

        $posts = $this->getClient()->search($params);

        $list = array('total' => $posts['hits']['total'], 'posts' => array());

        if (count($posts['hits']['hits']) == 0) {
            return $list;
        }

        foreach ($posts['hits']['hits'] as $post) {
            $list['posts'][] = array(
                'post_id'        => $post['_source']['post_id'],
                'title'          => $post['_source']['title'],
                'content'        => $post['_source']['content'],
                'tags'           => $post['_source']['tags'],
                'post_date'      => $post['_source']['post_date'],
                'update_date'    => $post['_source']['update_date'],
                'comment_status' => $post['_source']['comment_status'],
                'status'         => $post['_source']['status']
            );
        }

        return $list;
    }

    public function searchTags($term)
    {
        $results = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => array(
                'query' => array('wildcard' => array('tags' => sprintf('%s*', $term))),
                'size'  => 10
            )
        ));

        if (count($results['hits']['hits']) == 0) {
            return array();
        }

        $tags = array();
        foreach ($results['hits']['hits'] as $hit) {
            $tags = array_merge($tags, $hit['_source']['tags']);
        }

        $tags = array_unique($tags);

        //apply closest match
        $match = array_filter(
                    array_map(
                        function ($val) use ($term) {
                            return stristr($val, $term) ? $val : false;
                        },
                        $tags
                    ));

        return $match;
    }

    public function getTagCollection()
    {
        $stats = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => array(
                'aggs' => array(
                    'tags' => array(
                        'terms' => array(
                            'field'         => 'tags',
                            'min_doc_count' => 2,
                            'size'          => 50
                        )
                    )
                )
            )
        ));

        if (count($stats['aggregations']['tags']['buckets'])) {
            $list = array();
            foreach ($stats['aggregations']['tags']['buckets'] as $bucket) {
                $percent = round($bucket['doc_count']/$stats['hits']['total']  * 100);
                $list[] = array('name' => $bucket['key'], 'size' => ($percent < 1) ? 1 : ($percent/10) + 1);
            }

            return $list;
        } else {
            return array();
        }
    }

    public function getRelatedPosts(array $post)
    {
        $resp = $this->getClient()->indices()->analyze(
            array(
                'index' => $this->getIndex(),
                'analyzer' => 'standard',
                'text'  => $post['content'],
                'tokenizer' => 'standard',
                //@todo read more about filters and use appropriate ones
                'filters' => array('lowercase', 'html_strip')
            )
        );

        $tokens = array();
        foreach ($resp['tokens'] as $val) {
            //ignore words less than 6 chars
            if (strlen($val['token']) > 6) {
                $tokens[] = $val['token'];
            }
        }

        //group tokens by count
        $list = array();
        foreach ($tokens as $token) {
            if (isset($list[$token])) {
                $list[$token]++;
            } else {
                $list[$token] = 1;
            }
        }

        //sort by the most used words (descending order)
        arsort($list, SORT_NUMERIC);

        //and then slice the top 30 terms
        $tokens = array_slice(array_keys($list), 0, 30);

        //build should array
        $should = array(
            array_map(
                function ($val) {
                    return array(
                        'term' => array('content' => $val)
                    );
                }, $tokens
            )
        );

        $page = 1;
        $size = 5;
        $query = array(
            'bool' => array(
                'must_not' => array('term' => array('post_id' => $post['post_id'])),
                'should'   => $should[0],
                'minimum_should_match' => '20%',
                )
            );

        $filter = array();
        //set tag filter to increase similarity
        if (count($post['tags'])) {
            $filter = array('or' => array());

            foreach ($post['tags'] as $tag) {
                $filter['or'][] = array('term' => array('tags' => $tag));
            }
        };

        return $this->getPublishedPosts($query, $page, $size, $filter);
    }

    public function getPostsByMonth()
    {
        $stats = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => array(
                'aggs' => array(
                    'posts_by_month' => array(
                        'date_histogram' => array(
                            'field'    => 'post_date',
                            'interval' =>  'month',
                            'format'   => 'yyyy-M',
                            'order'    => array('_key' => 'desc')
                        )
                    )
                )
            )
        ));

        if (count($stats['aggregations']['posts_by_month']['buckets'])) {
            $list = array();
            foreach ($stats['aggregations']['posts_by_month']['buckets'] as $bucket) {
                if (preg_match('/(\d{4})-(\d{2})/', $bucket['key_as_string'], $match)) {
                    $list[] = array('year' => $match[1], 'month' => $match[2], 'month_name' => $this->getMonthName($match[2]), 'count' => $bucket['doc_count']);
                }
            }
            return $list;
        } else {
            return array();
        }
    }

    /**
     * If you run in to Elasticsearch "No alive nodes found" or "Empty reply from Server"
     * It is possible that Lucene has thrown
     * TooLongFrameException: An HTTP line is larger than 4096 bytes
     *
     * To fix the above issue:
     * Edit /etc/elasticsearch/elasticsearch.yml file
     *
     * and set the following:
     * http.max_initial_line_length: 1mb
     * http.max_content_length: 10mb
     *
     * Ramp up the above value - if required
     *
     * @param $id
     * @return array
     */
    public function fetchPostById($id)
    {
        $result = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'body'  => array(
                'query' => array('match' => array('post_id' => $id))
            )
        ));

        if (!isset($result['hits']['hits'][0])) {
            return array();
        }

        $item = $result['hits']['hits'][0]['_source'];
        //flatten tags
        $item['tags'] = implode(',', $item['tags']);
        //pass on elasticsearch Id
        $item['id']   = $result['hits']['hits'][0]['_id'];

        //unset fields that won~t be shown on Form
        unset($item['update_date']);
        unset($item['user_id']);

        return $item;
    }

    public function updatePost(array $post)
    {
        //grab the elasticsearch Id
        $id = $post['id'];
        //unset the field
        unset($post['id']);

        //set the update time in terms of GMT
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $post['update_date'] = $dateTime->format('Y-m-d H:i:s');
        //explode tags
        $post['tags'] = array_map(function ($val) {
                            return preg_replace('/\s+/', '-', trim($val));
                        }, explode(',', $post['tags']));

        return $this->getClient()->update(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_POST,
            'id'    => $id,
            'body'  => array('doc' => $post),
        ));
    }

    public function addPost(array $post)
    {
        //set the update time in terms of GMT
        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));
        $post['post_date']   = $dateTime->format('Y-m-d H:i:s');
        $post['update_date'] = $dateTime->format('Y-m-d H:i:s');
        //explode tags
        $post['tags'] = array_map(function ($val) {
            return preg_replace('/\s+/', '-', trim($val));
        }, explode(',', $post['tags']));

        $post['post_id'] = $this->getNextInsertIdForPost();

        return $this->indexPost($post);
    }

    public function addUser(array $user)
    {
        $user['user_id']  = $this->getNextInsertIdForUser();
        $user['password'] = $this->getHasher()->encrypt($user['password']);

        return $this->indexUser($user);
    }

    public function updateUser(array $user)
    {
        //grab the elasticsearch Id
        $id = $user['id'];
        //unset the field
        unset($user['id']);

        return $this->getClient()->update(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_USER,
            'id'    => $id,
            'body'  => array('doc' => $user),
        ));
    }

    public function renameTag($old, $new)
    {
        if ((strtolower($old) === strtolower($new))
          || empty($old)) {
            return;
        }

        //iterate through search results and rename matching document~s tag
        $results = $this->getClient()->search(array(
            'search_type' => 'scan',
            'scroll'      => '30s', //a wait of 30 seconds between each scroll request
            'size'        => 50,
            'index'       => $this->getIndex(),
            'type'        => self::TYPE_POST,
            'body'        => array(
                'query' => array(
                    'match' => array(
                        'tags' => $old
                    )
                )
            )
        ));

        //get scrolling identifier
        $scrollId = $results['_scroll_id'];

        //collect document ids and matching tags as key=value pairs
        $ids = array();

        while(true) {

            try {
                $resp = $this->getClient()->scroll(
                    array(
                        'scroll_id' => $scrollId,
                        'scroll'    => '30s'
                    ));
            } catch (Missing404Exception $e) {
                break;
            }

            if (count($resp['hits']['hits']) > 0) {
                //update tag info
                foreach ($resp['hits']['hits'] as $hit) {

                    $tags = array_map(function ($val) use ($old, $new) {
                        return strtolower($val) === strtolower($old) ? $new : $val;
                    }, $hit['_source']['tags']);

                    //remove empty values - if any
                    $tags = array_filter($tags);

                    $ids[$hit['_id']] = $tags;

                }
            } else {
                //when there are no results - break out of the loop
                break;
            }
        }

        //update the tags
        foreach ($ids as $id => $val) {
            $this->getClient()->update(array(
                'index' => $this->getIndex(),
                'type'  => self::TYPE_POST,
                'id'    => $id,
                //for some reason, ElasticSearch does not like array keys that are non-sequential
                //hence this array_values approach to ensure the elements have ordered keys
                'body'  => array('doc' => array('tags' => array_values($val)))
            ));
        }
    }

    public function deleteTag($name)
    {
        return $this->renameTag($name, '');
    }

    public function getCommentsByPostId($postId, $page=1, $size=50)
    {
        $query = array(
            'match' => array('post_id' => $postId)
        );

        $filter = array('term' => array('status' => 'approved'));

        return $this->getComments($query, $page, $size, $filter);
    }

    public function updatePostsStatusByTagName($name, $status)
    {
        if (empty($name)) {
            return;
        }

        if (!in_array($status, array('draft', 'publish'))) {
            return;
        }

        //fetching Posts matching the specified tag
        //iterate through search results and then unPublish them
        $results = $this->getClient()->search(array(
            'search_type' => 'scan',
            'scroll'      => '30s', //a wait of 30 seconds between each scroll request
            'size'        => 50,
            'index'       => $this->getIndex(),
            'type'        => self::TYPE_POST,
            'body'        => array(
                'query' => array(
                    'match' => array(
                        'tags' => $name
                    )
                )
            )
        ));

        //get scrolling identifier
        $scrollId = $results['_scroll_id'];

        //collect matching document ids
        $ids = array();

        while(true) {

            try {
                $resp = $this->getClient()->scroll(
                    array(
                        'scroll_id' => $scrollId,
                        'scroll'    => '30s'
                    ));
            } catch (Missing404Exception $e) {
                break;
            }

            if (count($resp['hits']['hits']) > 0) {
                //update tag info
                foreach ($resp['hits']['hits'] as $hit) {
                    $ids[] = $hit['_id'];
                }
            } else {
                //when there are no results - break out of the loop
                break;
            }
        }

        //update the tags
        foreach ($ids as $id) {
            $this->getClient()->update(array(
                'index' => $this->getIndex(),
                'type'  => self::TYPE_POST,
                'id'    => $id,
                //for some reason, ElasticSearch does not like array keys that are non-sequential
                //hence this array_values approach to ensure the elements have ordered keys
                'body'  => array('doc' => array('status' => $status))
            ));
        }
    }

    public function fetchCommentById($id)
    {
        $result = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_COMMENT,
            'body'  => array(
                'query' => array('match' => array('comment_id' => $id))
            )
        ));

        if (!isset($result['hits']['hits'][0])) {
            return array();
        }

        $item = $result['hits']['hits'][0]['_source'];
        //pass on elasticsearch Id
        $item['id']   = $result['hits']['hits'][0]['_id'];

        return $item;
    }

    public function approveComment($commentId)
    {
        $comment = $this->fetchCommentById($commentId);
        $comment['status'] = 'approved';
        return $this->updateComment($comment);
    }

    public function updateComment(array $comment)
    {
        //grab the elasticsearch Id
        $id = $comment['id'];
        //unset the field
        unset($comment['id']);

        return $this->getClient()->update(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_COMMENT,
            'id'    => $id,
            'body'  => array('doc' => $comment),
        ));
    }

    public function addComment(array $comment)
    {
        //simple spam filter
        $status = 'unapproved';
        if (preg_match_all('#(https?://\w+)#', $comment['content'], $match)) {
            if (isset($match[1]) && count($match[1]) >= 2) {
                //if more than or two hyperlinks have been provided then classify it as *potential* SPAM
                $status = 'spam';
            }
        }

        $dateTime = new \DateTime('now', new \DateTimeZone('UTC'));

        //add other information
        $comment = array_merge($comment, array(
            'comment_date' => $dateTime->format('Y-m-d H:i:s'),
            'status'       => $status,
            'parent_id'    => 0,
            'comment_id'   => $this->getNextInsertIdForComment()
        ));
        return $this->indexComment($comment);
    }

    public function getDashboardStatistics()
    {
        return array();
    }

    public function getUsers(array $query, $page=1, $size=10, array $filter=array())
    {
        $params = array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_USER,
            'body'  => array(
                'from'  => ($page - 1) * $size,
                'size'  => $size
            )
        );

        if (count($filter)) {
            $params['body']['query'] = array(
                'filtered' => array(
                    'filter' => $filter,
                    'query'  => $query
                ));
        } else {
            $params['body']['query'] = $query;
        }

        $users = $this->getClient()->search($params);

        $list = array('total' => $users['hits']['total'], 'users' => array());

        if (count($users['hits']['hits']) == 0) {
            return $list;
        }

        foreach ($users['hits']['hits'] as $user) {
            $list['users'][] = array(
                'user_id'        => $user['_source']['user_id'],
                'user_name'      => $user['_source']['user_name'],
                'display_name'   => $user['_source']['display_name'],
                'email'          => $user['_source']['email'],
                'status'         => $user['_source']['status']
            );
        }

        return $list;
    }

    public function fetchUserById($id)
    {
        $result = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_USER,
            'body'  => array(
                'query' => array('match' => array('user_id' => $id))
            )
        ));

        if (!isset($result['hits']['hits'][0])) {
            return array();
        }

        $item = $result['hits']['hits'][0]['_source'];
        //pass on elasticsearch Id
        $item['id']   = $result['hits']['hits'][0]['_id'];
        //drop the following field
        unset($item['password']);

        return $item;
    }

    public function fetchUserByUsername($name)
    {
        $result = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => self::TYPE_USER,
            'body'  => array(
                'query' => array(
                    'filtered' => array(
                        'filter' => array('term'  => array('status' => 'active')),
                        'query'  => array('match' => array('user_name' => $name))
                    )
                )
            )
        ));

        if (!isset($result['hits']['hits'][0])) {
            return array();
        }

        $item = $result['hits']['hits'][0]['_source'];
        //pass on elasticsearch Id
        $item['id']   = $result['hits']['hits'][0]['_id'];

        return $item;
    }

    public function deleteCommentsMarkedAsSpam()
    {
        //iterate through search results and delete matching spam comments
        $results = $this->getClient()->search(array(
            'search_type' => 'scan',
            'scroll'      => '30s', //a wait of 30 seconds between each scroll request
            'size'        => 50,
            'index'       => $this->getIndex(),
            'type'        => self::TYPE_COMMENT,
            'body'        => array(
                'query' => array(
                    'match' => array(
                        'status' => 'spam'
                    )
                )
            )
        ));

        //get scrolling identifier
        $scrollId = $results['_scroll_id'];

        //collect document ids and matching tags as key=value pairs
        $ids = array();

        while(true) {

            try {
                $resp = $this->getClient()->scroll(
                    array(
                        'scroll_id' => $scrollId,
                        'scroll'    => '30s'
                    ));
            } catch (Missing404Exception $e) {
                break;
            }

            if (count($resp['hits']['hits']) > 0) {
                //update tag info
                foreach ($resp['hits']['hits'] as $hit) {
                    $ids[] = $hit['_id'];
                }
            } else {
                //when there are no results - break out of the loop
                break;
            }
        }

        //delete documents
        foreach ($ids as $id) {
            $this->getClient()->delete(array(
                'index' => $this->getIndex(),
                'type'  => self::TYPE_COMMENT,
                'id'    => $id
            ));
        }
    }

    private function getMonthName($key)
    {
        //if your language is other than English - change this array
        $months = array(
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        );

        return isset($months[$key]) ? $months[$key] : null;
    }

    private function getNextInsertId($type, $field)
    {
        $stats = $this->getClient()->search(array(
            'index' => $this->getIndex(),
            'type'  => $type,
            'body'  => array(
                'aggs' => array(
                    'max_id' => array(
                        'max' => array('field' => $field)
                    )
                )
            )
        ));

        return (integer) $stats['aggregations']['max_id']['value'] + 1;
    }

    private function getNextInsertIdForPost()
    {
        return $this->getNextInsertId(self::TYPE_POST, 'post_id');
    }

    private function getNextInsertIdForComment()
    {
        return $this->getNextInsertId(self::TYPE_COMMENT, 'comment_id');
    }

    private function getNextInsertIdForUser()
    {
        return $this->getNextInsertId(self::TYPE_USER, 'user_id');
    }
}