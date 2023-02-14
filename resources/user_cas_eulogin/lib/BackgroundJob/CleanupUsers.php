<?php

namespace OCA\UserCAS\BackgroundJob;


use OC\BackgroundJob\TimedJob;
use OC\User\NoUserException;
use OCP\Defaults;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;

class CleanupUsers extends TimedJob {

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
	 * - disable argument = true in order to also disable users
	 *     if not set the cron job will not disable identified users
	 * - initialNotificationMonthsNo to define the number of months of
	 *     inactivity after which a user will be notified.
	 *     if not set default will be 6
	 * - disableAfterMonthsNo to define the number of months of inactivity
	 *     after which a user will be disabled
	 *     if not set default will be 7
	 * - processOnlyUserEmails - if only a distinct set of user email should be
	 *     processed use this argument emails should be separated by semicolon
	 *     if left empty then all identified users will be processed (notified/disabled)
	 *
	 * @param $argument
	 *
	 *
	 * @throws \OC\User\NoUserException
	 */
    public function run($argument)
    {
		$disable = false;
        if (isset($argument['disable']) && $argument['disable'] === true) {
        	$disable = true;
        }

		$initialNotificationMonthsNo = 6;
		if (isset($argument['initialNotificationMonthsNo'])) {
			$initialNotificationMonthsNo = (int) $argument['initialNotificationMonthsNo'];
		}

		$disableAfterMonthsNo = 7;
		if (isset($argument['disableAfterMonthsNo'])) {
			$disableAfterMonthsNo = (int) $argument['disableAfterMonthsNo'];
		}

		$processOnlyUserEmails = null;
		if (isset($argument['processOnlyUserEmails'])) {
			$processOnlyUserEmails = explode(";", $argument['processOnlyUserEmails']);
		}

        $sixMonthsAGo = (new \DateTime("-".$initialNotificationMonthsNo." month"))->getTimestamp();
        $sevenMonthsAGo = (new \DateTime("-".$disableAfterMonthsNo." month"))->getTimestamp();

		$toBeNotified = [];
		$toBeNotifiedLdap = [];
        $toBeDisabled = [];

        foreach($this->userManager->getBackends() as $backend) {
            $limit = 1000;
            $offset = 0;
            do {
                $users = $backend->getUsers('', $limit, $offset);
                foreach ($users as $user) {
                    if ($this->userManager->userExists($user)) {
                        $user = $this->userManager->get($user);
						if (!$user) {
                            throw new NoUserException();
                        }
                        $lastLogin = $user->getLastLogin();

						if ($lastLogin < $sevenMonthsAGo && $user->isEnabled()) {
							if ($disable) {
								$toBeDisabled[] = $user;
							} else {
								$backend->getBackendName() === 'LDAP' ? $toBeNotifiedLdap[] = $user : $toBeNotified[] = $user;
							}
						} else {
							if ($lastLogin < $sixMonthsAGo && $user->isEnabled()) {
								$backend->getBackendName() === 'LDAP' ? $toBeNotifiedLdap[] = $user : $toBeNotified[] = $user;
							}
						}
                    }
                }
                $offset += $limit;
            } while (count($users) >= $limit);
        }

        $toBeDisabled = $this->filterUsers($processOnlyUserEmails, $toBeDisabled, 'disabled');
		$toBeNotified = $this->filterUsers($processOnlyUserEmails, $toBeNotified, 'notify');
		$toBeNotifiedLdap = $this->filterUsers($processOnlyUserEmails, $toBeNotifiedLdap, 'notify');

        if (count($toBeDisabled) && $disable) {
        	$this->disableUsers($toBeDisabled);
        }
        if (count($toBeNotified)) {
        	$this->sendNotificationEmail(array_filter($toBeNotified), $initialNotificationMonthsNo, $disableAfterMonthsNo, "ecas");
        }
		if (count($toBeNotifiedLdap)) {
			$this->sendNotificationEmail(array_filter($toBeNotifiedLdap), $initialNotificationMonthsNo, $disableAfterMonthsNo, "ldap");
		}
    }

