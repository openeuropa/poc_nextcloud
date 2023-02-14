<?php

/**
 * ownCloud - user_cas
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\UserCAS\User;

use OC\User\Database;
use OCA\UserCAS\Exception\PhpCas\PhpUserCasLibraryNotFoundException;
use OCA\UserCAS\Service\AppService;
use OCA\UserCAS\Service\LoggingService;
use OCA\UserCAS\Service\UserService;
use OCP\IConfig;
use OCP\IUserBackend;
use OCP\IUserManager;
use OCP\User\IProvidesDisplayNameBackend;
use OCP\User\IProvidesHomeBackend;
use OCP\UserInterface;


/**
 * Class Backend
 *
 * @package OCA\UserCAS\User
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.4.0
 */
class Backend extends Database implements UserInterface, IUserBackend, IProvidesHomeBackend, IProvidesDisplayNameBackend, UserCasBackendInterface
{

    /**
     * @var string
     */
    protected $appName;

    /**
     * @var IConfig
     */
    protected $config;

    /**
     * @var \OCA\UserCAS\Service\LoggingService $loggingService
     */
    protected $loggingService;

    /**
     * @var \OCA\UserCAS\Service\AppService $appService
     */
    protected $appService;

    /**
     * @var \OCA\UserCAS\Service\UserService $userService
     */
    protected $userService;


    /**
     * @var \OCP\IUserManager $userManager
     */
    protected $userManager;


    /**
     * Backend constructor.
     * @param string $appName
     * @param IConfig $config
     * @param LoggingService $loggingService
     * @param AppService $appService
     * @param IUserManager $userManager
     * @param UserService $userService
     */
    public function __construct($appName, IConfig $config, LoggingService $loggingService, AppService $appService, IUserManager $userManager, UserService $userService)
    {

        parent::__construct();
        $this->appName = $appName;
        $this->loggingService = $loggingService;
        $this->appService = $appService;
        $this->userService = $userService;
        $this->config = $config;
        $this->userManager = $userManager;
    }


    /**
     * Backend name to be shown in user management
     * @return string the name of the backend to be shown
     */
    public function getBackendName()
    {

        return "EULOGIN";
    }


    /**
     * @param string $uid
     * @param string $password
     * @return string|bool The users UID or false
     */
    public function checkPassword($uid, $password)
    {

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                return FALSE;
            }
        }

        if (\phpCAS::isInitialized()) {

            if ($uid === FALSE) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::ERROR, 'phpCAS returned no user.');
            }

            if (\phpCAS::isAuthenticated()) {

                #$casUid = \phpCAS::getUser();
                $casUid = $this->userService->getUserId();

                $isAuthorized = TRUE;
                $createUser = TRUE;


                # Check if user may be authorized based on groups or not
                $cas_access_allow_groups = $this->config->getAppValue($this->appName, 'cas_access_allow_groups');
                if (is_string($cas_access_allow_groups) && strlen($cas_access_allow_groups) > 0) {

                    $cas_access_allow_groups = explode(',', $cas_access_allow_groups);
                    $casAttributes = \phpCAS::getAttributes();
                    $casGroups = array();
                    $groupMapping = $this->config->getAppValue($this->appName, 'cas_group_mapping');

                    # Test if an attribute parser added a new dimension to our attributes array
                    if (array_key_exists('attributes', $casAttributes)) {

                        $newAttributes = $casAttributes['attributes'];

                        unset($casAttributes['attributes']);

                        $casAttributes = array_merge($casAttributes, $newAttributes);
                    }

                    # Test for mapped attribute from settings
                    if (array_key_exists($groupMapping, $casAttributes)) {

                        $casGroups = (array)$casAttributes[$groupMapping];
                    } # Test for standard 'groups' attribute
                    else if (array_key_exists('groups', $casAttributes)) {

                        if ($this->config->getAppValue($this->appName, 'cas_groups_json_decode')) {

                            $casGroups = json_decode($casAttributes['groups']);
                        } else {

                            $casGroups = (array)$casAttributes['groups'];
                        }
                    }

                    $isAuthorized = FALSE;

                    foreach ($casGroups as $casGroup) {

                        if (in_array($casGroup, $cas_access_allow_groups)) {

                            $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS users login has been authorized with group: ' . $casGroup);

                            $isAuthorized = TRUE;
                        } else {

                            $this->loggingService->write(LoggingService::DEBUG, 'phpCas CAS users login has not been authorized with group: ' . $casGroup . ', because the group was not in allowedGroups: ' . implode(", ", $cas_access_allow_groups));
                        }
                    }
                }


                // Autocreate user if needed or create a new account in CAS Backend
                if (!$this->userManager->userExists($uid) && boolval($this->config->getAppValue($this->appName, 'cas_autocreate'))) {

                    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'phpCAS creating a new user with UID: ' . $uid);
                } elseif (!$this->userManager->userExists($uid) && !boolval($this->config->getAppValue($this->appName, 'cas_autocreate'))) {

                    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'phpCAS no new user has been created.');

                    $createUser = FALSE;
                }

                // Finalize check
                if ($casUid === $uid && $isAuthorized && $createUser) {

                    $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'phpCAS user password has been checked.');

                    return $uid;
                }
            }

            $this->loggingService->write(\OCA\UserCas\Service\LoggingService::DEBUG, 'phpCAS user password has been checked, user not logged in.');

            return FALSE;
        } else {

            $this->loggingService->write(\OCA\UserCas\Service\LoggingService::ERROR, 'phpCAS has not been initialized.');
            return FALSE;
        }
    }


    /**
     * @param string $uid
     * @return bool|string
     */
    public function getDisplayName($uid)
    {

        $displayName = $uid;

        if (!$this->appService->isCasInitialized()) {

            try {

                $this->appService->init();
            } catch (PhpUserCasLibraryNotFoundException $e) {

                $this->loggingService->write(\OCA\UserCas\Service\LoggingService::ERROR, 'Fatal error with code: ' . $e->getCode() . ' and message: ' . $e->getMessage());

                return $displayName;
            }
        }

        if (\phpCAS::isInitialized()) {

            if (\phpCAS::isAuthenticated()) {

                $casAttributes = \phpCAS::getAttributes();

                # Test if an attribute parser added a new dimension to our attributes array
                if (array_key_exists('attributes', $casAttributes)) {

                    $newAttributes = $casAttributes['attributes'];

                    unset($casAttributes['attributes']);

                    $casAttributes = array_merge($casAttributes, $newAttributes);
                }

                // DisplayName
                $displayNameMapping = $this->config->getAppValue($this->appName, 'cas_displayName_mapping');

                $displayNameMappingArray = explode("+", $displayNameMapping);

                $displayName = '';

                foreach ($displayNameMappingArray as $displayNameMapping) {

                    if (array_key_exists($displayNameMapping, $casAttributes)) {

                        $displayName .= $casAttributes[$displayNameMapping] . " ";
                    }
                }

                $displayName = trim($displayName);

                if ($displayName === '' && array_key_exists('displayName', $casAttributes)) {

                    $displayName = $casAttributes['displayName'];
                }
            }
        }

        return $displayName;
    }
}
