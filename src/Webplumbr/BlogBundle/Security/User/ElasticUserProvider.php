<?php

namespace Webplumbr\BlogBundle\Security\User;

use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Webplumbr\BlogBundle\Lib\ElasticSearch;
use Webplumbr\BlogBundle\Security\User\ElasticUser;

class ElasticUserProvider implements UserProviderInterface
{
    protected $search;

    public function __construct(ElasticSearch $search)
    {
        $this->search = $search;
    }

    public function loadUserByUsername($username)
    {
        $userData = $this->search->fetchUserByUsername($username);

        if ($userData) {
            return new ElasticUser(
                            $userData['user_name'],
                            $userData['password'],
                            $this->search->getHasher()->getSalt(),
                            array('ROLE_ADMIN')
            );
        }

        throw new UsernameNotFoundException(
            sprintf('Username "%s" does not exist.', $username)
        );
    }

    public function refreshUser(UserInterface $user)
    {
        if (!$user instanceof ElasticUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    public function supportsClass($class)
    {
        return $class === 'Webplumbr\BlogBundle\Security\User\ElasticUser';
    }
}