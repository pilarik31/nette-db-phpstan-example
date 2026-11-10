<?php declare(strict_types=1);

namespace App\Presentation\Home;

use App\Model\Database\DatabaseDemoService;
use App\Model\User\UserRepository;
use Nette;


final class HomePresenter extends Nette\Application\UI\Presenter
{
	public function __construct(
		private readonly UserRepository $users,
		private readonly DatabaseDemoService $demo,
	) {
	}

	public function renderDefault(?int $id = null): void
	{
		$demoDatabase = $this->demo->initialize();
		$selectedUser = $id === null ? null : $this->users->findById($id);
		if ($id !== null && $selectedUser === null) {
			$this->error('Uživatel nebyl nalezen.');
		}

		$template = $this->createTemplate(HomeTemplate::class);
		$template->users = $this->users->findAll();
		$template->selectedUser = $selectedUser;
		$template->pageTitle = $selectedUser === null ? 'Uživatelé' : 'Uživatel: ' . $selectedUser->name;
		$template->demoDatabase = $demoDatabase;
		$this->sendTemplate($template);
	}
}
