<?php

namespace OCA\UserCAS\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OC\User\NoUserException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Iuser;
use OCP\Mail\IMailer;
use OCP\Defaults;

class DeleteDisabledUsers extends TimedJob {

	/** @var IUserManager */
	private $userManager;

	/** @var ILogger */
	private $logger;

	/** @var IMailer */
	private $mailer;

	/** @var Defaults */
	private $defaults;

	/** @var IL10N */
	private $l;

	/**
	 * @var mixed $argument
	 */
	protected $argument;

	/**
	 * CleanupUsers constructor.
	 */
	public function __construct(
		IUserManager $userManager,
		ILogger $logger,
		IMailer $mailer,
		Defaults $defaults,
		IL10N $l
	) {
		$nextInterval = (new \DateTime("1 months"))->getTimestamp() - (new \DateTime())->getTimestamp();
		$this->setInterval($nextInterval);
		$this->userManager = $userManager;
		$this->logger = $logger;
		$this->mailer = $mailer;
		$this->defaults = $defaults;
		$this->l = $l;
	}

	/**
	 * @desc
	 * - dryrun argument = true it will not remove users, instead it will send
	 *     an email with the list of users
	 * - deleteAfterMonthsNo to define the number of months of inactivity
	 *     after which a user will be disabled
	 *     if not set default will be 13(6 months after user should be disabled)
	 * - processOnlyUserEmails - if only a distinct set of user email should be
	 *     processed use this argument emails should be separated by semicolon
	 *     if left empty then all identified users will be deleted
	 * - notifyEmails - notify the following emails separated by semicolon
	 *     if left empty no notification will be sent
	 *
	 *
	 * @param $argument
	 *
	 * @throws \OC\User\NoUserException
	 */
	public function run($argument): void
	{
		$dryRun = false;
		if (isset($argument['dryrun']) && $argument['dryrun'] === true) {
			$dryRun = true;
		}

		$notifyEmails = false;
		if (isset($argument['notifyEmails'])) {
			$notifyEmails= explode(";", $argument['notifyEmails']);
		}

		$deleteAfterMonthsNo = 13;
		if (isset($argument['deleteAfterMonthsNo'])) {
			$deleteAfterMonthsNo = (int) $argument['deleteAfterMonthsNo'];
		}

		$processOnlyUserEmails = null;
		if (isset($argument['processOnlyUserEmails'])) {
			$processOnlyUserEmails = explode(";", $argument['processOnlyUserEmails']);
		}

		$deleteMonthsValue = (new \DateTime("-".$deleteAfterMonthsNo." month"))->getTimestamp();

		$toBeDeleted = [];

		foreach($this->userManager->getBackends() as $backend) {
			$limit = 1000;
			$offset = 0;
			if ($backend->getBackendName() !== 'LDAP') {
				do {
					$users = $backend->getUsers('', $limit, $offset);
					foreach ($users as $user) {
						if ($this->userManager->userExists($user)) {
							$user = $this->userManager->get($user);
							if (!$user) {
								throw new NoUserException();
							}
							$lastLogin = $user->getLastLogin();

							if ($lastLogin < $deleteMonthsValue && !$user->isEnabled()) {
								$toBeDeleted[] = $user;
							}
						}
					}
					$offset += $limit;
				} while (count($users) >= $limit);
			}
		}

		$toBeDeleted = $this->filterUsers($processOnlyUserEmails, $toBeDeleted);

		if (count($toBeDeleted)) {
			$this->deleteUsers($toBeDeleted, $dryRun, $notifyEmails);
		}

	}

