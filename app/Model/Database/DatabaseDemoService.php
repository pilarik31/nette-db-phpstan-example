<?php declare(strict_types=1);

namespace App\Model\Database;

use App\Model\User\UserRepository;
use Nette\Database\Helpers;


final class DatabaseDemoService
{
	private bool $initialized = false;


	public function __construct(
		private readonly ModelExplorer $database,
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
			Helpers::loadFromFile($connection, dirname(__DIR__, 3) . '/examples/users.sql');
			$this->users->create('Ada Lovelace', 'ada@example.com');
			$this->users->create('Grace Hopper', 'grace@example.com');
			$this->users->create('Margaret Hamilton', 'margaret@example.com');
			$this->initialized = true;
		}

		return true;
	}
}
