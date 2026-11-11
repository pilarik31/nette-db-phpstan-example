Requirements
------------

This example uses Nette 3.x and requires PHP 8.5 or newer and Composer.
The default database connection also requires PHP's `pdo_sqlite` extension.
Node.js and npm are only needed if you enable Vite.


Installation
------------

From this repository's root directory, install the locked dependencies, including
the development tools:

	composer install

Ensure the `temp/` and `log/` directories are writable.
The current `app/Bootstrap.php` explicitly enables development mode with
`setDebugMode(true)`. Configure debug mode appropriately before deploying to
production.


Database
--------

Nette Database is installed and configured in `config/common.neon`. The default
connection uses an in-memory SQLite database, whose contents disappear when the
connection closes. PHP's `pdo_sqlite` extension is required for this connection.
Change the DSN and credentials to use a persistent database.


Typed Database Model Example
----------------------------

The homepage demonstrates the model through `HomePresenter`, `UserRepository`
and the typed `HomeTemplate`:

	php -S localhost:8000 -t www

Open `http://localhost:8000/` to see the users. Click a name to view its detail
(for example, `/home/default/1`), including the annotated columns and `getLabel()`
method. A nonexistent user returns HTTP 404.

`DatabaseDemoService` creates three sample users for each request when the
configured connection is `sqlite::memory:`. It does not initialize or seed other
database connections. With a persistent database, supply the schema and data
yourself.

[`App\Database\Repository\UserRepository`](app/Database/Repository/UserRepository.php)
uses Nette Database Explorer for queries and is automatically discovered by the
`*Repository` rule in `config/services.neon`.
Inject it into a presenter or service through its constructor. Its methods return
`User`, `?User` or `list<User>`. `User` extends `ActiveRow` directly:

```php
/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $email
 */
final class User extends ActiveRow
{
	public function getLabel(): string
	{
		return $this->name . ' <' . $this->email . '>';
	}
}
```

There is no `fromRow()` or separate hydration code in each model:

```php
// $users is an injected App\Database\Repository\UserRepository.
$user = $users->findById(1);
if ($user !== null) {
	echo strtoupper($user->name); // PHPStan knows name is string
	echo $user->getLabel();      // a method on the actual User instance
	$user->update(['name' => 'Ada']);
}
```

[`App\Database\Explorer::ROW_CLASSES`](app/Database/Explorer.php) is the single
table-to-model registry:

```php
public const ROW_CLASSES = [
	'users' => User::class,
];
```

`App\Database\Explorer::createActiveRow()` constructs the mapped class whenever
Explorer loads a row, including insert refetches and relationships. Unmapped
tables use the original `ActiveRow`. The DI definition replaces `database.default.explorer`
with `App\Database\Explorer`, preserving the configured connection and autowiring.

`App\Database\Explorer::tableFor()` accepts a `class-string<T>` and returns
`Selection<T>`. Both PHPStan and Intelephense infer the row type directly from
the supplied model class:

```php
// $database is an injected App\Database\Explorer.
$users = $database->tableFor(User::class); // Selection<User>
$user = $users->get(1);                   // User|null
if ($user !== null) {
	echo $user->getLabel();
}
```

The types propagate through `get()`, `fetch()`, `fetchAll()`, query chaining and
`foreach`. `tableFor()` looks up the table in `ROW_CLASSES` and throws
`InvalidArgumentException` for an unregistered model, before querying the
database. A single `@var Selection<T>` annotation at construction connects the
checked registry entry to Nette's row factory. Callers need no type overrides.
The configured connection, conventions and cache storage are preserved.
There is no project PHPStan extension or per-table conditional return annotation.
The inherited `table('users')` still creates mapped rows at runtime, but its
declared type is the generic `Selection<ActiveRow>`.

Add another model by extending `ActiveRow`, documenting its columns with
`@property-read`, and adding its table to `ROW_CLASSES`. Use nullable types for
nullable columns. The annotations must match the schema and the database
driver's actual types.

Column reads use the inherited `ActiveRow` accessors. Values stay in `ActiveRow`,
so `update()` refreshes are visible and Nette keeps its normal column-loading
behavior. Use `update()` to write changes. Explicit `select()` still limits which
columns can be read, just as with plain `ActiveRow`.

The annotations describe column types to the IDE and PHPStan; they do not
validate or convert values at runtime. `User` contains only these declarations
and its `getLabel()` method. No additional base model class,
reflection, `unset()`, constructor or property hooks are needed.

Use property access (`$user->email`) for the declared column types;
the inherited array access and `ref()` / `related()` method signatures still
return their generic Nette types. Runtime row mapping also works for relations,
but precise static relation types require additional declarations.

No baseline or error ignores are needed.

The example schema belongs to the demo. A persistent application
database needs its own schema/migrations.


Replicating the Database Setup in Another Project
------------------------------------------------

To reproduce the typed rows and PHPStan inference in another Nette project:

1. **Install the dependencies.** This project's `composer.lock` uses
   `nette/database` 3.2.10, `phpstan/phpstan` 2.3.5 and
   `phpstan/phpstan-nette` 2.0.15. These are the reference versions for this
   example; an older Database release must support `Explorer::createActiveRow()`
   and generic `Selection` types for this approach to work. The project itself
   requires PHP 8.5+. Install the packages and the PDO driver for your database
   (`pdo_sqlite` for the demo):

   ```bash
   composer require nette/database:^3.2.10
   composer require --dev phpstan/phpstan:^2.3.5 phpstan/phpstan-nette:^2.0.15 phpstan/extension-installer:^1.4
   ```

   Allow `phpstan/extension-installer` in Composer's `config.allow-plugins` so
   the Nette extension loads automatically. Alternatively, include
   `vendor/phpstan/phpstan-nette/extension.neon` explicitly in `phpstan.neon`
   without the installer.

2. **Copy the runtime classes.** Transfer
   [`app/Database/Explorer.php`](app/Database/Explorer.php), the models in
   [`app/Database/Model/`](app/Database/Model/) and the repositories in
   [`app/Database/Repository/`](app/Database/Repository/). Adapt namespaces,
   tables, queries and column annotations to the target schema. Each model
   extends `Nette\Database\Table\ActiveRow` and declares its columns with
   `@property-read`; register every table/model pair in `Explorer::ROW_CLASSES`.
   Keep an autoload mapping such as `"App\\": "app/"` in Composer's `autoload.psr-4`.

3. **Configure the connection and replace the Explorer service.** Merge the
   `database` section from [`config/common.neon`](config/common.neon), adjusting
   the DSN and credentials, then merge this into the loaded services config:

   ```neon
   services:
       database.default.explorer:
           alteration: true
           create: App\Database\Explorer
   ```

   For a named connection, replace `default` with its configured name. Register
   repositories explicitly or copy the `*Repository` discovery rule from
   [`config/services.neon`](config/services.neon). Inject
   `App\Database\Explorer` into repositories to use `tableFor(Model::class)`.
   Connection settings and service naming follow the
   [Nette Database configuration](https://doc.nette.org/en/database/configuration);
   `alteration` modifies the service created by the framework as described in
   [Nette DI service definitions](https://doc.nette.org/en/dependency-injection/services).

4. **Use the generic model API.** Replace queries such as `table('users')` with
   `tableFor(User::class)` in repositories. Preserve the `@template T`,
   `@param class-string<T>` and `@return Selection<T>` annotations on `tableFor()`
   and its construction annotation. PHPStan and Intelephense use these to infer
   the row type without a custom analysis extension. Add the application's paths
   to `phpstan.neon` and choose an analysis level (`max` here).

5. **Provide the schema and verify the result.** Create the target database's
   schema with its own migrations, then run `vendor/bin/phpstan analyse`.
   Check that `$database->tableFor(User::class)->get($id)` is inferred as `?User`
   and that a loaded row is a `User` at runtime, with access to `getLabel()`.
   Adapt these checks to the target model. Keep repository return types such as
   `?User` and `list<User>`; preserve the `insert()` result guard from
   `UserRepository::create()`, since inserts do not always return a row.

To reproduce the sample application too, also copy
[`DatabaseDemoService`](app/Database/DatabaseDemoService.php),
[`examples/users.sql`](examples/users.sql) and the Home presenter/template files,
register the demo service (for example with the `*Service` discovery rule), and
call `initialize()` before querying. This initialization creates and seeds only
`sqlite::memory:` connections; persistent databases need their own setup.
The presenter, templates and demo seed data are optional for the typed database
integration itself.


Static Analysis
---------------

Run PHPStan with:

	composer run phpstan

The configuration in `phpstan.neon` analyses `app/`, `bin/` and
`www/index.php` at `level: max`, which always selects PHPStan's highest available
level. The installed Nette extension is loaded by `phpstan/extension-installer`.
PHPStan stores its cache in `temp/phpstan/`.

See the [PHPStan configuration reference](https://phpstan.org/config-reference).


Code Style
----------

Check the PHP files in `app/` against `phpcs.xml`:

	composer run phpcs

Apply the automatically fixable changes:

	composer run phpcbf


Asset Building with Vite
------------------------

This project supports Vite for asset building, which is recommended but optional. To activate Vite:

1. Uncomment the `type: vite` line in the `common.neon` configuration file under the assets mapping section.
2. Then set up and build the assets:

		npm install
		npm run build

Use `npm run dev` instead of `npm run build` to start the Vite development server
with hot reload.


Web Server Setup
----------------

To quickly dive in, use PHP's built-in server:

	php -S localhost:8000 -t www

Then, open `http://localhost:8000` in your browser to view the user list.

For Apache or Nginx users, configure a virtual host pointing to your project's `www/` directory.

**Important Note:** Ensure `app/`, `config/`, `log/`, and `temp/` directories are not web-accessible.
Refer to [security warning](https://nette.org/security-warning) for more details.
