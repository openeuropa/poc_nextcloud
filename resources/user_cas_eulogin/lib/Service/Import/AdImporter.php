<?php


namespace OCA\UserCAS\Service\Import;

use OCA\UserCAS\Service\Merge\AdUserMerger;
use OCA\UserCAS\Service\Merge\MergerInterface;
use OCP\IConfig;
use Psr\Log\LoggerInterface;


/**
 * Class AdImporter
 * @package OCA\UserCAS\Service\Import
 *
 * @author Felix Rupp <kontakt@felixrupp.com>
 * @copyright Felix Rupp
 *
 * @since 1.0.0
 */
class AdImporter implements ImporterInterface
{

    /**
     * @var boolean|resource
     */
    private $ldapConnection;

    /**
     * @var MergerInterface $merger
     */
    private $merger;

    /**
     * @var LoggerInterface $logger
     */
    private $logger;

    /**
     * @var IConfig
     */
    private $config;

    /**
     * @var string $appName
     */
    private $appName = 'user_cas';


    /**
     * AdImporter constructor.
     * @param IConfig $config
     */
    public function __construct(IConfig $config)
    {

        $this->config = $config;
    }


    /**
     * @param LoggerInterface $logger
     *
     * @throws \Exception
     */
    public function init(LoggerInterface $logger)
    {

        $this->merger = new AdUserMerger($logger);
        $this->logger = $logger;

        $this->ldapConnect();
        $this->ldapBind();

        $this->logger->info("Init complete.");
    }

    /**
     * @throws \Exception
     */
    public function close()
    {

        $this->ldapClose();
    }

