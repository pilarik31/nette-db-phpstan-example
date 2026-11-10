<?php declare(strict_types=1);

namespace App\Model\Database;

use App\Model\User\User;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;


final class ModelExplorer extends Explorer
{
	// Shared by row creation and the PHPStan extension. Add further models here.
	public const ROW_CLASSES = [
		'users' => User::class,
	];


	/**
	 * @param array<string, mixed> $data
	 * @param Selection<ActiveRow> $selection
	 */
	public function createActiveRow(array $data, Selection $selection): ActiveRow
	{
		$rowClass = self::ROW_CLASSES[$selection->getName()] ?? ActiveRow::class;
		return new $rowClass($data, $selection);
	}
}
