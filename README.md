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

The project PHPStan extension
[`ExplorerReturnTypeExtension`](phpstan/ExplorerReturnTypeExtension.php) reads the
same registry and infers `Selection<User>` from
`App\Database\Explorer::table('users')`. The types propagate through
`get()`, `fetch()`, `fetchAll()`, query chaining and `foreach`. An unknown table
name remains `Selection<ActiveRow>`. Inject `App\Database\Explorer` when you want
this inference; a variable declared only as the parent `Nette\Database\Explorer`
does not carry the custom mapping type information.

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

No baseline, error ignores or inline `@var` overrides are needed.

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
   `App\Database\Explorer` into repositories so PHPStan can apply the mapping.
   Connection settings and service naming follow the
   [Nette Database configuration](https://doc.nette.org/en/database/configuration);
   `alteration` modifies the service created by the framework as described in
   [Nette DI service definitions](https://doc.nette.org/en/dependency-injection/services).

4. **Wire up the project PHPStan extension.** Copy
   [`phpstan/ExplorerReturnTypeExtension.php`](phpstan/ExplorerReturnTypeExtension.php)
   and [`phpstan/extension.neon`](phpstan/extension.neon). If namespaces change,
   update both the extension's Explorer import and its service registration.
   Merge this development autoload mapping into `composer.json`:

   ```json
   {
       "autoload-dev": {
           "psr-4": {
               "App\\PHPStan\\": "phpstan/"
           }
       }
   }
   ```

   Include `phpstan/extension.neon` in the target `phpstan.neon`, add `phpstan/`
   to the analysed paths, and choose the application's paths and analysis level
   (`max` here). Run `composer dump-autoload` after merging the mappings.
   Both runtime row creation and static inference must use the same
   `Explorer::ROW_CLASSES` registry. Inference requires a literal table name
   (or a union of known literal strings); an arbitrary `string` cannot identify
   a specific model.

5. **Provide the schema and verify the result.** Create the target database's
   schema with its own migrations, then run `vendor/bin/phpstan analyse`.
   Check that `$database->table('users')->get($id)` is inferred as `?User`
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

The configuration in `phpstan.neon` analyses `app/`, `bin/`, `phpstan/` and
`www/index.php` at `level: max`, which always selects PHPStan's highest available
level. The installed Nette extension is loaded by `phpstan/extension-installer`.
The project extension is registered separately through `phpstan/extension.neon`.
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