	protected function deleteUsers($users, $dryRun, $notifyEmails): void {
		$usersDeleted = [];
		$usersIdDeleted = [];

		$instanceId = \OC::$server->getConfig()->getSystemValue('instanceid', null);
		$homePath = \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data');
		$avatarPath = $homePath . '/appdata_' . $instanceId . '/avatar';
		$deleteFolderList = \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/deleted_users.txt';
		$deletedFp = fopen($deleteFolderList, "a");

		/** @var Iuser $user */
		foreach ($users as $user) {
			if($dryRun) {
				$this->logger->info("User " . $user->getUID() . " " . $user->getEMailAddress() . " prepared to be deleted.");
			} else {
				$usersDeleted[] = $user->getEMailAddress();
				$usersIdDeleted[] = $user->getUID();
				$user->delete();

				//try to delete data from tables where it is not being deleted
				$builder = \OC::$server->getDatabaseConnection()->getQueryBuilder();

				//delete from oc_cards_properties
				$builder->select('id')
					->from('cards')
					->where($builder->expr()->eq('uid', $builder->createNamedParameter(mb_strtolower($user->getUID()))));
				$cardIds = $builder->execute()->fetchAll(\PDO::FETCH_COLUMN);

				if (count($cardIds)) {
					foreach ($cardIds as $cardId) {
						$builder->delete('cards_properties')
							->where($builder->expr()->eq('cardid', $builder->createNamedParameter($cardId)));
						$builder->execute();
					}
				}

				//delete from oc_cards
				$builder->delete('cards')
					->where($builder->expr()->eq('uid', $builder->createNamedParameter(mb_strtolower($user->getUID()))));
				$builder->execute();

				//delete from oc_credentials
				$builder->delete('storages_credentials')
					->where($builder->expr()->eq('user', $builder->createNamedParameter(mb_strtolower($user->getUID()))));
				$builder->execute();

				//delete from oc_preferences (only added files_external config_version identified)
				$builder->delete('preferences')
					->where($builder->expr()->eq('userid', $builder->createNamedParameter(mb_strtolower($user->getUID()))));
				$builder->execute();

				//delete from oc_mounts
				$builder->delete('mounts')
					->where($builder->expr()->eq('user_id', $builder->createNamedParameter(mb_strtolower($user->getUID()))));
				$builder->execute();

				//delete from oc_storages
				$storage_home = "home::" . $user->getUID();
				$builder->delete('storages')
					->where($builder->expr()->eq('id', $builder->createNamedParameter($storage_home)));
				$builder->execute();

				//delete files
				\OC_Helper::rmdirr($homePath . '/' . $user->getUID());

				if ($instanceId !== null) {
					\OC_Helper::rmdirr($avatarPath . '/' . $user->getUID());
				}

				//write in a list the userID that were deleted
				fwrite($deletedFp, $user->getUID() . PHP_EOL);

				$this->logger->info("User " . $user->getUID() . " " . $user->getEMailAddress() . " has been deleted.");
			}
		}

		fclose($deletedFp);

		if(!$dryRun && $notifyEmails && count($usersDeleted)) {
			$this->notifyEmails($notifyEmails, $usersDeleted, $usersIdDeleted);
		}
	}

	protected function filterUsers($processOnlyUserEmails, $userList): array {
		$filteredUsers = [];
		if(count($userList)) {
			foreach ($userList as $user) {
				if(!$processOnlyUserEmails || in_array($user->getEMailAddress(), $processOnlyUserEmails, false)) {
					$filteredUsers[] = $user;
				}
			}
		}

		return $filteredUsers;
	}

	protected function notifyEmails(array $notifyEmails, array $usersDeleted, array $usersIdDeleted):void {
		$emailTemplate = $this->mailer->createEMailTemplate('activity.Notification');

		$emailTemplate->setSubject("AMNGR Deleted - Disabled Users");
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText("Dear Asset Manager Admin,");
		$emailTemplate->addBodyText("The following disabled users were deleted from Asset Manager:");
		$emailTemplate->addBodyText(implode("; ", $usersDeleted));
		$emailTemplate->addBodyText(implode(",", $usersIdDeleted));

		// The "Reply-To" is set to the sharer if an mail address is configured
		// also the default footer contains a "Do not reply" which needs to be adjusted.
		$emailTemplate->addFooter();

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				\OCP\Util::getDefaultEmailAddress($instanceName),
				$instanceName
			]
		);

		foreach ($notifyEmails as $notifyEmail) {
			$message = $this->mailer->createMessage();
			$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
			$message->useTemplate($emailTemplate);
			$message->setTo([$notifyEmail]);
			try {
				$this->mailer->send($message);
			} catch (\Exception $exception) {
				$this->logger->alert($exception->getMessage());
			}
		}
	}
}
