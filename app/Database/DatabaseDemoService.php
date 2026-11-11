<?php declare(strict_types=1);

namespace App\Database;

use App\Database\Repository\UserRepository;
use Nette\Database\Helpers;


final class DatabaseDemoService
{
	private bool $initialized = false;


	public function __construct(
		private readonly Explorer $database,
		private readonly UserRepository $users,
	) {
	}

	public function initialize(): bool
	{
		$connection = $this->database->getConnection();
		if ($connection->getDsn() !== 'sqlite::memory:') {
			return false;
		}

		if (!$this->initialized) {
			Helpers::loadFromFile($connection, dirname(__DIR__, 2) . '/examples/users.sql');
			$this->users->create('Ada Lovelace', 'ada@example.com');
			$this->users->create('Grace Hopper', 'grace@example.com');
			$this->users->create('Margaret Hamilton', 'margaret@example.com');
			$this->initialized = true;
		}

		return true;
	}
}
