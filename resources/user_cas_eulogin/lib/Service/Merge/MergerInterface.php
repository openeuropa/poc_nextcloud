<?php


namespace OCA\UserCAS\Service\Merge;


/**
 * Interface MergerInterface
 * @package OCA\UserCAS\Service\Merge
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
interface MergerInterface
{

    public function mergeUsers(array &$userStack, array $userToMerge, $merge, $preferEnabledAccountsOverDisabled, $primaryAccountDnStartswWith);
}