    /**
     * Get User data
     *
     * @return array User data
     */
    public function getUsers()
    {

        $uidAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_uid');

        $displayNameAttribute1 = $this->config->getAppValue($this->appName, 'cas_import_map_displayname');
        $displayNameAttribute2 = '';

        if (strpos($displayNameAttribute1, "+") !== FALSE) {
            $displayNameAttributes = explode("+", $displayNameAttribute1);
            $displayNameAttribute1 = $displayNameAttributes[0];
            $displayNameAttribute2 = $displayNameAttributes[1];
        }


        $emailAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_email');
        $groupsAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_groups');
        $quotaAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_quota');
        $enableAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_enabled');
        $dnAttribute = $this->config->getAppValue($this->appName, 'cas_import_map_dn');
        $mergeAttribute = boolval($this->config->getAppValue($this->appName, 'cas_import_merge'));
        $primaryAccountDnStartswWith = $this->config->getAppValue($this->appName, 'cas_import_map_dn_filter');
        $preferEnabledAccountsOverDisabled = boolval($this->config->getAppValue($this->appName, 'cas_import_merge_enabled'));
        $andEnableAttributeBitwise = $this->config->getAppValue($this->appName, 'cas_import_map_enabled_and_bitwise');

        $keep = [$uidAttribute, $displayNameAttribute1, $displayNameAttribute2, $emailAttribute, $groupsAttribute, $quotaAttribute, $enableAttribute, $dnAttribute];

        $groupAttrField = $this->config->getAppValue($this->appName, 'cas_import_map_groups_description');
        $groupsKeep = [$groupAttrField];

        $pageSize = $this->config->getAppValue($this->appName, 'cas_import_ad_sync_pagesize');

        $users = [];

        $this->logger->info("Getting all users from the AD …");

        # Get all members of the sync group
        $memberPages = $this->getLdapList($this->config->getAppValue($this->appName, 'cas_import_ad_base_dn'), $this->config->getAppValue($this->appName, 'cas_import_ad_sync_filter'), $keep, $pageSize);

        foreach ($memberPages as $memberPage) {

            #var_dump($memberPage["count"]);

            for ($key = 0; $key < $memberPage["count"]; $key++) {

                $m = $memberPage[$key];

                # Each attribute is returned as an array, the first key is [count], [0]+ will contain the actual value(s)
                $employeeID = isset($m[$uidAttribute][0]) ? $m[$uidAttribute][0] : "";
                $mail = isset($m[$emailAttribute][0]) ? $m[$emailAttribute][0] : "";
                $dn = isset($m[$dnAttribute]) ? $m[$dnAttribute] : "";

                $displayName = $employeeID;

                if (isset($m[$displayNameAttribute1][0])) {

                    $displayName = $m[$displayNameAttribute1][0];

                    if (strlen($displayNameAttribute2) > 0 && isset($m[$displayNameAttribute2][0])) {

                        $displayName .= " " . $m[$displayNameAttribute2][0];
                    }
                } else {

                    if (strlen($displayNameAttribute2) > 0 && isset($m[$displayNameAttribute2][0])) {

                        $displayName = $m[$displayNameAttribute2][0];
                    }
                }

                $quota = isset($m[$quotaAttribute][0]) ? intval($m[$quotaAttribute][0]) : 0;


                $enable = 1;

                # Shift enable attribute bytewise?
                if (isset($m[$enableAttribute][0])) {

                    if (strlen($andEnableAttributeBitwise) > 0) {

                        if (is_numeric($andEnableAttributeBitwise)) {

                            $andEnableAttributeBitwise = intval($andEnableAttributeBitwise);
                        }

                        $enable = intval((intval($m[$enableAttribute][0]) & $andEnableAttributeBitwise) == 0);
                    } else {

                        $enable = intval($m[$enableAttribute][0]);
                    }
                }

                $groupsArray = [];

                $addUser = FALSE;

                if (isset($m[$groupsAttribute][0])) {

                    # Cycle all groups of the user
                    for ($j = 0; $j < $m[$groupsAttribute]["count"]; $j++) {

                        # Check if user has MAP_GROUPS attribute
                        if (isset($m[$groupsAttribute][$j])) {

                            $addUser = TRUE; # Only add user if the group has a MAP_GROUPS attribute

                            $groupCn = $m[$groupsAttribute][$j];

                            # Retrieve the MAP_GROUPS_FIELD attribute of the group
                            $groupAttr = $this->getLdapAttributes($groupCn, $groupsKeep);
                            $groupName = '';

                            if (isset($groupAttr[$groupAttrField][0])) {

                                $groupName = $groupAttr[$groupAttrField][0];

                                /*# Replace umlauts
                                if (boolval($this->config->getAppValue($this->appName, 'cas_import_map_groups_letter_umlauts'))) {

                                    $groupName = str_replace("Ä", "Ae", $groupName);
                                    $groupName = str_replace("Ö", "Oe", $groupName);
                                    $groupName = str_replace("Ü", "Ue", $groupName);
                                    $groupName = str_replace("ä", "ae", $groupName);
                                    $groupName = str_replace("ö", "oe", $groupName);
                                    $groupName = str_replace("ü", "ue", $groupName);
                                    $groupName = str_replace("ß", "ss", $groupName);
                                }

                                # Filter unwanted characters
                                $nameFilter = $this->config->getAppValue($this->appName, 'cas_import_map_groups_letter_filter');

                                if (strlen($nameFilter) > 0) {

                                    $groupName = preg_replace("/[^" . $nameFilter . "]+/", "", $groupName);
                                }

                                # Filter length to max 64 chars
                                $groupName = substr($groupName, 0, 64);*/
                            }
                            else {

                                $groupCnArray = explode(",", $groupCn);
                                $groupName = substr($groupCnArray[0], 3,  strlen($groupCnArray[0]));
                            }

                            if (strlen($groupName) > 0) {

                                $groupsArray[] = $groupName;
                            }
                        }
                    }
                }

                # Fill the users array only if we have an employeeId and addUser is true
                if (isset($employeeID) && $addUser) {

                    $this->merger->mergeUsers($users, ['uid' => $employeeID, 'displayName' => $displayName, 'email' => $mail, 'quota' => $quota, 'groups' => $groupsArray, 'enable' => $enable, 'dn' => $dn], $mergeAttribute, $preferEnabledAccountsOverDisabled, $primaryAccountDnStartswWith);
                }
            }
        }

        $this->logger->info("Users have been retrieved.");

        return $users;
    }


    /**
     * List ldap entries in the base dn
     *
     * @param string $object_dn
     * @param $filter
     * @param array $keepAtributes
     * @param $pageSize
     * @return array
     */
    protected function getLdapList($object_dn, $filter, $keepAtributes, $pageSize)
    {

        $cookie = '';
        $members = [];

        do {

            // Query Group members
            ldap_control_paged_result($this->ldapConnection, $pageSize, false, $cookie);

            $results = ldap_search($this->ldapConnection, $object_dn, $filter, $keepAtributes/*, array("member;range=$range_start-$range_end")*/) or die('Error searching LDAP: ' . ldap_error($this->ldapConnection));
            $members[] = ldap_get_entries($this->ldapConnection, $results);

            ldap_control_paged_result_response($this->ldapConnection, $results, $cookie);

        } while ($cookie !== null && $cookie != '');

        // Return sorted member list
        sort($members);

        return $members;
    }


