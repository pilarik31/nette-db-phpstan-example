<?php declare(strict_types=1);

namespace App\Model\User;

use App\Model\Database\ModelRow;


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
