## Integrating MongoDB into Laravel with Filament

## Introduction

_Wall of text incoming..._
_Skip to [Getting Started](#getting-started) if you want to go directly to the code_

[Laravel](https://laravel.com/) is one of the best PHP frameworks I ever tried in my career which works very well with relational databases such [MariaDB](https://mariadb.org/) or [PostgreSQL](https://www.postgresql.org/). However recently I had the opportunity to dig into NoSQL databases, specifically into [MongoDB](https://www.mongodb.com/) that offers amazing features like TTL indexes or embedded documents (aka One to Few relationships).

One of my hobbies is the homelab. I love to manage my own server and services. I have many of docker compose projects and everytime I want to test a new service it becomes a pain to manage the updates. Therefore I just started a new project trying to auto manage my docker applications in my personal server without using the CLI. I mean the CLI of docker-compose is amazing but I wanted to try out alternatives that are not so powerkill as [portainer](https://www.portainer.io/) is. Saying that it came into my mind to work on a small application using [Filament admin](https://filamentphp.com/) in order to avoid working on templates, scripts and all repetitive tasks that requires for a simple CRUD application. Filament offers a very nice admin panel that works out of the box and it is very easy to customize and integrate with Laravel.

So I decided to work on [Lunash](https://github.com/adrhem/lunash) and at the time I am writing this post I just released version 0.1 (All feedback is well received!). While I was working in Lunash I encountered many blockers integrating mongo with Laravel and Filament and this is the intention of this post. To you developers to avoid all the blockers I had and be able to create your own projects using these amazing technologies.

## Getting Started

Requirements:

-   PHP running and configured in your machine.
-   PECL. (If you have PHP installed probably you already have it).
-   Composer ([official documentation](https://getcomposer.org/doc/00-intro.md)).
-   MongoDB running in your machine or a remote instance. (I used the community edition).

Also I will assume you have basic knowledge of Laravel and MongoDB. If you don't, please check the official documentation of both technologies.

For reference I published the code on my repo:

-   https://github.com/adrhem/laravel-mongo-filament

### Laravel

First of all we need to create a new laravel project. I used the [Laravel Installer](https://laravel.com/docs/12.x/installation#creating-a-laravel-project) to create a new project:

```
laravel new <project>
```

I used the default configurations and I chose the **no starter kit** option since filament covers all the needs of this project. If the CLI asks you for a specific database, pick any option since we will remove it in following steps.

### MongoDB

Now we need to configure MongoDB as our primary database. In the [official documentation of mongodb for laravel](https://laravel.com/docs/12.x/mongodb) explains very well all the steps needed. But I will summarize them here.

```
pecl install mongodb
```

[PECL](https://pecl.php.net/) is a CLI that helps you to install PHP extensions easily and this command will install and configure the mongodb driver for your default PHP installation.

```
composer require mongodb/laravel-mongodb
```

Then using composer to manage our dependencies it will install the [official laravel mongodb package](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/) that will help us with all the driver integration.

### Laravel with Mongo

Now that we have all the required dependencies we can go into specific configurations to make laravel compatible with mongodb.

#### Updating the `.env` configuration

I removed all the MariaDB (or any other relational database) configuration from the `.env` file and added the following lines:

```
DB_CONNECTION=mongodb
MONGODB_DATABASE=db-name
MONGODB_URI="mongodb://localhost:27017/"
```

and make sure the connection `mongodb` exists in the `config/database.php` file:

```
'connections' => [
    ...
    'mongodb' => [
        'driver' => 'mongodb',
        'dsn' => env('MONGODB_URI', 'mongodb://localhost:27017'),
        'database' => env('MONGODB_DATABASE', 'db-name'),
    ],
],
```

#### Updating the migrations

We need to update the database migrations to make them fully compatible with mongodb. Remember now we are working with collections and not tables. So we need to update the `use` statement in the migration files as follows:

```
use MongoDB\Laravel\Schema\Blueprint;

Schema::create('users', function (Blueprint $collection) {
    $collection->id();
    ... // other fields
    $collection->timestamps();
});
```

If you plan to use the database session driver then update its migration as well:

```
Schema::create('sessions', function (Blueprint $collection) {
    $collection->id();
    ... // other fields
    $collection->expire('expires_at', config('session.lifetime'));
});
```

Where expire method will create a TTL index in the `expires_at` field that will automatically remove the documents after the defined time.

Now we can run the migration command and check everything ran correctly:

```
php artisan migrate
```

![Laravel migration](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/z8ltzf8tpj5e36lcd6mi.png)

#### Updating the User Model and other Models

When we created the laravel application the model app/Models/User.php file auto generated. We need to update it to make it compatible with mongodb. We just need to update the `Authenticable` dependency as follows:

```
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable
{
    ...
}
```

All other traits and dependencies can remain the same.

If you add more models remember to update the base model dependency with: `use MongoDB\Laravel\Eloquent\Model;`

#### Updating the Cache Store

By default laravel uses the database store to write the cache hits. If you want to use mongodb as your cache provider you need to do the following updates:

In the `config/cache.php` update or create a new entry for mongodb:

```
'mongodb' => [
    'driver' => 'mongodb',
    'connection' => 'mongodb',
    'collection' => 'cache',
    'lock_connection' => 'mongodb',
    'lock_collection' => 'cache_locks',
    'lock_lottery' => [2, 100],
    'lock_timeout' => 86400,
],
```

Update your `.env` file to `CACHE_STORE=mongodb` if needed.

And finally update your migration that contains the creation of the cache table:

```
<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /** @var MongoDB\Laravel\Cache\MongoStore */
        $store = Cache::store('mongodb');
        $store->createTTLIndex();
        $store->lock('')->createTTLIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /** @var MongoDB\Laravel\Cache\MongoStore */
        $store = Cache::store('mongodb');
        $store->flush();
        /** @var MongoDB\Laravel\Cache\MongoLock */
        $store
            ->restoreLock('lunash', owner: 'app')
            ->forceRelease();
    }
};
```

This migration will create and expire all documents saved in the cache collection automatically.

Since we ran previously the `php artisan migrate` you can run the command:

```
php artisan migrate:fresh
```

to drop all the collections and run all the migrations again.

### Installing Filament

Now that we have all the required configuration with mongodb we can continue installing Filament admin. You can check [the official documentation](https://filamentphp.com/docs/4.x/getting-started) for detailed instructions. But I know we just want the straightforward commands to make it work. So here we go:

```
composer require filament/filament:"^4.0"
```

This command will install the filament official package. The version 4 is the latest version for now.

```
php artisan filament:install --panels
```

It will create the default administration panels that allows you to navigate to `/admin` or any other defined route administration.

```
php artisan make:filament-user
```

And finally running this command will create an user to login into your app admin page:

![Filament default sign in page](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/sdrb7pt0d7gkj2drw4v2.png)

The admin page looks empty, right? Don't worry we will create a resource to manage users in the next section.

![Filament dashboard](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/1s0kne0ldiz38xenye8i.png)

### Creating a Filament Resource

Now that we have everything set up we can create a resource to manage users. Filament resources are very powerful and easy to create. You can check the [official documentation](https://filamentphp.com/docs/4.x/resources/overview) for more information.

```
php artisan make:filament-resource User
```

Then you will be able to see the user we created as admin with all the CRUD Operations available.

![User resource for user resource](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/iproyzpfnbu87ttc8aba.png)

And of course with `mongosh` you will be able to see the document you created:

```
db.users.find()
[
  {
    _id: ObjectId('68d2f89c9109cb649b081532'),
    name: 'Adrián HM',
    email: 'dev.foe555@slmail.me',
    password: '$2y$.....u',
    updated_at: ISODate('2025-09-23T19:50:46.440Z'),
    created_at: ISODate('2025-09-23T19:44:28.257Z')
  }
]
```

### Adding a new One to Few relationship

Now that we have everything working we can create a new model with a One to Few relationship. In MongoDB we can embed documents inside other documents. This is very useful when you have a relationship that doesn't require to be queried separately or it is not going to grow too much.

For example a user can have many addresses but an address belongs to one user. So we can embed the addresses inside the user document.

```
php artisan make:model Address
```

Do you remember that we are working with collections and not tables? So the migration is not needed. We just need to update the Address model as follows:

```
<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Address extends Model
{
    protected $fillable = [
        'street',
        'city',
        'state',
        'zip_code',
    ];
}
```

Then we need to update the User model to add the new relationship:

```
use MongoDB\Laravel\Relations\EmbedsMany;
...

protected $fillable = [
    ...
    'addresses',
];

public function addresses(): EmbedsMany
{
    return $this->embedsMany(Address::class);
}
```

Now we can update the `UserResource` to add the addresses relationship. We need to update the `app/Filament/Resources/Users/Schemas/UserForm.php` and add a new [Repeater](https://filamentphp.com/docs/4.x/forms/repeater) field as follows:

```
public static function configure(Schema $schema): Schema
{
    return $schema
        ->components([
            ...
            Repeater::make('addresses')
                ->schema([
                    TextInput::make('street')->required(),
                    TextInput::make('city')->required(),
                    TextInput::make('state')->required(),
                    TextInput::make('zip_code')->required(),
                ])
                ->columnSpanFull()
                ->columns(2)
        ]);
}
```

As you can see it is very easy to add a new embedded document with filament.

![Filament embedded addresses](https://dev-to-uploads.s3.amazonaws.com/uploads/articles/q758v4ya97wy8fg2bgjl.png)

And checking with `mongosh` we can see the new addresses embedded in the user document:

```
db.users.find()

[
  {
    _id: ObjectId('68d4699ca2f00456ca042612'),
    name: 'Adrián HM',
    email: 'dev.foe555@slmail.me',
    password: "$2y$10$.....u"And that's it, you can easily integrate mongo with laravel and filament. Just a reminder that Filament doesn't officially support MongoDB so if you plan to use it in production please test all the features you plan to use. For more information visit the official documentation of all the technologies described in this post.

Thanks for reading! And if you have any questions or feedback please let me know.,
    updated_at: ISODate('2025-09-24T22:14:49.202Z'),
    created_at: ISODate('2025-09-24T21:58:52.165Z'),
    addresses: [
      {
        street: 'En algún lugar de la mancha',
        city: 'De cuyo nombre',
        state: 'No quiero acordarme',
        zip_code: '12345'
      }
    ]
  }
]
```

## Conclusion

And that's it, you can easily integrate mongo with laravel and filament. Just a reminder that Filament doesn't officially support MongoDB so if you plan to use it in production please test all the features you plan to use. For more information visit the official documentation of all the technologies described in this post.

Thanks for reading! And if you have any questions or feedback please let me know.
