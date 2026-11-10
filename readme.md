Nette Web Project
=================

Welcome to the Nette Web Project! This is a basic skeleton application built using
[Nette](https://nette.org), ideal for kick-starting your new web projects.

Nette is a renowned PHP web development framework, celebrated for its user-friendliness,
robust security, and outstanding performance. It's among the safest choices
for PHP frameworks out there.

If Nette helps you, consider supporting it by [making a donation](https://nette.org/donate).
Thank you for your generosity!


Requirements
------------

This Web Project is compatible with Nette 3.2 and requires PHP 8.2.


Installation
------------

To install the Web Project, Composer is the recommended tool. If you're new to Composer,
follow [these instructions](https://doc.nette.org/composer). Then, run:

	composer create-project nette/web-project path/to/install
	cd path/to/install

Ensure the `temp/` and `log/` directories are writable.


Database
--------

Nette Database is installed and configured in `config/common.neon`. The default
connection uses an in-memory SQLite database, whose contents disappear when the
connection closes. PHP's `pdo_sqlite` extension is required for this connection.
Change the DSN and credentials to use a persistent database.

Inject `Nette\Database\Explorer` or `Nette\Database\Connection` through a constructor;
both services are registered automatically by Nette.
See the [database configuration documentation](https://doc.nette.org/en/database/configuration/3.x).


Typed Database Model Example
----------------------------

The homepage demonstrates the model through `HomePresenter`, `UserRepository`
and the typed `HomeTemplate`:

	php -S localhost:8000 -t www

Open `http://localhost:8000/` to see the users. Click a name to view its detail
(for example, `/home/default/1`), including the native properties and `getLabel()`
method. A nonexistent user returns HTTP 404.

`DatabaseDemoService` creates three sample users for each request when the
configured connection is `sqlite::memory:`. It does not initialize or seed other
database connections. With a persistent database, supply the schema and data
yourself.

Run the standalone example:

	composer run example:database

It creates a separate SQLite database in memory, loads `examples/users.sql`,
inserts a user and reads it back. It never changes the application database.

`App\Model\User\UserRepository` uses Nette Database Explorer for queries and is
automatically discovered by the `*Repository` rule in `config/services.neon`.
Inject it into a presenter or service through its constructor. Its methods return
`User`, `?User` or `list<User>`. `User` extends `ModelRow`, a subclass of `ActiveRow`:

```php
final class User extends ModelRow
{
	public int $id;
	public string $name;
	public string $email;

	public function getLabel(): string
	{
		return $this->name . ' <' . $this->email . '>';
	}
}
```

There is no `fromRow()` or separate hydration code in each model:

```php
// $users is an injected App\Model\User\UserRepository.
$user = $users->findById(1);
if ($user !== null) {
	echo strtoupper($user->name); // PHPStan knows name is string
	echo $user->getLabel();      // a method on the actual User instance
	$user->update(['name' => 'Ada']);
}
```

`ModelExplorer::ROW_CLASSES` is the single table-to-model registry:

```php
public const ROW_CLASSES = [
	'users' => User::class,
];
```

`ModelExplorer::createActiveRow()` constructs the mapped class whenever Explorer
loads a row, including insert refetches and relationships. Unmapped tables use
the original `ActiveRow`. The DI definition replaces `database.default.explorer`
with `ModelExplorer`, preserving the configured connection and autowiring.
This example uses the API in the locked Nette Database 3.2.10 version.

The PHPStan extension in `phpstan/` reads the same registry and infers
`Selection<User>` from `ModelExplorer::table('users')`. The types propagate through
`get()`, `fetch()`, `fetchAll()`, query chaining and `foreach`. An unknown table
name remains `Selection<ActiveRow>`. Inject `ModelExplorer` when you want this
inference; a variable declared only as the parent `Explorer` does not carry the
custom mapping type information.

Add another model by extending `ModelRow`, declaring its columns as ordinary
public typed properties without defaults, and adding its table to `ROW_CLASSES`.
Use nullable types for nullable columns. The properties must match the schema
and the database driver's actual types.

`ModelRow` unsets the declared public instance properties in its constructor.
PHP then delegates reads and writes to the inherited `ActiveRow` magic accessors,
while still enforcing each declared property's type when it is read. This is a
[documented PHP mechanism](https://wiki.php.net/rfc/typed_properties_v2#overloaded_properties)
and works with the project's PHP 8.2 requirement. Values remain in `ActiveRow`,
so `update()` refreshes are visible through the properties and Nette keeps its
normal column-loading behavior. Explicit `select()` still limits which columns
can be read, just as with plain `ActiveRow`.

Do not mark these properties `readonly`: their values are managed by `ActiveRow`.
Use `update()` to write changes; direct property assignment is rejected by its
magic setter. PHPStan sees the native declarations as public writable properties,
so it does not enforce that write restriction statically. A database value with
the wrong type raises `TypeError` on property access instead of being silently
cast. No per-model constructor, property hooks or `@property` annotations are
needed.

Use property access (`$user->email`) for the declared column types;
the inherited array access and `ref()` / `related()` method signatures still
return their generic Nette types. Runtime row mapping also works for relations,
but precise static relation types require additional declarations.

No baseline, error ignores or inline `@var` overrides are needed.

The example schema belongs to the demo. A persistent application
database needs its own schema/migrations.

Static Analysis
---------------

Run PHPStan with:

	composer run phpstan

The configuration in `phpstan.neon` analyses `app/`, `bin/`, `phpstan/` and
`www/index.php` at `level: max`, which always selects PHPStan's highest available
level. The installed Nette extensions are loaded by `phpstan/extension-installer`.
PHPStan stores its cache in `temp/phpstan/`.

See the [PHPStan configuration reference](https://phpstan.org/config-reference).


Asset Building with Vite
------------------------

This project supports Vite for asset building, which is recommended but optional. To activate Vite:

1. Uncomment the `type: vite` line in the `common.neon` configuration file under the assets mapping section.
2. Then set up and build the assets:

		npm install
		npm run build


Web Server Setup
----------------

To quickly dive in, use PHP's built-in server:

	php -S localhost:8000 -t www

Then, open `http://localhost:8000` in your browser to view the welcome page.

For Apache or Nginx users, configure a virtual host pointing to your project's `www/` directory.

**Important Note:** Ensure `app/`, `config/`, `log/`, and `temp/` directories are not web-accessible.
Refer to [security warning](https://nette.org/security-warning) for more details.


Minimal Skeleton
----------------

For demonstrating issues or similar tasks, rather than starting a new project, use
[minimal skeleton](https://github.com/nette/web-project/tree/minimal).
