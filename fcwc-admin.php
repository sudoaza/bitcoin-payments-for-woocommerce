<?php
/*
Faircoin Payments for WooCommerce
https://fair-coin.org/
*/

// Include everything
include (dirname(__FILE__) . '/fcwc-include-all.php');

//===========================================================================
// Global vars.

global $g_FCWC__plugin_directory_url;
$g_FCWC__plugin_directory_url = plugins_url ('', __FILE__);

global $g_FCWC__cron_script_url;
$g_FCWC__cron_script_url = $g_FCWC__plugin_directory_url . '/fcwc-cron.php';

//===========================================================================

//===========================================================================
// Global default settings
global $g_FCWC__config_defaults;
$g_FCWC__config_defaults = array (

   // ------- Hidden constants
// 'supported_currencies_arr'             =>  array ('USD', 'AUD', 'CAD', 'CHF', 'CNY', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'PLN', 'RUB', 'SEK', 'SGD', 'THB'), // Not used right now.
   'database_schema_version'              =>  1.2,
   'assigned_address_expires_in_mins'     =>  4*60,   // 4 hours to pay for order and receive necessary number of confirmations.
   'funds_received_value_expires_in_mins' =>  '10',		// 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
   'starting_index_for_new_fair_addresses' =>  '2',    // Generate new addresses for the wallet starting from this index.
   'max_blockchains_api_failures'         =>  '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
   'max_unusable_generated_addresses'     =>  '20',   // Return error after this number of unusable (non-empty) faircoin addresses were sequentially generated
   'blockchain_api_timeout_secs'          =>  '20',   // Connection and request timeouts for curl operations dealing with blockchain requests.
   'exchange_rate_api_timeout_secs'       =>  '10',   // Connection and request timeouts for curl operations dealing with exchange rate API requests.
   'soft_cron_job_schedule_name'          =>  'minutes_1',   // WP cron job frequency
   'delete_expired_unpaid_orders'         =>  '1',   // Automatically delete expired, unpaid orders from WooCommerce->Orders database
   'reuse_expired_addresses'              =>  '1',   // True - may reduce anonymouty of store customers (someone may click/generate bunch of fake orders to list many addresses that in a future will be used by real customers).
                                                      // False - better anonymouty but may leave many addresses in wallet unused (and hence will require very high 'gap limit') due to many unpaid order clicks.
                                                      //        In this case it is recommended to regenerate new wallet after 'gap limit' reaches 1000.
   'max_unused_addresses_buffer'          =>  10,     // Do not pre-generate more than these number of unused addresses. Pregeneration is done only by hard cron job or manually at plugin settings.
   'cache_exchange_rates_for_minutes'			=>	10,			// Cache exchange rate for that number of minutes without re-calling exchange rate API's.
// 'soft_cron_max_loops_per_run'					=>	2,			// NOT USED. Check up to this number of assigned faircoin addresses per soft cron run. Each loop involves number of DB queries as well as API query to blockchain - and this may slow down the site.
   'elists'																=>	array(),

   // ------- General Settings
   'license_key'                          =>  'UNLICENSED',
   'api_key'                              =>  substr(md5(microtime()), -16),
   'delete_db_tables_on_uninstall'        =>  '0',
   'enable_soft_cron_job'                 =>  '1',    // Enable "soft" Wordpress-driven cron jobs.

   // ------- Copy of $this->settings of 'FCWC_Faircoin' class.
   'gateway_settings'                     =>  array('confirmations' => 6),

   // ------- Special settings
   'exchange_rates'                       =>  array('EUR' => array('method|type' => array('time-last-checked' => 0, 'exchange_rate' => 1), 'GBP' => array())),
   );
//===========================================================================

//===========================================================================
// These are coming from plugin-specific table.
function FCWC__get_persistent_settings ($key=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return array();
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'fcwc_persistent_settings';
  $sql_query = "SELECT * FROM `$persistent_settings_table_name` WHERE `id` = '1';";

  $row = $wpdb->get_row($sql_query, ARRAY_A);
  if ($row)
  {
    $settings = @unserialize($row['settings']);
    if ($key)
      return $settings[$key];
    else
      return $settings;
  }
  else
    return array();
}
//===========================================================================

//===========================================================================
function FCWC__update_persistent_settings ($fcwc_use_these_settings_array=false)
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////
  global $wpdb;

  $persistent_settings_table_name = $wpdb->prefix . 'fcwc_persistent_settings';

  if (!$fcwc_use_these_settings)
    $fcwc_use_these_settings = array();

  $db_ready_settings = FCWC__safe_string_escape (serialize($fcwc_use_these_settings_array));

  $wpdb->update($persistent_settings_table_name, array('settings' => $db_ready_settings), array('id' => '1'), array('%s'));
}
//===========================================================================

