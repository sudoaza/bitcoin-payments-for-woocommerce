<?php
/*


Plugin Name: Faircoin Payments for WooCommerce
Plugin URI: http://www.faircoin.org/
Description: Faircoin Payments for WooCommerce plugin allows you to accept
payments in faircoins for physical and digital products at your WooCommerce-powered online store.
Version: 1.0
Author: Faircoop
Author URI: https://fair.coop
License: GNU General Public License 3.0 (GPL) http://www.gnu.org/licenses/gpl.html

*/


// Include everything
include (dirname(__FILE__) . '/fcwc-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'FCWC_create_menu' );

register_activation_hook(__FILE__,          'FCWC_activate');
register_deactivation_hook(__FILE__,        'FCWC_deactivate');
register_uninstall_hook(__FILE__,           'FCWC_uninstall');

add_filter ('cron_schedules',               'FCWC__add_custom_scheduled_intervals');
add_action ('FCWC_cron_action',             'FCWC_cron_job_worker');     // Multiple functions can be attached to 'FCWC_cron_action' action

FCWC_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function FCWC_activate()
{
    global  $g_FCWC__config_defaults;

    $fcwc_default_options = $g_FCWC__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $fcwc_settings = FCWC__get_settings ();

    foreach ($fcwc_settings as $key=>$value)
    	$fcwc_default_options[$key] = $value;

    update_option (FCWC_SETTINGS_NAME, $fcwc_default_options);

    // Re-get new settings.
    $fcwc_settings = FCWC__get_settings ();

    // Create necessary database tables if not already exists...
    FCWC__create_database_tables ($fcwc_settings);

    //----------------------------------
    // Setup cron jobs

    if ($fcwc_settings['enable_soft_cron_job'] && !wp_next_scheduled('FCWC_cron_action'))
    {
    	$cron_job_schedule_name = strpos($_SERVER['HTTP_HOST'], 'ttt.com')===FALSE ? $fcwc_settings['soft_cron_job_schedule_name'] : 'seconds_30';
    	wp_schedule_event(time(), $cron_job_schedule_name, 'FCWC_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function FCWC__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));     // For testing only.
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function FCWC_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

   //----------------------------------
   // Clear cron jobs
   wp_clear_scheduled_hook ('FCWC_cron_action');
   //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function FCWC_uninstall ()
{
    $fcwc_settings = FCWC__get_settings();

    if ($fcwc_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(FCWC_SETTINGS_NAME);

        // delete all DB tables and data.
        FCWC__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function FCWC_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo Faircoin', FCWC_I18N_DOMAIN),                    // Page title
        __('Faircoin', FCWC_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'fcwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'FCWC__render_general_settings_page',                   // Function
        plugins_url('/images/faircoin_16x.png', __FILE__)                // Icon URL
        );

    add_submenu_page (
        'fcwc-settings',                                        // Parent
        __("WooCommerce Faircoin Payments Gateway", FCWC_I18N_DOMAIN),                   // Page title
        __("General Settings", FCWC_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'fcwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'FCWC__render_general_settings_page'                    // Function
        );

    add_submenu_page (
        'fcwc-settings',                                        // Parent
        __("Faircoin Plugin Advanced Settings", FCWC_I18N_DOMAIN),       // Page title
        __("Advanced Settings", FCWC_I18N_DOMAIN),                // Menu title
        'administrator',                                        // Capability
        'fcwc-settings-advanced',                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'FCWC__render_advanced_settings_page'            // Function
        );
}
//===========================================================================

//===========================================================================
// load language files
function FCWC_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(FCWC_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

