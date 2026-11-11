<?php declare(strict_types=1);

namespace App\Database\Repository;

use App\Database\Explorer;
use App\Database\Model\User;
use UnexpectedValueException;


final class UserRepository
{
	public function __construct(
		private readonly Explorer $database,
	) {
	}

	public function findById(int $id): ?User
	{
		return $this->database->table('users')->get($id);
	}

	/**
	 * @return list<User>
	 */
	public function findAll(): array
	{
		return array_values($this->database->table('users')->order('name, id')->fetchAll());
	}

	public function create(string $name, string $email): User
	{
		$row = $this->database->table('users')->insert([
			'name' => $name,
			'email' => $email,
		]);

		if (!$row instanceof User) {
			throw new UnexpectedValueException('Expected the inserted users row; check the table primary key.');
		}

		return $row;
	}
}
