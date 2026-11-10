<?php declare(strict_types=1);

namespace App\PHPStan;

use App\Model\Database\ModelExplorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;


final class ModelExplorerReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return ModelExplorer::class;
	}


	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'table';
	}


	public function getTypeFromMethodCall(
		MethodReflection $methodReflection,
		MethodCall $methodCall,
		Scope $scope,
	): ?Type
	{
		if ($methodCall->isFirstClassCallable() || $methodCall->getArgs() === []) {
			return null;
		}

		$tableType = $scope->getType($methodCall->getArgs()[0]->value);
		$tables = $tableType->getConstantStrings();
		// Do not narrow arbitrary strings or unions containing non-constant strings.
		if ($tables === [] || !TypeCombinator::union(...$tables)->equals($tableType)) {
			return null;
		}

		$types = [];
		foreach ($tables as $table) {
			$rowClass = ModelExplorer::ROW_CLASSES[$table->getValue()] ?? ActiveRow::class;
			$types[] = new GenericObjectType(Selection::class, [new ObjectType($rowClass)]);
		}

		return TypeCombinator::union(...$types);
	}
}
