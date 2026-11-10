<?php declare(strict_types=1);

namespace App\Model\Database;

use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use ReflectionClass;
use ReflectionProperty;


abstract class ModelRow extends ActiveRow
{
	/**
	 * @param array<string, mixed> $data
	 * @param Selection<ActiveRow> $table
	 */
	final public function __construct(array $data, Selection $table)
	{
		parent::__construct($data, $table);

		// Keep column values in ActiveRow. Unset declared properties delegate to
		// its magic accessors, preserving lazy loading and update() refreshes.
		foreach ((new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
			if (!$property->isStatic()) {
				unset($this->{$property->getName()});
			}
		}
	}
}
