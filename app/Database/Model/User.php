<?php declare(strict_types=1);

namespace App\Database\Model;

use Nette\Database\Table\ActiveRow;


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
