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

namespace OCA\UserCAS\Service;

use \OCP\IConfig;
use \OCP\ILogger;

/**
 * Class LoggingService
 *
 * @package OCA\UserCAS\Service
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp <kontakt@felixrupp.com>
 *
 * @since 1.5.0
 */
class LoggingService
{

    /**
     * @since 1.6.1
     */
    const DEBUG = 0;
    /**
     * @since 1.6.1
     */
    const INFO = 1;
    /**
     * @since 1.6.1
     */
    const WARN = 2;
    /**
     * @since 1.6.1
     */
    const ERROR = 3;
    /**
     * @since 1.6.1
     */
    const FATAL = 4;

    /**
     * @var string $appName
     */
    private $appName;

    /**
     * @var \OCP\IConfig $appConfig
     */
    private $config;

    /**
     * @var \OCP\ILogger $logger
     */
    private $logger;

    /**
     * LoggingService constructor.
     * @param string $appName
     * @param \OCP\IConfig $config
     * @param \OCP\ILogger $logger
     */
    public function __construct($appName, IConfig $config, ILogger $logger)
    {

        $this->appName = $appName;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param mixed $level
     * @param string $message
     */
    public function write($level, $message)
    {

        $this->logger->log($level, $message, ['app' => $this->appName]);
    }
}