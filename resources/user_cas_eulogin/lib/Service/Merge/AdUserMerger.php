<?php


namespace OCA\UserCAS\Service\Merge;


use Psr\Log\LoggerInterface;

/**
 * Class AdUserMerger
 * @package OCA\UserCAS\Service\Merge
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class AdUserMerger implements MergerInterface
{


    /**
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * AdUserMerger constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Merge users method
     *
     * @param array $userStack
     * @param array $userToMerge
     * @param bool $merge
     * @param bool $preferEnabledAccountsOverDisabled
     * @param string $primaryAccountDnStartswWith
     */
    public function mergeUsers(array &$userStack, array $userToMerge, $merge, $preferEnabledAccountsOverDisabled, $primaryAccountDnStartswWith)
    {
        # User already in stack
        if ($merge && isset($userStack[$userToMerge["uid"]])) {

            $this->logger->debug("User " . $userToMerge["uid"] . " has to be merged …");

            // Check if accounts are enabled or disabled
            //      if both disabled, first account stays
            //      if one is enabled, use this account
            //      if both enabled, use information of $primaryAccountDnStartswWith

            if ($preferEnabledAccountsOverDisabled && $userStack[$userToMerge["uid"]]['enable'] == 0 && $userToMerge['enable'] == 1) { # First disabled, second enabled and $preferEnabledAccountsOverDisabled is true

                $this->logger->info("User " . $userToMerge["uid"] . " is merged because first account was disabled.");

                $userStack[$userToMerge["uid"]] = $userToMerge;
            }
            elseif(!$preferEnabledAccountsOverDisabled && $userStack[$userToMerge["uid"]]['enable'] == 0 && $userToMerge['enable'] == 1) {  # First disabled, second enabled and $preferEnabledAccountsOverDisabled is false

                $this->logger->info("User " . $userToMerge["uid"] . " has not been merged, second enabled account was not preferred, because of preferEnabledAccountsOverDisabled option.");
            }
            elseif ($userStack[$userToMerge["uid"]]['enable'] == 1 && $userToMerge['enable'] == 1) { # Both enabled

                if (strpos(strtolower($userToMerge['dn']), strtolower($primaryAccountDnStartswWith) !== FALSE)) {

                    $this->logger->info("User " . $userToMerge["uid"] . " is merged because second account is primary, based on merge filter.");

                    $userStack[$userToMerge["uid"]] = $userToMerge;
                }
                else {

                    $this->logger->info("User " . $userToMerge["uid"] . " has not been merged, second account was not primary, based on merge filter.");
                }
            } else {

                $this->logger->info("User " . $userToMerge["uid"] . " has not been merged, second account was disabled, first account was enabled.");
            }
        } else { # User not in stack

            $userStack[$userToMerge["uid"]] = $userToMerge;
        }
    }
}