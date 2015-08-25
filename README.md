# Elasticsearch powered blog bundle for Symfony 2 Projects

**Step 1.** Add the following package to your _composer.json_

```
require {
  "webplumbr/elastic-blog": "dev-master",
}
```

```
"repositories": [
  {
    "type": "vcs",
    "url": "git@github.com:webplumbr/elastic-blog.git"
  }
]
```

**Step 2.** Run the following

```
composer update webplumbr/elastic-blog
```

**Step 3.** Register the bundle with your _app/AppKernel.php_ file

```
$bundles[] = new Webplumbr\BlogBundle\WebplumbrBlogBundle();
```

**Step 4.** Edit _app/config/config.yml_ and add the following under _assetic_

```
assetic:
    bundles:        [WebplumbrBlogBundle]
```

**Step 5.** Add the following to _app/config/parameters.yml.dist_ file

```
    elastic_host: localhost
    elastic_port: 9200
    elastic_index: your_blog_index
    # change the following
    secret: YouBetterChangeThisToSomethingElse
    # this is used as the default password for all users that you might
    # import using your existing Wordpress Blog XML
    default_user_password: '!letmein!'
```

**Step 6.** Add the following to _app/config/routing.yml_ file

```
webplumbr_blog:
    resource: "@WebplumbrBlogBundle/Controller/"
    type:     annotation
    prefix:   /
```

**Step 7.** Make sure your _app/config/security.yml_ file resembles the following:

```
# To get started with security, check out the documentation:
# http://symfony.com/doc/current/book/security.html
security:
    encoders:
      Symfony\Component\Security\Core\User\User:
        algorithm: bcrypt
        cost: 12
      Webplumbr\BlogBundle\Security\User\ElasticUser:
        algorithm: bcrypt
        cost: 12

    # http://symfony.com/doc/current/book/security.html#where-do-users-come-from-user-providers
    providers:
        chain_provider:
            chain:
                providers: [in_memory, elasticuser]
        in_memory:
            memory:
                users:
                    # generated using: https://www.dailycred.com/article/bcrypt-calculator
                    superman: { password: '$2a$12$7BhgZjRGuSueYLJy1ZNNieSHf2VDdFsvqyG3wajDu2//VSX5gIT3m', roles: 'ROLE_SUPER_ADMIN' }
        elasticuser:
            id: elastic_user_provider

    firewalls:
        # disables authentication for assets and the profiler, adapt it according to your needs
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        main:
            anonymous: ~
            # activate different ways to authenticate

            # http_basic: ~
            # http://symfony.com/doc/current/book/security.html#a-configuring-how-your-users-will-authenticate

            # http://symfony.com/doc/current/cookbook/security/form_login_setup.html
            # reference: http://stackoverflow.com/a/26614055
            form_login:
                login_path: /admin/login
                check_path: /admin/login-check
                default_target_path: /admin/dashboard
                always_use_default_target_path: true
            logout:
                path: /admin/logout
                target: /admin/login

    access_control:
        - { path: ^/admin/login, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/admin, roles: [ROLE_ADMIN, ROLE_SUPER_ADMIN] }
```
**NOTE** The default super admin uses the following credentials:

**Step 8.** Dump all newly installed assets
```
app/console assetic:dump
app/console assets:install --symlink
```

**Step 9.** You can then visit the following link and login using the default super admin user credentials:

username: superman
password: !underwear!

Login page: http://your-project-domain/admin/login

If you are on a local development box: http://your-project-name/app_dev.php/admin/login

**Step 10.** Import your Wordpress XML by visiting the "Import Wordpress Blog" link after logging in to the Admin area.

If everything goes well, you should see your wordpress blog posts, tags, comments and users successfully migrated to the ElasticSearch powered blog platform.

## FAQ
If you have any issues, make sure you have checked the following:

1. Is Elasticsearch installed and running as a service
2. Is the error related to Symfony 2 not being able to write to app/cache and/or app/logs folder? Then, clear the cache and logs folder and make sure the web browser user has appropriate rights to write cache and log files

