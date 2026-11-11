<?php declare(strict_types=1);

namespace App\Presentation\Home;

use App\Database\Model\User;
use Nette\Bridges\ApplicationLatte\Template;


final class HomeTemplate extends Template
{
	/** @var list<User> */
	public array $users = [];

	public ?User $selectedUser = null;

	public string $pageTitle = 'Uživatelé';

	public bool $demoDatabase = false;

	/** @var array<\stdClass> */
	public array $flashes = [];
}