    protected function sendNotificationEmail(array $emails, int $initialNotificationMonthsNo, int $disableAfterMonthsNo, string $userType = "ecas") {

		$this->logger->debug("emails to be sent " . serialize($emails));

		$emailTemplate = $this->renderEmailTemplate($initialNotificationMonthsNo, $disableAfterMonthsNo, $userType);

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				\OCP\Util::getDefaultEmailAddress($instanceName),
				$instanceName
			]
		);

		foreach ($emails as $email) {
			$message = $this->mailer->createMessage();
			$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
			$message->useTemplate($emailTemplate);
			$message->setTo([$email]);
			try {
				$this->mailer->send($message);
			} catch (\Exception $exception) {
				$this->logger->alert($exception->getMessage());
			}
		}
    }

    protected function renderEmailTemplate(int $initialNotificationMonthsNo, int $disableAfterMonthsNo, string $userType): IEMailTemplate {
		$emailTemplate = $this->mailer->createEMailTemplate('activity.Notification');

		$emailTemplate->setSubject("Account inactivity");
		$emailTemplate->addHeader();
		$emailTemplate->addBodyText(
			"Dear Asset Manager User,"
		);
		$monthsNoUntilDisabled = $disableAfterMonthsNo - $initialNotificationMonthsNo;
		if($monthsNoUntilDisabled <= 1) {//do not allow a lower than 1 month difference until disabling the account
			$monthsNoUntilDisabled = 1;
		}
		$expirationDate = (new \DateTime('+'.$monthsNoUntilDisabled.' month'))->format('Y-m-d H:i:s');

		$phone = <<<EOF
<a href="tel:+3222997969">+32 2 29 97969</a>
EOF;
		$mail = <<<EOF
<a href="mailto:europamanagement@ec.europa.eu">europamanagement@ec.europa.eu</a>
EOF;

		if($userType === 'ldap') {
			$emailTemplate->addBodyText(
				htmlspecialchars("According to our information you did not used Asset Manager API for more than $initialNotificationMonthsNo months. If you would like to keep this LDAP API account active, please send a minimum of 1 request to the Asset Manager API before $expirationDate." .
					" If you do not act, your account will be suspended from the application and you won't be able to use the LDAP account to send requests to Asset Manager API. Your content will not be removed.")
			);
			$emailTemplate->addBodyText(
				htmlspecialchars("If your account becomes disabled you will have to request CEM to enable it.")
			);

			$emailTemplate->addBodyText(
				$this->l->t("If you need more information please do not hesitate to contact the CEM support at %s or via %s.", [$phone, $mail]),
				vsprintf("If you need more information please do not hesitate to contact the CEM support at %1\$s or via %2\$s.", [$phone, $mail])
			);

			$emailTemplate->addBodyButton(
				$this->l->t('You can check the Asset Manager API documentation by using this link »%s«', ["Asset Manager API"]),
				"https://webgate.ec.europa.eu/fpfis/wikis/display/webtools/Asset+Manager+-+API"
			);
		} else {
			$emailTemplate->addBodyText(
				htmlspecialchars("According to our information you did not login into Asset Manager for more than $initialNotificationMonthsNo months. If you would like to keep this account active, please login into the application before $expirationDate." .
					" If you do not login before this date, your account will be suspended from the application and you won't be able to login anymore. Your content will not be removed.")
			);
			$emailTemplate->addBodyText(
				htmlspecialchars("If your account becomes disabled you will have to request CEM to enable it.")
			);

			$emailTemplate->addBodyText(
				$this->l->t("If you need more information please do not hesitate to contact the CEM support at %s or via %s.", [$phone, $mail]),
				vsprintf("If you need more information please do not hesitate to contact the CEM support at %1\$s or via %2\$s.", [$phone, $mail])
			);

			$emailTemplate->addBodyButton(
				$this->l->t('You can login by using this link »%s«', ["Asset Manager"]),
				"https://webgate.ec.europa.eu/webtools/asset-manager/index.php/login"
			);
		}

		// The "Reply-To" is set to the sharer if an mail address is configured
		// also the default footer contains a "Do not reply" which needs to be adjusted.
		$emailTemplate->addFooter();

		return $emailTemplate;
	}

    protected function disableUsers($users) {
		$this->logger->debug("Users to be disabled " . serialize($users));

		foreach ($users as $user) {
			$us = $this->userManager->get($user);
			if ($us) {
				if ($us->setEnabled(false)) {
					$this->logger->info("User " . $user . " has been disabled.");
				} else {
					$this->logger->info("Could not disable user $user");
				}
			}
		}
	}

	protected function filterUsers($processOnlyUserEmails, $userList, $actionType): array {
    	$filteredUsers = [];
    	if(count($userList)) {
    		foreach ($userList as $user) {
    			if(!$processOnlyUserEmails || in_array($user->getEMailAddress(), $processOnlyUserEmails)) {
					if($actionType === 'notify'){
						$filteredUsers[] = $user->getEmailAddress();
					} else {
						$filteredUsers[] = $user->getUID();
					}
				}
			}
		}

    	return $filteredUsers;
	}
}
