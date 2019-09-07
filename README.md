# Install package

```
composer require uccello/uccello-api
```

# Config JWT

## Publish the config
Run the following command to publish the package config file:

```bash
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
```

You should now have a config/jwt.php file that allows you to configure the basics of this package.

## Generate secret key
I have included a helper command to generate a key for you:

```bash
php artisan jwt:secret
```

This will update your .env file with something like JWT_SECRET=foobar

It is the key that will be used to sign your tokens. How that happens exactly will depend on the algorithm that you choose to use.

## Update your User model
Firstly you need to implement the Tymon\JWTAuth\Contracts\JWTSubject contract on your User model, which requires that you implement the 2 methods getJWTIdentifier() and getJWTCustomClaims().

The example below should give you an idea of how this could look. Obviously you should make any changes, as necessary, to suit your own needs.

```php
<?php

namespace App;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
```

## Configure Auth guard
Note: This will only work if you are using Laravel 5.2 and above.

Inside the config/auth.php file you will need to make a few changes to configure Laravel to use the jwt guard to power your application authentication.

Make the following changes to the file:

```php
'defaults' => [
    'guard' => 'api',
    'passwords' => 'users',
],

...

'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

Here we are telling the api guard to use the jwt driver, and we are setting the api guard as the default.

We can now use Laravel's built in Auth system, with jwt-auth doing the work behind the scenes!


# Config CORS
The provided Spatie\Cors\Cors middleware must be registered in the global middleware group.

```php
// app/Http/Kernel.php

protected $middleware = [
    ...
    \Spatie\Cors\Cors::class
];
```

```bash
php artisan vendor:publish --provider="Spatie\Cors\CorsServiceProvider" --tag="config"
```

# Daily usage
You can pass several params to the request URL:

| Param | Description | Default  | Example |
|---|---|:---:|---|
| descendants |  Activate (1) or not (0) descendant view according to the user's roles.  | 0 | &descendants=1 |
| order_by | Semicolon and comma separated list of the order_by clauses.  | | &order_by=name,asc;email,desc |
| with | Semicolon separated list of the relations to add in the response. |  | &with=domain;clients |