//===========================================================================
// Wipe existing table's contents and recreate first record with all defaults.
function FCWC__reset_all_persistent_settings ()
{
////// PERSISTENT SETTINGS CURRENTLY UNUSED
return;
//////

  global $wpdb;
  global $g_FCWC__config_defaults;

  $persistent_settings_table_name = $wpdb->prefix . 'fcwc_persistent_settings';

  $initial_settings = FCWC__safe_string_escape (serialize($g_FCWC__config_defaults));

  $query = "TRUNCATE TABLE `$persistent_settings_table_name`;";
  $wpdb->query ($query);

  $query = "INSERT INTO `$persistent_settings_table_name`
      (`id`, `settings`)
        VALUES
      ('1', '$initial_settings');";
  $wpdb->query ($query);
}
//===========================================================================

//===========================================================================
function FCWC__get_settings ($key=false)
{
  global   $g_FCWC__plugin_directory_url;
  global   $g_FCWC__config_defaults;

  $fcwc_settings = get_option (FCWC_SETTINGS_NAME);
  if (!is_array($fcwc_settings))
    $fcwc_settings = array();


  if ($key)
    return (@$fcwc_settings[$key]);
  else
    return ($fcwc_settings);
}
//===========================================================================

//===========================================================================
function FCWC__update_settings ($fcwc_use_these_settings=false, $also_update_persistent_settings=false)
{
   if ($fcwc_use_these_settings)
      {
      if ($also_update_persistent_settings)
        FCWC__update_persistent_settings ($fcwc_use_these_settings);

      update_option (FCWC_SETTINGS_NAME, $fcwc_use_these_settings);
      return;
      }

   global   $g_FCWC__config_defaults;

   // Load current settings and overwrite them with whatever values are present on submitted form
   $fcwc_settings = FCWC__get_settings();

   foreach ($g_FCWC__config_defaults as $k=>$v)
      {
      if (isset($_POST[$k]))
         {
         if (!isset($fcwc_settings[$k]))
            $fcwc_settings[$k] = ""; // Force set to something.
         FCWC__update_individual_fcwc_setting ($fcwc_settings[$k], $_POST[$k]);
         }
      // If not in POST - existing will be used.
      }

   //---------------------------------------
   // Validation
   //if ($fcwc_settings['aff_payout_percents3'] > 90)
   //   $fcwc_settings['aff_payout_percents3'] = "90";
   //---------------------------------------

  if ($also_update_persistent_settings)
    FCWC__update_persistent_settings ($fcwc_settings);

  update_option (FCWC_SETTINGS_NAME, $fcwc_settings);
}
//===========================================================================

//===========================================================================
// Takes care of recursive updating
function FCWC__update_individual_fcwc_setting (&$fcwc_current_setting, $fcwc_new_setting)
{
   if (is_string($fcwc_new_setting))
      $fcwc_current_setting = FCWC__stripslashes ($fcwc_new_setting);
   else if (is_array($fcwc_new_setting))  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
      {
      // Need to do recursive
      foreach ($fcwc_new_setting as $k=>$v)
         {
         if (!isset($fcwc_current_setting[$k]))
            $fcwc_current_setting[$k] = "";   // If not set yet - force set it to something.
         FCWC__update_individual_fcwc_setting ($fcwc_current_setting[$k], $v);
         }
      }
   else
      $fcwc_current_setting = $fcwc_new_setting;
}
//===========================================================================

//===========================================================================
//
// Reset settings only for one screen
function FCWC__reset_partial_settings ($also_reset_persistent_settings=false)
{
   global   $g_FCWC__config_defaults;

   // Load current settings and overwrite ones that are present on submitted form with defaults
   $fcwc_settings = FCWC__get_settings();

   foreach ($_POST as $k=>$v)
      {
      if (isset($g_FCWC__config_defaults[$k]))
         {
         if (!isset($fcwc_settings[$k]))
            $fcwc_settings[$k] = ""; // Force set to something.
         FCWC__update_individual_fcwc_setting ($fcwc_settings[$k], $g_FCWC__config_defaults[$k]);
         }
      }

  update_option (FCWC_SETTINGS_NAME, $fcwc_settings);

  if ($also_reset_persistent_settings)
    FCWC__update_persistent_settings ($fcwc_settings);
}
//===========================================================================

//===========================================================================
function FCWC__reset_all_settings ($also_reset_persistent_settings=false)
{
  global   $g_FCWC__config_defaults;

  update_option (FCWC_SETTINGS_NAME, $g_FCWC__config_defaults);

  if ($also_reset_persistent_settings)
    FCWC__reset_all_persistent_settings ();
}
//===========================================================================

