# Elasticsearch powered blog bundle for Symfony 2 Projects

If you are thinking of migrating your Wordpress blog or even starting a blogging platform to something that
uses No-SQL, then give this bundle a try. It uses ElasticSearch for full-text search capabilities and stores your
blog posts and associated users, tags, comments as JSON documents within ElasticSearch.

Before you jump in, please read the following:

## To do
1. Presently does not preserve your Wordpress categories and pages (what this means: your wordpress categories and pages can't be imported)
2. Password change functionality for users
3. Write PHPunit test cases

## Requirements
1. PHP version 5.5 or above
2. Symfony 2.3 LTS or above with its default set of vendor bundles (out of box)
3. [ElasticSearch](https://www.elastic.co/downloads/elasticsearch) version >= 1.0

## Demo
[Blog migrated from Wordpress](http://prophecy.webplumbr.com/)


## Installation & Configuration

**Step 1.** Add the following package to your _composer.json_

```
require {
  "webplumbr/elastic-blog": "v0.6"
}
```

**Step 2.** Run the following to install the package and its dependencies

```
composer update
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

**NOTE** Remember to change the _default_user_password_ and _secret_ parameters to suit yours.

Run the following to grab the above parameters

```
composer install
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

**Step 8.** Dump all newly installed assets
```
app/console assetic:dump
app/console assets:install --symlink
```

**Step 9.** Add the following to the file _/etc/elasticsearch/elasticsearch.yml_

```
http.max_initial_line_length: 1mb
http.max_content_length: 10mb
```

**Step 10.** The default super admin user credentials to login the first time unless you have modified the user credentials in
_app/config/security.yml_ to something else.

```
username: superman
password: !underwear!
```

**Step 11.** You can try the following to print the available routes offered by this bundle

```
app/console router:debug
```

**Step 12.** Import your Wordpress XML by visiting the "Import Wordpress Blog" link after logging in to the Admin area.

If everything goes well, you should see your wordpress blog posts, tags, comments and users successfully migrated to the ElasticSearch powered blog platform.

## FAQ
If you have any issues, make sure you have checked the following:

1. Is Elasticsearch installed and running as a Service?
2. Does Symfony 2 have required permissions to write to app/cache and/or app/logs folders?
3. Have you cleared Symfony 2 cache folder?
4. Have other installation dependencies outside of this bundle been met with?
5. If you get "no matching package found" error when using _composer_ _update_, then change your Project root level _composer.json_ 's minimum stability to "dev"
6. If you get "No alive nodes found" or "Empty server" exceptions, then make sure you made changes as per Step 9.
