<?php declare(strict_types=1);

namespace App\Database;

use App\Database\Model\User;
use Nette\Database\Explorer as NetteExplorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;


final class Explorer extends NetteExplorer
{
	/**
	 * Shared by row creation and the PHPStan extension.
	 * Add further models here.
	 */
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
