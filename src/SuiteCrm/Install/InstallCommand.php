<?php
/**
 * Created by Adam Jakab.
 * Date: 08/03/16
 * Time: 9.03
 */

namespace SuiteCrm\Install;

use SuiteCrm\Console\Command\Command;
use SuiteCrm\Console\Command\CommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class InstallCommand
 * @package SuiteCrm\Install
 */
class InstallCommand extends Command implements CommandInterface {
    const COMMAND_NAME = 'app:install';
    const COMMAND_DESCRIPTION = 'Install the SuiteCrm application';

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(NULL);
    }

    /**
     * Configure command
     */
    protected function configure() {
        $this->setName(static::COMMAND_NAME);
        $this->setDescription(static::COMMAND_DESCRIPTION);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return bool
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        parent::_execute($input, $output);
        $this->log("Starting command " . static::COMMAND_NAME . "...");
        $this->doPhpVersionCheck();
        $this->setupSugarServerValues();
        $this->setupSugarGlobals();
        $this->setupSugarSessionVars([]);
        $this->setupSugarLogger();
        $this->performInstallation();
        $this->log("Command " . static::COMMAND_NAME . " done.");
    }


    /**
     *
     */
    protected function performInstallation() {
        define('sugarEntry', TRUE);
        global $beanList;
        global $app_list_strings;
        global $timedate;

        if (is_file("config.php")) {
            $this->log("Removing stale configuration file.");
            unlink("config.php");
        }

        require_once(PROJECT_ROOT . '/sugar_version.php');
        require_once(PROJECT_ROOT . '/suitecrm_version.php');
        require_once(PROJECT_ROOT . '/include/utils.php');
        require_once(PROJECT_ROOT . '/install/install_utils.php');
        require_once(PROJECT_ROOT . '/install/install_defaults.php');
        require_once(PROJECT_ROOT . '/include/TimeDate.php');
        require_once(PROJECT_ROOT . '/include/Localization/Localization.php');
        require_once(PROJECT_ROOT . '/include/SugarTheme/SugarTheme.php');
        require_once(PROJECT_ROOT . '/include/utils/LogicHook.php');
        require_once(PROJECT_ROOT . '/data/SugarBean.php');
        require_once(PROJECT_ROOT . '/include/entryPoint.php');
        require_once(PROJECT_ROOT . '/modules/TableDictionary.php');

        $timedate = \TimeDate::getInstance();
        setPhpIniSettings();
        $locale = new \Localization();
        /** @var  string $suitecrm_version */
        $setup_sugar_version = $suitecrm_version;
        $install_script = TRUE;
        $current_language = 'en_us';

        $mod_strings = [];
        @include(PROJECT_ROOT . '/install/language/en_us.lang.php');

        $app_list_strings = return_app_list_strings_language($current_language);

        SystemChecker::runChecks();
        DatabaseChecker::runChecks();

        ini_set("output_buffering", "0");
        set_time_limit(3600);

        $this->log(str_repeat("-", 120));

        $trackerManager = \TrackerManager::getInstance();
        $trackerManager->pause();

        make_writable(PROJECT_ROOT . '/config.php');
        make_writable(PROJECT_ROOT . '/custom');
        recursive_make_writable(PROJECT_ROOT . '/modules');
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('custom_fields'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('dyn_lay'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('images'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('modules'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('layout'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('pdf'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('upload'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('upload/import'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('xml'));
        create_writable_dir(PROJECT_ROOT . '/' . sugar_cached('include/javascript'));
        recursive_make_writable(PROJECT_ROOT . '/' . sugar_cached('modules'));

        $cache_dir = sugar_cached("");
        $line_entry_format = "&nbsp&nbsp&nbsp&nbsp&nbsp<b>";
        $line_exit_format = "... &nbsp&nbsp</b>";
        /** @var  array $dictionary */
        $rel_dictionary = $dictionary; // sourced by modules/TableDictionary.php
        $render_table_close = "";
        $render_table_open = "";

        $setup_db_admin_password = $_SESSION['setup_db_admin_password'];
        $setup_db_admin_user_name = $_SESSION['setup_db_admin_user_name'];

        $setup_db_sugarsales_password = $setup_db_admin_password;
        $setup_db_sugarsales_user = $setup_db_admin_user_name;

        $setup_site_admin_user_name = $_SESSION['setup_site_admin_user_name'];
        $setup_site_admin_password = $_SESSION['setup_site_admin_password'];

        $setup_db_create_database = $_SESSION['setup_db_create_database'];
        $setup_db_create_sugarsales_user = $_SESSION['setup_db_create_sugarsales_user'];

        $setup_db_database_name = $_SESSION['setup_db_database_name'];
        $setup_db_drop_tables = $_SESSION['setup_db_drop_tables'];
        $setup_db_host_instance = $_SESSION['setup_db_host_instance'];
        $setup_db_port_num = $_SESSION['setup_db_port_num'];
        $setup_db_host_name = $_SESSION['setup_db_host_name'];
        $demoData = $_SESSION['demoData'];



        $setup_site_guid = (isset($_SESSION['setup_site_specify_guid'])
                            && $_SESSION['setup_site_specify_guid'] != '') ? $_SESSION['setup_site_guid'] : '';
        $setup_site_url = $_SESSION['setup_site_url'];
        $parsed_url = parse_url($setup_site_url);
        $setup_site_host_name = $parsed_url['host'];
        $setup_site_log_dir = isset($_SESSION['setup_site_custom_log_dir']) ? $_SESSION['setup_site_log_dir'] : '.';
        $setup_site_log_file = 'suitecrm.log';  // may be an option later
        $setup_site_session_path = isset($_SESSION['setup_site_custom_session_path']) ? $_SESSION['setup_site_session_path'] : '';
        $setup_site_log_level = 'fatal';


        $GLOBALS['cache_dir'] = $cache_dir;
        $GLOBALS['mod_strings'] = $mod_strings;
        $GLOBALS['setup_site_log_level'] = $setup_site_log_level;
        $GLOBALS['create_default_user'] = false;

        $GLOBALS['setup_db_host_name'] = $setup_db_host_name;
        $GLOBALS['setup_db_host_instance'] = $setup_db_host_instance;
        $GLOBALS['setup_db_port_num'] = $setup_db_port_num;

        //some of these username/pwd pairs must be unused ... but which?
        $GLOBALS['setup_db_admin_user_name'] = $setup_db_admin_user_name;
        $GLOBALS['setup_db_admin_password'] = $setup_db_admin_password;

        $GLOBALS['setup_site_admin_user_name'] = $setup_site_admin_user_name;
        $GLOBALS['setup_site_admin_password'] = $setup_site_admin_password;

        $GLOBALS['setup_db_sugarsales_user'] = $setup_db_sugarsales_user;
        $GLOBALS['setup_db_sugarsales_password'] = $setup_db_sugarsales_password;

        $GLOBALS['setup_db_database_name'] = $setup_db_database_name;

        $GLOBALS['setup_site_host_name'] = $setup_site_host_name;
        $GLOBALS['setup_site_log_dir'] = $setup_site_log_dir;
        $GLOBALS['setup_site_log_file'] = $setup_site_log_file;
        $GLOBALS['setup_site_session_path'] = $setup_site_session_path;

        $GLOBALS['setup_site_guid'] = $setup_site_guid;
        $GLOBALS['setup_site_url'] = $setup_site_url;
        $GLOBALS['setup_sugar_version'] = $setup_sugar_version;

        //global $sugar_config;

        //install/install_utils.php:724
        $this->log("Calling: handleSugarConfig()");
        $bottle = handleSugarConfig();

        $this->log("Calling: handleWebConfig()");
        handleWebConfig();

        $this->log("Calling: handleHtaccess()");
        handleHtaccess();


        //We are NOT creating database (nor db user) - do that yourself!
        $this->log("Calling: handleDbCharsetCollation()");
        installerHook('pre_handleDbCharsetCollation');
        handleDbCharsetCollation();
        installerHook('post_handleDbCharsetCollation');

        /**
         * @var array  $beanFiles
         * @var string $beanName
         * @var string $beanFile
         */
        foreach ($beanFiles as $beanName => $beanFile) {
            $this->log("Requiring bean[$beanName] file: $beanFile", "info");
            require_once($beanFile);
        }


        global $db;
        /** @ var \DBManager $db */
        $db = \DBManagerFactory::getInstance();

        $startTime = microtime(TRUE);
        $focus = 0;
        $processed_tables = []; // for keeping track of the tables we have worked on
        $empty = '';
        $new_tables = 1; // is there ever a scenario where we DON'T create the admin user?
        $new_config = 1;
        $new_report = 1;
        $nonStandardModules = [];


        $this->log("cleaning bean vardefs");
        //$this->log("BEAN-LIST: " . json_encode($beanList));
        \VardefManager::clearVardef();


        /**
         * loop through all the Beans and create their tables
         */
        $this->log(str_repeat("-", 120));
        $this->log("Creating database tables...");

        installerHook('pre_createAllModuleTables');
        foreach ($beanFiles as $beanName => $beanFile) {

            $doNotInitModules = [
                'Scheduler',
                'SchedulersJob',
                'ProjectTask',
                'jjwg_Maps',
                'jjwg_Address_Cache',
                'jjwg_Areas',
                'jjwg_Markers'
            ];

            /** @var \SugarBean $focus */
            if (in_array($beanName, $doNotInitModules)) {
                $focus = new $beanName(FALSE);
            }
            else {
                $focus = new $beanName();
            }

            if ($beanName == 'Configurator') {
                continue;
            }

            $table_name = $focus->table_name;

            $this->log("processing Module: " . $beanName . "(" . $focus->table_name . ")");

            // check to see if we have already setup this table
            if (!in_array($table_name, $processed_tables)) {
                if (!file_exists("modules/" . $focus->module_dir . "/vardefs.php")) {
                    continue;
                }
                if (!in_array($beanName, $nonStandardModules)) {
                    require_once("modules/" . $focus->module_dir . "/vardefs.php"); // load up $dictionary
                    if ($dictionary[$focus->object_name]['table'] == 'does_not_exist') {
                        continue; // support new vardef definitions
                    }
                }
                else {
                    continue; //no further processing needed for ignored beans.
                }

                // table has not been setup...we will do it now and remember that
                $processed_tables[] = $table_name;

                $focus->db->database = $db->database; // set db connection so we do not need to reconnect

                if ($setup_db_drop_tables) {
                    drop_table_install($focus);
                    //$this->log("dropping table: ".$focus->table_name);
                }

                if (create_table_if_not_exist($focus)) {
                    //$this->log("creating table: ". $focus->table_name );
                    if ($beanName == "User") {
                        $new_tables = 1;
                    }
                    if ($beanName == "Administration") {
                        $new_config = 1;
                    }
                }
                //$this->log("creating Relationship Meta for ".$focus->getObjectName());
                installerHook('pre_createModuleTable', array('module' => $focus->getObjectName()));
                \SugarBean::createRelationshipMeta($focus->getObjectName(), $db, $table_name, $empty, $focus->module_dir);
                installerHook('post_createModuleTable', array('module' => $focus->getObjectName()));
            }
        }
        installerHook('post_createAllModuleTables');

        /**
         * loop through all Relationships and create their tables
         */
        $this->log(str_repeat("-", 120));
        $this->log("Creating relationships...");
        ksort($rel_dictionary);
        foreach ($rel_dictionary as $rel_name => $rel_data) {
            $table = $rel_data['table'];

            $this->log("processing Relationship: " . $rel_name . "(" . $table . ")");

            if ($setup_db_drop_tables) {
                if ($db->tableExists($table)) {
                    $db->dropTableName($table);
                }
            }

            if (!$db->tableExists($table)) {
                $fields = isset($rel_data['fields']) ? $rel_data['fields'] : [];
                $indices = isset($rel_data['indices']) ? $rel_data['indices'] : [];
                $db->createTableParams($table, $fields, $indices);
            }

            \SugarBean::createRelationshipMeta($rel_name, $db, $table, $rel_dictionary, '');
        }

        /**
         * Create Default Settings
         */
        $this->log(str_repeat("-", 120));
        $this->log("Creating default settings...");
        installerHook('pre_createDefaultSettings');
        if ($new_config) {
            /** @var string $sugar_db_version - loaded from sugar_version.php*/
            $GLOBALS['sugar_db_version'] =  $sugar_db_version;
            insert_default_settings();
        }
        installerHook('post_createDefaultSettings');

        /**
         * Create Administrator User
         */
        $this->log(str_repeat("-", 120));
        $this->log("Creating admin user...");
        installerHook('pre_createUsers');
        if ($new_tables) {
            create_default_users();
        } else {
            //@todo: CHECK ME! - cannot find methods: setUserName, setUserPassword
            //$db->setUserName($setup_db_sugarsales_user);
            //$db->setUserPassword($setup_db_sugarsales_password);
            set_admin_password($setup_site_admin_password);
        }
        installerHook('post_createUsers');


        /**
         * Rebuild Shedulers
         */
        $this->log(str_repeat("-", 120));
        $this->log("Rebuilding schedulers...");
        $scheduler = new \Scheduler();
        installerHook('pre_createDefaultSchedulers');
        $scheduler->rebuildDefaultSchedulers();
        installerHook('post_createDefaultSchedulers');

        /**
         * Update upgrade history
         */
        if(isset($_SESSION['INSTALLED_LANG_PACKS']) &&
           is_array($_SESSION['INSTALLED_LANG_PACKS']) &&
           !empty($_SESSION['INSTALLED_LANG_PACKS'])) {
            $this->log(str_repeat("-", 120));
            $this->log("Updating upgrade history...");
            updateUpgradeHistory();
        }


        /**
         *  Enable Sugar Feeds
         */
        $this->log(str_repeat("-", 120));
        $this->log("Enabling Sugar Feeds...");
        enableSugarFeeds();


        /**
         * Handle Sugar Versions
         */
        $this->log(str_repeat("-", 120));
        $this->log("Handling Sugar Versions...");
        require_once(PROJECT_ROOT . '/modules/Versions/InstallDefaultVersions.php');


        /**
         * Advanced Password Seeds
         */
        $this->log(str_repeat("-", 120));
        $this->log("Handling Advanced Password Seeds...");
        include(PROJECT_ROOT . '/install/seed_data/Advanced_Password_SeedData.php');

        /**
         * Advanced Password Seeds
         */
        $this->log(str_repeat("-", 120));
        $this->log("Handling Advanced Password Seeds...");
        include(PROJECT_ROOT . '/install/seed_data/Advanced_Password_SeedData.php');

        /**
         * Administration Variables
         */
        $this->log(str_repeat("-", 120));
        $this->log("Handling Administration Variables...");
        if( isset($_SESSION['setup_site_sugarbeet_automatic_checks']) &&
            $_SESSION['setup_site_sugarbeet_automatic_checks'] == true) {
            set_CheckUpdates_config_setting('automatic');
        }else{
            set_CheckUpdates_config_setting('manual');
        }
        if(!empty($_SESSION['setup_system_name'])){
            $admin = new \Administration();
            $admin->saveSetting('system','name',$_SESSION['setup_system_name']);
        }

        /**
         * Setting Default Tabs
         */
        $this->log(str_repeat("-", 120));
        $this->log("Setting Default Tabs...");
        // Bug 28601 - Set the default list of tabs to show
        $enabled_tabs = array();
        $enabled_tabs[] = 'Home';
        $enabled_tabs[] = 'Accounts';
        $enabled_tabs[] = 'Contacts';
        $enabled_tabs[] = 'Opportunities';
        $enabled_tabs[] = 'Leads';
        $enabled_tabs[] = 'AOS_Quotes';
        $enabled_tabs[] = 'Calendar';
        $enabled_tabs[] = 'Documents';
        $enabled_tabs[] = 'Emails';
        $enabled_tabs[] = 'Campaigns';
        $enabled_tabs[] = 'Calls';
        $enabled_tabs[] = 'Meetings';
        $enabled_tabs[] = 'Tasks';
        $enabled_tabs[] = 'Notes';
        $enabled_tabs[] = 'AOS_Invoices';
        $enabled_tabs[] = 'AOS_Contracts';
        $enabled_tabs[] = 'Cases';
        $enabled_tabs[] = 'Prospects';
        $enabled_tabs[] = 'ProspectLists';
        $enabled_tabs[] = 'Project';
        $enabled_tabs[] = 'AM_ProjectTemplates';
        $enabled_tabs[] = 'AM_TaskTemplates';
        $enabled_tabs[] = 'FP_events';
        $enabled_tabs[] = 'FP_Event_Locations';
        $enabled_tabs[] = 'AOS_Products';
        $enabled_tabs[] = 'AOS_Product_Categories';
        $enabled_tabs[] = 'AOS_PDF_Templates';
        $enabled_tabs[] = 'jjwg_Maps';
        $enabled_tabs[] = 'jjwg_Markers';
        $enabled_tabs[] = 'jjwg_Areas';
        $enabled_tabs[] = 'jjwg_Address_Cache';
        $enabled_tabs[] = 'AOR_Reports';
        $enabled_tabs[] = 'AOW_WorkFlow';
        $enabled_tabs[] = 'AOK_KnowledgeBase';
        $enabled_tabs[] = 'AOK_Knowledge_Base_Categories';

        installerHook('pre_setSystemTabs');
        require_once(PROJECT_ROOT . '/modules/MySettings/TabController.php');
        $tabs = new \TabController();
        $tabs->set_system_tabs($enabled_tabs);
        installerHook('post_setSystemTabs');

        /**
         * Modules Post Install
         */
        $this->log(str_repeat("-", 120));
        $this->log("Modules Post Install...");
        include_once(PROJECT_ROOT . '/install/suite_install/suite_install.php');
        post_install_modules();

        /**
         * @todo: 6 million warnings & errors - CHECK ME!
         * Install Demo Data
         */
        if(isset($_SESSION['demoData']) &&  $_SESSION['demoData'] === true){
            $this->log(str_repeat("-", 120));
            $this->log("Installing Demo Data...");

            installerHook('pre_installDemoData');
            global $current_user;
            $current_user = new \User();
            $current_user->retrieve(1);
            include(PROJECT_ROOT . '/install/populateSeedData.php');
            installerHook('post_installDemoData');
        }


        /**
         * Save Administration Configuration
         */
        $this->log(str_repeat("-", 120));
        $this->log("Saving Administration Configuration...");
        $varStack['GLOBALS'] = $GLOBALS;
        $varStack['defined_vars'] = get_defined_vars();
        $_REQUEST = array_merge($_REQUEST, $_SESSION);
        $_POST = array_merge($_POST, $_SESSION);
        $admin = new \Administration();
        $admin->saveSetting('system','adminwizard',1);
        $admin->saveConfig();


        /**
         * Save Global Configuration
         */
        $this->log(str_repeat("-", 120));
        $this->log("Saving Global Configuration...");
        $configurator = new \Configurator();
        $configurator->populateFromPost();
        // add local settings to config overrides
        if(!empty($_SESSION['default_date_format'])) $sugar_config['default_date_format'] = $_SESSION['default_date_format'];
        if(!empty($_SESSION['default_time_format'])) $sugar_config['default_date_format'] = $_SESSION['default_time_format'];
        if(!empty($_SESSION['default_language'])) $sugar_config['default_language'] = $_SESSION['default_language'];
        if(!empty($_SESSION['default_locale_name_format'])) $sugar_config['default_locale_name_format'] = $_SESSION['default_locale_name_format'];
        $configurator->saveConfig();


        /**
         * @todo: check and remove this
         * Fix Currency - Bug 37310
         */
        $this->log(str_repeat("-", 120));
        $this->log("Fix Currency - Bug 37310...");
        $currency = new \Currency();
        $currency->retrieve($currency->retrieve_id_by_name($_REQUEST['default_currency_name']));
        if (!empty($currency->id)
             && $currency->symbol == $_REQUEST['default_currency_symbol']
             && $currency->iso4217 == $_REQUEST['default_currency_iso4217'] ) {
            $currency->deleted = 1;
            $currency->save();
        }


        /**
         * Save User
         * old note: set all of these default parameters since the Users save action
         * will undo the defaults otherwise
         */
        $this->log(str_repeat("-", 120));
        $this->log("Saving Admin User...");
        $current_user = new \User();
        $current_user->retrieve(1);
        $current_user->is_admin = '1';
        $sugar_config = get_sugar_config_defaults();

        // set locale settings
        if(isset($_REQUEST['timezone']) && $_REQUEST['timezone']) {
            $current_user->setPreference('timezone', $_REQUEST['timezone']);
        }
        $_POST['dateformat'] = $_REQUEST['default_date_format'];
        $_POST['record'] = $current_user->id;
        $_POST['is_admin'] = ( $current_user->is_admin ? 'on' : '' );
        $_POST['use_real_names'] = true;
        $_POST['reminder_checked'] = '1';
        $_POST['reminder_time'] = 1800;
        $_POST['email_reminder_time'] = 3600;
        $_POST['mailmerge_on'] = 'on';
        $_POST['receive_notifications'] = $current_user->receive_notifications;
        installLog('DBG: SugarThemeRegistry::getDefault');
        $_POST['user_theme'] = (string) \SugarThemeRegistry::getDefault();
        require(PROJECT_ROOT . '/modules/Users/Save.php');

        // restore superglobals and vars
        $GLOBALS = $varStack['GLOBALS'];
        foreach($varStack['defined_vars'] as $__key => $__value) {
            $$__key = $__value;
        }

        $endTime = microtime(true);
        $deltaTime = $endTime - $startTime;

        $this->log(str_repeat("-", 120));
        $this->log("Calling Post Install Modules Hook...");
        installerHook('post_installModules');

        $this->log(str_repeat("-", 120));
        $this->log(str_repeat("-", 120));
        //$this->log("SESSION VARS: " . json_encode($_SESSION));
        $this->log("Installation complete.");
    }


    /**
     * Set up our own LoggerManager for the installation
     */
    protected function setupSugarLogger() {
        $GLOBALS['log'] = $this->loggerManager;
    }

    /**
     * Setup Session variables prior to executing installation
     *
     * @param array $data
     */
    protected function setupSugarSessionVars($data) {
        if ((function_exists('session_status') && session_status() == PHP_SESSION_NONE) || session_id() == '') {
            session_start();
        }

        $databaseConfigData = [
            /* DATABASE */
            'setup_db_type' => 'mysql',
            'setup_db_admin_user_name' => 'jack',
            'setup_db_admin_password' => '02203505',
            'setup_db_database_name' => 'suitecrm_bradipo_travis',
            'setup_db_host_name' => 'localhost',
            'setup_db_port_num' => '3306',
            'setup_db_host_instance' => '',
            'setup_db_manager' => '',
            'setup_db_create_database' => TRUE,
            'setup_db_drop_tables' => TRUE,
            'setup_db_username_is_privileged' => TRUE,
            'setup_db_create_sugarsales_user' => FALSE,
            //            'setup_db_pop_demo_data' => true,

            /*SITE DEFAULT ADMIN USER*/
            'setup_site_admin_user_name' => 'admin',
            'setup_site_admin_password' => 'admin',
            /*FTS SUPPORT*/
            'setup_fts_type' => '',
            'setup_fts_host' => '',
            'setup_fts_port' => '',
            /* INTERNAL */
            'setup_system_name' => 'SuiteCRM Travis Build',
            'setup_site_url' => 'http://localhost',
            'host' => 'localhost',
            'dbUSRData' => 'create',
            'demoData' => false,
            'default_date_format' => 'Y-m-d',
            'default_time_format' => 'H:i',
            'default_decimal_seperator' => '.',
            'default_export_charset' => 'ISO-8859-1',
            'export_delimiter' => ',',
            'default_language' => 'en_us',
            'default_locale_name_format' => 's f l',
            'default_number_grouping_seperator' => ',',
            'setup_site_sugarbeet_automatic_checks' => TRUE
        ];

        $data = array_merge_recursive($data, $databaseConfigData);
        $_SESSION = array_merge_recursive($_SESSION, $data);
    }

    /**
     * Setup Globals prior to requiring Sugar application files
     */
    protected function setupSugarGlobals() {
        $GLOBALS['installing'] = TRUE;
        define('SUGARCRM_IS_INSTALLING', $GLOBALS['installing']);
        $GLOBALS['sql_queries'] = 0;
    }

    /**
     * Mostly for avoiding undefined index notices
     */
    protected function setupSugarServerValues() {
        $_SERVER['SERVER_SOFTWARE'] = NULL;
    }

    /**
     * @throws \Exception
     */
    protected function doPhpVersionCheck() {
        if (version_compare(phpversion(), '5.2.0') < 0) {
            throw new \Exception('Minimum PHP version required is 5.2.0.  You are using PHP version  ' . phpversion());
        }
    }
}