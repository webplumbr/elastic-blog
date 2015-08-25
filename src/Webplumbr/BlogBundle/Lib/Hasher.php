<?php
namespace Webplumbr\BlogBundle\Lib;

class Hasher
{
    protected $salt;
    protected $defaultPassword;

    public function __construct($salt, $defaultPassword)
    {
        //used to encrypt password
        $this->salt = $salt;
        //used as default password for imported users
        $this->defaultPassword = $defaultPassword;
    }

    public function getSalt()
    {
        return $this->salt;
    }

    public function getDefaultPassword()
    {
        return $this->defaultPassword;
    }

    public function getDefaultEncryptedHash()
    {
        return $this->encrypt($this->getDefaultPassword());
    }

    public function encrypt($password)
    {
        //requires PHP v5.5 or above
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public function verify($password, $hash)
    {
        //requires PHP v5.5 or above
        return password_verify($password, $hash);
    }
}