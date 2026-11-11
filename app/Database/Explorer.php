<?php declare(strict_types=1);

namespace App\Database;

use App\Database\Model\User;
use InvalidArgumentException;
use Nette\Caching\Storage;
use Nette\Database\Connection;
use Nette\Database\Conventions;
use Nette\Database\Explorer as NetteExplorer;
use Nette\Database\IStructure;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection as NetteSelection;


final class Explorer extends NetteExplorer
{
	/**
	 * Shared by row creation and tableFor().
	 * Add further models here.
	 */
	public const ROW_CLASSES = [
		'users' => User::class,
	];

	public function __construct(
		Connection $connection,
		IStructure $structure,
		?Conventions $conventions = null,
		private readonly ?Storage $cacheStorage = null,
	) {
		parent::__construct($connection, $structure, $conventions, $cacheStorage);
	}

	/**
	 * @template T of ActiveRow
	 * @param class-string<T> $model
	 * @return NetteSelection<T>
	 */
	public function tableFor(string $model): NetteSelection
	{
		$table = array_search($model, self::ROW_CLASSES, true);
		if ($table === false) {
			throw new InvalidArgumentException("No table is registered for model $model.");
		}

		// The checked registry entry determines the row type created by createActiveRow().
		/** @var NetteSelection<T> $selection */
		$selection = new NetteSelection($this, $this->getConventions(), $table, $this->cacheStorage);
		return $selection;
	}

	/**
	 * @param array<string, mixed> $data
	 * @param NetteSelection<ActiveRow> $selection
	 */
	public function createActiveRow(array $data, NetteSelection $selection): ActiveRow
	{
		$rowClass = self::ROW_CLASSES[$selection->getName()] ?? ActiveRow::class;
		return new $rowClass($data, $selection);
	}
}