//===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function FCWC__stripslashes (&$val)
{
   if (is_string($val))
      return (stripslashes($val));
   if (!is_array($val))
      return $val;

   foreach ($val as $k=>$v)
      {
      $val[$k] = FCWC__stripslashes ($v);
      }

   return $val;
}
//===========================================================================

//===========================================================================
/*
    ----------------------------------
    : Table 'fair_addresses' :
    ----------------------------------
      status                "unused"      - never been used address with last known zero balance
                            "assigned"    - order was placed and this address was assigned for payment
                            "revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
                            "used"        - order was placed and this address and payment in full was received. Address will not be used again.
                            "xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
                            "unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function FCWC__create_database_tables ($fcwc_settings)
{
  global $wpdb;

  $fcwc_settings = FCWC__get_settings();
  $must_update_settings = false;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'fcwc_persistent_settings';
  ///$electrum_wallets_table_name          = $wpdb->prefix . 'fcwc_electrum_wallets';
  $fair_addresses_table_name             = $wpdb->prefix . 'fcwc_fair_addresses';

  if($wpdb->get_var("SHOW TABLES LIKE '$fair_addresses_table_name'") != $fair_addresses_table_name)
      $b_first_time = true;
  else
      $b_first_time = false;

 //----------------------------------------------------------
 // Create tables
  /// NOT NEEDED YET
  /// $query = "CREATE TABLE IF NOT EXISTS `$persistent_settings_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `settings` text,
  ///   PRIMARY KEY  (`id`)
  ///   );";
  /// $wpdb->query ($query);

  /// $query = "CREATE TABLE IF NOT EXISTS `$electrum_wallets_table_name` (
  ///   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  ///   `master_public_key` varchar(255) NOT NULL,
  ///   PRIMARY KEY  (`id`),
  ///   UNIQUE KEY  `master_public_key` (`master_public_key`)
  ///   );";
  /// $wpdb->query ($query);

  $query = "CREATE TABLE IF NOT EXISTS `$fair_addresses_table_name` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `fair_address` char(36) NOT NULL,
    `origin_id` char(64) NOT NULL DEFAULT '',
    `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
    `status` char(16)  NOT NULL DEFAULT 'unknown',
    `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
    `assigned_at` bigint(20) NOT NULL DEFAULT '0',
    `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
    `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
    `address_meta` text NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `fair_address` (`fair_address`),
    KEY `index_in_wallet` (`index_in_wallet`),
    KEY `origin_id` (`origin_id`),
    KEY `status` (`status`)
    );";
  $wpdb->query ($query);
  //----------------------------------------------------------

	// upgrade fcwc_fair_addresses table, add additional indexes
  if (!$b_first_time)
  {
    $version = floatval($fcwc_settings['database_schema_version']);

    if ($version < 1.1)
    {

      $query = "ALTER TABLE `$fair_addresses_table_name` ADD INDEX `origin_id` (`origin_id` ASC) , ADD INDEX `status` (`status` ASC)";
      $wpdb->query ($query);
      $fcwc_settings['database_schema_version'] = 1.1;
      $must_update_settings = true;
    }

		if ($version < 1.2)
		{

      $query = "ALTER TABLE `$fair_addresses_table_name` DROP INDEX `index_in_wallet`, ADD INDEX `index_in_wallet` (`index_in_wallet` ASC)";
      $wpdb->query ($query);
      $fcwc_settings['database_schema_version'] = 1.2;
      $must_update_settings = true;
    }
  }

  if ($must_update_settings)
  {
	  FCWC__update_settings ($fcwc_settings);
	}

  //----------------------------------------------------------
  // Seed DB tables with initial set of data
  /* PERSISTENT SETTINGS CURRENTLY UNUNSED
  if ($b_first_time || !is_array(FCWC__get_persistent_settings()))
  {
    // Wipes table and then creates first record and populate it with defaults
    FCWC__reset_all_persistent_settings();
  }
  */
   //----------------------------------------------------------
}
//===========================================================================

//===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function FCWC__delete_database_tables ()
{
  global $wpdb;

  ///$persistent_settings_table_name       = $wpdb->prefix . 'fcwc_persistent_settings';
  ///$electrum_wallets_table_name          = $wpdb->prefix . 'fcwc_electrum_wallets';
  $fair_addresses_table_name    = $wpdb->prefix . 'fcwc_fair_addresses';

  ///$wpdb->query("DROP TABLE IF EXISTS `$persistent_settings_table_name`");
  ///$wpdb->query("DROP TABLE IF EXISTS `$electrum_wallets_table_name`");
  $wpdb->query("DROP TABLE IF EXISTS `$fair_addresses_table_name`");
}
//===========================================================================