    /**
     * @param string $user_dn
     * @param bool $keep
     * @return array Attribute list
     */
    protected function getLdapAttributes($user_dn, $keep = false)
    {

        if (!isset($this->ldapConnection)) die('Error, no LDAP connection established');
        if (empty($user_dn)) die('Error, no LDAP user specified');

        // Disable pagination setting, not needed for individual attribute queries
        ldap_control_paged_result($this->ldapConnection, 1);

        // Query user attributes
        $results = (($keep) ? ldap_search($this->ldapConnection, $user_dn, 'cn=*', $keep) : ldap_search($this->ldapConnection, $user_dn, 'cn=*'))
        or die('Error searching LDAP: ' . ldap_error($this->ldapConnection));

        $attributes = ldap_get_entries($this->ldapConnection, $results);

        $this->logger->debug("AD attributes successfully retrieved.");

        // Return attributes list
        if (isset($attributes[0])) return $attributes[0];
        else return array();
    }


    /**
     * Connect ldap
     *
     * @return bool|resource
     * @throws \Exception
     */
    protected function ldapConnect()
    {
        try {

            $host = $this->config->getAppValue($this->appName, 'cas_import_ad_host');

            $this->ldapConnection = ldap_connect($this->config->getAppValue($this->appName, 'cas_import_ad_protocol') . $host . ":" . $this->config->getAppValue($this->appName, 'cas_import_ad_port')) or die("Could not connect to " . $host);

            ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->ldapConnection, LDAP_OPT_NETWORK_TIMEOUT, 10);

            $this->logger->info("AD connected successfully.");

            return $this->ldapConnection;
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Bind ldap
     *
     * @throws \Exception
     */
    protected function ldapBind()
    {

        try {

            if ($this->ldapConnection) {

                $ldapIsBound = ldap_bind($this->ldapConnection, $this->config->getAppValue($this->appName, 'cas_import_ad_user') . "@" . $this->config->getAppValue($this->appName, 'cas_import_ad_domain'), $this->config->getAppValue($this->appName, 'cas_import_ad_password'));

                if (!$ldapIsBound) {

                    throw new \Exception("LDAP bind failed. Error: " . ldap_error($this->ldapConnection));
                } else {

                    $this->logger->info("AD bound successfully.");
                }
            }
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Unbind ldap
     *
     * @throws \Exception
     */
    protected function ldapUnbind()
    {

        try {

            ldap_unbind($this->ldapConnection);

            $this->logger->info("AD unbound successfully.");
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * Close ldap connection
     *
     * @throws \Exception
     */
    protected function ldapClose()
    {
        try {

            ldap_close($this->ldapConnection);

            $this->logger->info("AD connection closed successfully.");
        } catch (\Exception $e) {

            throw $e;
        }
    }

    /**
     * @param array $exportData
     */
    public function exportAsCsv(array $exportData)
    {

        $this->logger->info("Exporting users to .csv …");

        $fp = fopen('accounts.csv', 'wa+');

        fputcsv($fp, ["UID", "displayName", "email", "quota", "groups", "enabled"]);

        foreach ($exportData as $fields) {

            for ($i = 0; $i < count($fields); $i++) {

                if (is_array($fields[$i])) {

                    $fields[$i] = $this->multiImplode($fields[$i], " ");
                }
            }

            fputcsv($fp, $fields);
        }

        fclose($fp);

        $this->logger->info("CSV export finished.");
    }

    /**
     * @param array $exportData
     */
    public function exportAsText(array $exportData)
    {

        $this->logger->info("Exporting users to .txt …");

        file_put_contents('accounts.txt', serialize($exportData));

        $this->logger->info("TXT export finished.");
    }

    /**
     * @param array $array
     * @param string $glue
     * @return bool|string
     */
    private function multiImplode($array, $glue)
    {
        $ret = '';

        foreach ($array as $item) {
            if (is_array($item)) {
                $ret .= $this->multiImplode($item, $glue) . $glue;
            } else {
                $ret .= $item . $glue;
            }
        }

        $ret = substr($ret, 0, 0 - strlen($glue));

        return $ret;
    }
}