<?php

/**
 * @file classes/install/Installer.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Installer
 * @ingroup install
 *
 * @brief Base class for install and upgrade scripts.
 */

namespace PKP\install;

use adoSchema;
use APP\core\Application;
use APP\file\LibraryFileManager;
use APP\i18n\AppLocale;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\cache\CacheManager;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\DAOResultFactory;
use PKP\db\DBDataXMLParser;
use PKP\file\FileManager;
use PKP\filter\FilterHelper;
use PKP\notification\PKPNotification;
use PKP\plugins\HookRegistry;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\site\Version;

use PKP\site\VersionCheck;
use PKP\site\VersionDAO;
use PKP\xml\PKPXMLParser;

class Installer
{
    // Installer error codes
    public const INSTALLER_ERROR_GENERAL = 1;
    public const INSTALLER_ERROR_DB = 2;

    public const INSTALLER_DATA_DIR = 'dbscripts/xml';
    public const INSTALLER_DEFAULT_LOCALE = 'en_US';

    /** @var string descriptor path (relative to INSTALLER_DATA_DIR) */
    public $descriptor;

    /** @var boolean indicates if a plugin is being installed (thus modifying the descriptor path) */
    public $isPlugin;

    /** @var array installation parameters */
    public $params;

    /** @var Version currently installed version */
    public $currentVersion;

    /** @var Version version after installation */
    public $newVersion;

    /** @var string default locale */
    public $locale;

    /** @var string available locales */
    public $installedLocales;

    /** @var DBDataXMLParser database data parser */
    public $dataXMLParser;

    /** @var array installer actions to be performed */
    public $actions;

    /** @var array SQL statements for database installation */
    public $sql;

    /** @var array installation notes */
    public $notes;

    /** @var string contents of the updated config file */
    public $configContents;

    /** @var boolean indicating if config file was written or not */
    public $wroteConfig;

    /** @var int error code (null | INSTALLER_ERROR_GENERAL | INSTALLER_ERROR_DB) */
    public $errorType;

    /** @var string the error message, if an installation error has occurred */
    public $errorMsg;

    /** @var Logger logging object */
    public $logger;

    /** @var array List of migrations executed already */
    public $migrations = [];

    /**
     * Constructor.
     *
     * @param $descriptor string descriptor path
     * @param $params array installer parameters
     * @param $isPlugin boolean true iff a plugin is being installed
     */
    public function __construct($descriptor, $params = [], $isPlugin = false)
    {
        // Load all plugins. If any of them use installer hooks,
        // they'll need to be loaded here.
        PluginRegistry::loadAllPlugins();
        $this->isPlugin = $isPlugin;

        // Give the HookRegistry the opportunity to override this
        // method or alter its parameters.
        if (!HookRegistry::call('Installer::Installer', [$this, &$descriptor, &$params])) {
            $this->descriptor = $descriptor;
            $this->params = $params;
            $this->actions = [];
            $this->sql = [];
            $this->notes = [];
            $this->wroteConfig = true;
        }
    }

    /**
     * Returns true iff this is an upgrade process.
     */
    public function isUpgrade()
    {
        die('ABSTRACT CLASS');
    }

    /**
     * Destroy / clean-up after the installer.
     */
    public function destroy()
    {
        HookRegistry::call('Installer::destroy', [$this]);
    }

    /**
     * Pre-installation.
     *
     * @return boolean
     */
    public function preInstall()
    {
        $this->log('pre-install');
        if (!isset($this->currentVersion)) {
            // Retrieve the currently installed version
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $this->currentVersion = $versionDao->getCurrentVersion();
        }

        if (!isset($this->locale)) {
            $this->locale = AppLocale::getLocale();
        }

        if (!isset($this->installedLocales)) {
            $this->installedLocales = array_keys(AppLocale::getAllLocales());
        }

        if (!isset($this->dataXMLParser)) {
            $this->dataXMLParser = new DBDataXMLParser();
        }

        $result = true;
        HookRegistry::call('Installer::preInstall', [$this, &$result]);

        return $result;
    }

    /**
     * Installation.
     *
     * @return boolean
     */
    public function execute()
    {
        // Ensure that the installation will not get interrupted if it takes
        // longer than max_execution_time (php.ini). Note that this does not
        // work under safe mode.
        @set_time_limit(0);

        if (!$this->preInstall()) {
            return false;
        }

        if (!$this->parseInstaller()) {
            return false;
        }

        if (!$this->executeInstaller()) {
            return false;
        }

        if (!$this->postInstall()) {
            return false;
        }

        return $this->updateVersion();
    }

    /**
     * Post-installation.
     *
     * @return boolean
     */
    public function postInstall()
    {
        $this->log('post-install');
        $result = true;
        HookRegistry::call('Installer::postInstall', [$this, &$result]);
        return $result;
    }


    /**
     * Record message to installation log.
     *
     * @param $message string
     */
    public function log($message)
    {
        if (isset($this->logger)) {
            call_user_func([$this->logger, 'log'], $message);
        }
    }


    //
    // Main actions
    //

    /**
     * Parse the installation descriptor XML file.
     *
     * @return boolean
     */
    public function parseInstaller()
    {
        // Read installation descriptor file
        $this->log(sprintf('load: %s', $this->descriptor));
        $xmlParser = new PKPXMLParser();
        $installPath = $this->isPlugin ? $this->descriptor : self::INSTALLER_DATA_DIR . DIRECTORY_SEPARATOR . $this->descriptor;
        $installTree = $xmlParser->parse($installPath);
        if (!$installTree) {
            // Error reading installation file
            $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.installFileError');
            return false;
        }

        $versionString = $installTree->getAttribute('version');
        if (isset($versionString)) {
            $this->newVersion = Version::fromString($versionString);
        } else {
            $this->newVersion = $this->currentVersion;
        }

        // Parse descriptor
        $this->parseInstallNodes($installTree);

        $result = $this->getErrorType() == 0;

        HookRegistry::call('Installer::parseInstaller', [$this, &$result]);
        return $result;
    }

    /**
     * Execute the installer actions.
     *
     * @return boolean
     */
    public function executeInstaller()
    {
        $this->log(sprintf('version: %s', $this->newVersion->getVersionString(false)));
        foreach ($this->actions as $action) {
            if (!$this->executeAction($action)) {
                return false;
            }
        }

        $result = true;
        HookRegistry::call('Installer::executeInstaller', [$this, &$result]);

        return $result;
    }

    /**
     * Update the version number.
     *
     * @return boolean
     */
    public function updateVersion()
    {
        if ($this->newVersion->compare($this->currentVersion) > 0) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            if (!$versionDao->insertVersion($this->newVersion)) {
                return false;
            }
        }

        $result = true;
        HookRegistry::call('Installer::updateVersion', [$this, &$result]);

        return $result;
    }


    //
    // Installer Parsing
    //

    /**
     * Parse children nodes in the install descriptor.
     *
     * @param $installTree XMLNode
     */
    public function parseInstallNodes($installTree)
    {
        foreach ($installTree->getChildren() as $node) {
            switch ($node->getName()) {
                case 'schema':
                case 'data':
                case 'code':
                case 'migration':
                case 'note':
                    $this->addInstallAction($node);
                    break;
                case 'upgrade':
                    $minVersion = $node->getAttribute('minversion');
                    $maxVersion = $node->getAttribute('maxversion');
                    if ((!isset($minVersion) || $this->currentVersion->compare($minVersion) >= 0) && (!isset($maxVersion) || $this->currentVersion->compare($maxVersion) <= 0)) {
                        $this->parseInstallNodes($node);
                    }
                    break;
            }
        }
    }

    /**
     * Add an installer action from the descriptor.
     *
     * @param $node XMLNode
     */
    public function addInstallAction($node)
    {
        $fileName = $node->getAttribute('file');

        if (!isset($fileName)) {
            $this->actions[] = ['type' => $node->getName(), 'file' => null, 'attr' => $node->getAttributes()];
        } elseif (strstr($fileName, '{$installedLocale}')) {
            // Filename substitution for locales
            foreach ($this->installedLocales as $thisLocale) {
                $newFileName = str_replace('{$installedLocale}', $thisLocale, $fileName);
                $this->actions[] = ['type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes()];
            }
        } else {
            $newFileName = str_replace('{$locale}', $this->locale, $fileName);
            if (!file_exists($newFileName)) {
                // Use version from default locale if data file is not available in the selected locale
                $newFileName = str_replace('{$locale}', self::INSTALLER_DEFAULT_LOCALE, $fileName);
            }

            $this->actions[] = ['type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes()];
        }
    }


    //
    // Installer Execution
    //

    /**
     * Execute a single installer action.
     *
     * @param $action array
     *
     * @return boolean
     */
    public function executeAction($action)
    {
        switch ($action['type']) {
            case 'schema':
                $fileName = $action['file'];
                $this->log(sprintf('schema: %s', $action['file']));

                require_once('lib/pkp/lib/vendor/adodb/adodb-php/adodb.inc.php');
                require_once('./lib/pkp/lib/vendor/adodb/adodb-php/adodb-xmlschema.inc.php');
                $dbconn = ADONewConnection(Config::getVar('database', 'driver'));
                $port = Config::getVar('database', 'port');
                $dbconn->Connect(
                    Config::getVar('database', 'host') . ($port ? ':' . $port : ''),
                    Config::getVar('database', 'username'),
                    Config::getVar('database', 'password'),
                    Config::getVar('database', 'name')
                );
                $schemaXMLParser = new adoSchema($dbconn);
                $dict = $schemaXMLParser->dict;
                $sql = $schemaXMLParser->parseSchema($fileName);
                $schemaXMLParser->destroy();

                if ($sql) {
                    return $this->executeSQL($sql);
                } else {
                    $this->setError(self::INSTALLER_ERROR_DB, str_replace('{$file}', $fileName, __('installer.installParseDBFileError')));
                    return false;
                }
                break;
            case 'data':
                $fileName = $action['file'];
                $condition = $action['attr']['condition'] ?? null;
                $includeAction = true;
                if ($condition) {
                    // Create a new scope to evaluate the condition
                    $evalFunction = function ($installer, $action) use ($condition) {
                        return eval($condition);
                    };
                    $includeAction = $evalFunction($this, $action);
                }
                $this->log('data: ' . $action['file'] . ($includeAction ? '' : ' (skipped)'));
                if (!$includeAction) {
                    break;
                }

                $sql = $this->dataXMLParser->parseData($fileName);
                // We might get an empty SQL if the upgrade script has
                // been executed before.
                if ($sql) {
                    return $this->executeSQL($sql);
                }
                break;
            case 'migration':
                assert(isset($action['attr']['class']));
                $fullClassName = $action['attr']['class'];
                import($fullClassName);
                $shortClassName = substr($fullClassName, strrpos($fullClassName, '.') + 1);
                $this->log(sprintf('migration: %s', $shortClassName));
                $migration = new $shortClassName();
                try {
                    $migration->up();
                    $this->migrations[] = $migration;
                } catch (Exception $e) {
                    // Log an error message
                    $this->setError(
                        self::INSTALLER_ERROR_DB,
                        Config::getVar('debug', 'show_stacktrace') ? (string) $e : $e->getMessage()
                    );

                    // Back out already-executed migrations.
                    while ($previousMigration = array_pop($this->migrations)) {
                        try {
                            $previousMigration->down();
                        } catch (PKP\install\DowngradeNotSupportedException $e) {
                            break;
                        }
                    }
                    return false;
                }
                return true;
            case 'code':
                $condition = $action['attr']['condition'] ?? null;
                $includeAction = true;
                if ($condition) {
                    // Create a new scope to evaluate the condition
                    $evalFunction = function ($installer, $action) use ($condition) {
                        return eval($condition);
                    };
                    $includeAction = $evalFunction($this, $action);
                }
                $this->log(sprintf('code: %s %s::%s' . ($includeAction ? '' : ' (skipped)'), $action['file'] ?? 'Installer', $action['attr']['class'] ?? 'Installer', $action['attr']['function']));
                if (!$includeAction) {
                    return true;
                } // Condition not met; skip the action.

                if (isset($action['file'])) {
                    require_once($action['file']);
                }
                if (isset($action['attr']['class'])) {
                    return call_user_func([$action['attr']['class'], $action['attr']['function']], $this, $action['attr']);
                } else {
                    return call_user_func([$this, $action['attr']['function']], $this, $action['attr']);
                }
                break;
            case 'note':
                $this->log(sprintf('note: %s', $action['file']));
                $this->notes[] = join('', file($action['file']));
                break;
        }

        return true;
    }

    /**
     * Execute an SQL statement.
     *
     * @param $sql mixed
     *
     * @return boolean
     */
    public function executeSQL($sql)
    {
        if (is_array($sql)) {
            foreach ($sql as $stmt) {
                if (!$this->executeSQL($stmt)) {
                    return false;
                }
            }
        } else {
            try {
                DB::affectingStatement($sql);
            } catch (Exception $e) {
                $this->setError(self::INSTALLER_ERROR_DB, $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Update the specified configuration parameters.
     *
     * @param $configParams arrays
     *
     * @return boolean
     */
    public function updateConfig($configParams)
    {
        // Update config file
        $configParser = new \PKP\config\ConfigParser();
        if (!$configParser->updateConfig(Config::getConfigFileName(), $configParams)) {
            // Error reading config file
            $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.configFileError');
            return false;
        }

        $this->configContents = $configParser->getFileContents();
        if (!$configParser->writeConfig(Config::getConfigFileName())) {
            $this->wroteConfig = false;
        }

        return true;
    }


    //
    // Accessors
    //

    /**
     * Get the value of an installation parameter.
     *
     * @param $name
     */
    public function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Return currently installed version.
     *
     * @return Version
     */
    public function getCurrentVersion()
    {
        return $this->currentVersion;
    }

    /**
     * Return new version after installation.
     *
     * @return Version
     */
    public function getNewVersion()
    {
        return $this->newVersion;
    }

    /**
     * Get the set of SQL statements required to perform the install.
     *
     * @return array
     */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * Get the set of installation notes.
     *
     * @return array
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get the contents of the updated configuration file.
     *
     * @return string
     */
    public function getConfigContents()
    {
        return $this->configContents;
    }

    /**
     * Check if installer was able to write out new config file.
     *
     * @return boolean
     */
    public function wroteConfig()
    {
        return $this->wroteConfig;
    }

    /**
     * Return the error code.
     * Valid return values are:
     *   - 0 = no error
     *   - INSTALLER_ERROR_GENERAL = general installation error.
     *   - INSTALLER_ERROR_DB = database installation error
     *
     * @return int
     */
    public function getErrorType()
    {
        return $this->errorType ?? 0;
    }

    /**
     * The error message, if an error has occurred.
     * In the case of a database error, an unlocalized string containing the error message is returned.
     * For any other error, a localization key for the error message is returned.
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Return the error message as a localized string.
     *
     * @return string.
     */
    public function getErrorString()
    {
        switch ($this->getErrorType()) {
            case self::INSTALLER_ERROR_DB:
                return 'DB: ' . $this->getErrorMsg();
            default:
                return __($this->getErrorMsg());
        }
    }

    /**
     * Set the error type and messgae.
     *
     * @param $type int
     * @param $msg string Text message (INSTALLER_ERROR_DB) or locale key (otherwise)
     */
    public function setError($type, $msg)
    {
        $this->errorType = $type;
        $this->errorMsg = $msg;
    }

    /**
     * Set the logger for this installer.
     *
     * @param $logger Logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Clear the data cache files (needed because of direct tinkering
     * with settings tables)
     *
     * @return boolean
     */
    public function clearDataCache()
    {
        $cacheManager = CacheManager::getManager();
        $cacheManager->flush(null, CACHE_TYPE_FILE);
        $cacheManager->flush(null, CACHE_TYPE_OBJECT);
        return true;
    }

    /**
     * Set the current version for this installer.
     *
     * @param $version Version
     */
    public function setCurrentVersion($version)
    {
        $this->currentVersion = $version;
    }

    /**
     * For upgrade: install email templates and data
     *
     * @param $installer object
     * @param $attr array Attributes: array containing
     * 		'key' => 'EMAIL_KEY_HERE',
     * 		'locales' => 'en_US,fr_CA,...'
     */
    public function installEmailTemplate($installer, $attr)
    {
        $locales = explode(',', $attr['locales']);
        foreach ($locales as $locale) {
            AppLocale::requireComponents(LOCALE_COMPONENT_APP_EMAIL, $locale);
        }
        $emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /** @var EmailTemplateDAO $emailTemplateDao */
        // FIXME pkp/pkp-lib#6284 Remove after drop of support for upgrades from 3.2.0
        if (!Schema::hasColumn('email_templates_default', 'stage_id')) {
            Schema::table('email_templates_default', function (Blueprint $table) {
                $table->bigInteger('stage_id')->nullable();
            });
        }
        $emailTemplateDao->installEmailTemplates($emailTemplateDao->getMainEmailTemplatesFilename(), $locales, false, $attr['key']);
        return true;
    }

    /**
     * Install the given filter configuration file.
     *
     * @param $filterConfigFile string
     *
     * @return boolean true when successful, otherwise false
     */
    public function installFilterConfig($filterConfigFile)
    {
        static $filterHelper = false;

        // Parse the filter configuration.
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filterConfigFile);

        // Validate the filter configuration.
        if (!$tree) {
            return false;
        }

        // Get the filter helper.
        if ($filterHelper === false) {
            $filterHelper = new FilterHelper();
        }

        // Are there any filter groups to be installed?
        $filterGroupsNode = $tree->getChildByName('filterGroups');
        if ($filterGroupsNode instanceof \PKP\xml\XMLNode) {
            $filterHelper->installFilterGroups($filterGroupsNode);
        }

        // Are there any filters to be installed?
        $filtersNode = $tree->getChildByName('filters');
        if ($filtersNode instanceof \PKP\xml\XMLNode) {
            foreach ($filtersNode->getChildren() as $filterNode) { /** @var XMLNode $filterNode */
                $filterHelper->configureFilter($filterNode);
            }
        }

        return true;
    }

    /**
     * Check to see whether a column exists.
     * Used in installer XML in conditional checks on <data> nodes.
     *
     * @param $tableName string
     * @param $columnName string
     *
     * @return boolean
     */
    public function columnExists($tableName, $columnName)
    {
        $schema = DB::getDoctrineSchemaManager();
        // Make sure the table exists
        $tables = $schema->listTableNames();
        if (!in_array($tableName, $tables)) {
            return false;
        }

        return Schema::hasColumn($tableName, $columnName);
    }

    /**
     * Check to see whether a table exists.
     * Used in installer XML in conditional checks on <data> nodes.
     *
     * @param $tableName string
     *
     * @return boolean
     */
    public function tableExists($tableName)
    {
        $tables = DB::getDoctrineSchemaManager()->listTableNames();
        return in_array($tableName, $tables);
    }

    /**
     * Insert or update plugin data in versions
     * and plugin_settings tables.
     *
     * @return boolean
     */
    public function addPluginVersions()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $fileManager = new FileManager();
        $categories = PluginRegistry::getCategories();
        foreach ($categories as $category) {
            PluginRegistry::loadCategory($category);
            $plugins = PluginRegistry::getPlugins($category);
            if (!empty($plugins)) {
                foreach ($plugins as $plugin) {
                    $versionFile = $plugin->getPluginPath() . '/version.xml';

                    if ($fileManager->fileExists($versionFile)) {
                        $versionInfo = VersionCheck::parseVersionXML($versionFile);
                        $pluginVersion = $versionInfo['version'];
                    } else {
                        $pluginVersion = new Version(
                            1,
                            0,
                            0,
                            0, // Major, minor, revision, build
                            Core::getCurrentDate(), // Date installed
                            1,	// Current
                            'plugins.' . $category, // Type
                            basename($plugin->getPluginPath()), // Product
                            '',	// Class name
                            0,	// Lazy load
                            $plugin->isSitePlugin()	// Site wide
                        );
                    }
                    $versionDao->insertVersion($pluginVersion, true);
                }
            }
        }

        return true;
    }

    /**
     * Fail the upgrade.
     *
     * @param $installer Installer
     * @param $attr array Attributes
     *
     * @return boolean
     */
    public function abort($installer, $attr)
    {
        $installer->setError(self::INSTALLER_ERROR_GENERAL, $attr['message']);
        return false;
    }

    /**
     * For 3.1.0 upgrade.  DefaultMenus Defaults
     *
     * @return boolean Success/failure
     */
    public function installDefaultNavigationMenus()
    {
        $contextDao = Application::getContextDAO();
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */

        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $navigationMenuDao->installSettings($context->getId(), 'registry/navigationMenus.xml');
        }

        $navigationMenuDao->installSettings(\PKP\core\PKPApplication::CONTEXT_ID_NONE, 'registry/navigationMenus.xml');

        return true;
    }

    /**
     * Check that the environment meets minimum PHP requirements.
     *
     * @return boolean Success/failure
     */
    public function checkPhpVersion()
    {
        if (version_compare(PKPApplication::PHP_REQUIRED_VERSION, PHP_VERSION) != 1) {
            return true;
        }

        $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.unsupportedPhpError');
        return false;
    }

    /*
     * Migrate site locale settings to a serialized array in the database
     */
    public function migrateSiteLocales()
    {
        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */

        $result = $siteDao->retrieve('SELECT installed_locales, supported_locales FROM site');

        $set = $params = [];
        $row = (array) $result->current();
        $type = 'array';
        foreach ($row as $column => $value) {
            if (!empty($value)) {
                $set[] = $column . ' = ?';
                $params[] = $siteDao->convertToDB(explode(':', $value), $type);
            }
        }
        $siteDao->update('UPDATE site SET ' . join(',', $set), $params);

        return true;
    }

    /**
     * Migrate active sidebar blocks from plugin_settings to journal_settings
     *
     * @return boolean
     */
    public function migrateSidebarBlocks()
    {
        $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
        $site = $siteDao->getSite();

        $plugins = PluginRegistry::loadCategory('blocks');
        if (empty($plugins)) {
            return true;
        }

        // Sanitize plugin names for use in sql IN().
        $sanitizedPluginNames = array_map(function ($name) {
            return "'" . preg_replace('/[^A-Za-z0-9]/', '', $name) . "'";
        }, array_keys($plugins));

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $result = $pluginSettingsDao->retrieve(
            'SELECT plugin_name, context_id, setting_value FROM plugin_settings WHERE plugin_name IN (' . join(',', $sanitizedPluginNames) . ') AND setting_name=\'context\';'
        );

        $sidebarSettings = [];
        foreach ($result as $row) {
            if ($row->setting_value != 1) {
                continue;
            } // BLOCK_CONTEXT_SIDEBAR

            $seq = $pluginSettingsDao->getSetting($row->context_id, $row->plugin_name, 'seq');
            if (!isset($sidebarSettings[$row->context_id])) {
                $sidebarSettings[$row->context_id] = [];
            }
            $sidebarSettings[$row->context_id][(int) $seq] = $row->plugin_name;
        }

        foreach ($sidebarSettings as $contextId => $contextSetting) {
            // Order by sequence
            ksort($contextSetting);
            $contextSetting = array_values($contextSetting);
            if ($contextId) {
                $contextDao = Application::getContextDAO();
                $context = $contextDao->getById($contextId);
                $context->setData('sidebar', $contextSetting);
                $contextDao->updateObject($context);
            } else {
                $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
                $site = $siteDao->getSite();
                $site->setData('sidebar', $contextSetting);
                $siteDao->updateObject($site);
            }
        }

        $pluginSettingsDao->update('DELETE FROM plugin_settings WHERE plugin_name IN (' . join(',', $sanitizedPluginNames) . ') AND (setting_name=\'context\' OR setting_name=\'seq\');');

        return true;
    }

    /**
     * Migrate the metadata settings in the database to use a single row with one
     * of the new constants
     */
    public function migrateMetadataSettings()
    {
        $contextDao = Application::getContextDao();

        $metadataSettings = [
            'coverage',
            'languages',
            'rights',
            'source',
            'subjects',
            'type',
            'disciplines',
            'keywords',
            'agencies',
            'citations',
        ];

        $result = $contextDao->retrieve('SELECT ' . $contextDao->primaryKeyColumn . ' from ' . $contextDao->tableName);
        $contextIds = [];
        foreach ($result as $row) {
            $row = (array) $row;
            $contextIds[] = $row[$contextDao->primaryKeyColumn];
        }

        foreach ($metadataSettings as $metadataSetting) {
            foreach ($contextIds as $contextId) {
                $result = $contextDao->retrieve(
                    '
					SELECT * FROM ' . $contextDao->settingsTableName . ' WHERE
						' . $contextDao->primaryKeyColumn . ' = ?
						AND (
							setting_name = ?
							OR setting_name = ?
							OR setting_name = ?
						)
					',
                    [
                        $contextId,
                        $metadataSetting . 'EnabledWorkflow',
                        $metadataSetting . 'EnabledSubmission',
                        $metadataSetting . 'Required',
                    ]
                );
                $value = METADATA_DISABLE;
                foreach ($result as $row) {
                    if ($row->setting_name === $metadataSetting . 'Required' && $row->setting_value) {
                        $value = METADATA_REQUIRE;
                    } elseif ($row->setting_name === $metadataSetting . 'EnabledSubmission' && $row->setting_value && $value !== METADATA_REQUIRE) {
                        $value = METADATA_REQUEST;
                    } elseif ($row->setting_name === $metadataSetting . 'EnabledWorkflow' && $row->setting_value && $value !== METADATA_REQUEST && $value !== METADATA_REQUIRE) {
                        $value = METADATA_ENABLE;
                    }
                }

                if ($value !== METADATA_DISABLE) {
                    $contextDao->update(
                        '
						INSERT INTO ' . $contextDao->settingsTableName . ' (
							' . $contextDao->primaryKeyColumn . ',
							locale,
							setting_name,
							setting_value
						) VALUES (?, ?, ?, ?)',
                        [
                            $contextId,
                            '',
                            $metadataSetting,
                            $value,
                        ]
                    );
                }

                $contextDao->update(
                    '
					DELETE FROM ' . $contextDao->settingsTableName . ' WHERE
						' . $contextDao->primaryKeyColumn . ' = ?
						AND (
							setting_name = ?
							OR setting_name = ?
							OR setting_name = ?
						)
					',
                    [
                        $contextId,
                        $metadataSetting . 'EnabledWorkflow',
                        $metadataSetting . 'EnabledSubmission',
                        $metadataSetting . 'Required',
                    ]
                );
            }
        }

        return true;
    }

    /**
     * Set the notification settings for journal managers and subeditors so
     * that they are opted out of the monthly stats email.
     */
    public function setStatsEmailSettings()
    {
        $roleIds = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR];

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        for ($contexts = Application::get()->getContextDAO()->getAll(true); $context = $contexts->next();) {
            foreach ($roleIds as $roleId) {
                for ($userGroups = $userGroupDao->getByRoleId($context->getId(), $roleId); $userGroup = $userGroups->next();) {
                    for ($users = $userGroupDao->getUsersById($userGroup->getId(), $context->getId()); $user = $users->next();) {
                        $notificationSubscriptionSettingsDao->update(
                            'INSERT INTO notification_subscription_settings
								(setting_name, setting_value, user_id, context, setting_type)
								VALUES
								(?, ?, ?, ?, ?)',
                            [
                                'blocked_emailed_notification',
                                PKPNotification::NOTIFICATION_TYPE_EDITORIAL_REPORT,
                                $user->getId(),
                                $context->getId(),
                                'int'
                            ]
                        );
                    }
                }
            }
        }

        return true;
    }

    /**
     * Fix library files, which were mistakenly named server-side using source filenames.
     * See https://github.com/pkp/pkp-lib/issues/5471
     *
     * @return boolean
     */
    public function fixLibraryFiles()
    {
        // Fetch all library files (no method currently in LibraryFileDAO for this)
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $result = $libraryFileDao->retrieve('SELECT * FROM library_files');
        $libraryFiles = new DAOResultFactory($result, $libraryFileDao, '_fromRow', ['id']);
        $wrongFiles = [];
        while ($libraryFile = $libraryFiles->next()) {
            $libraryFileManager = new LibraryFileManager($libraryFile->getContextId());
            $wrongFilePath = $libraryFileManager->getBasePath() . $libraryFile->getOriginalFileName();
            $rightFilePath = $libraryFile->getFilePath();

            if (isset($wrongFiles[$wrongFilePath])) {
                error_log('A potential collision was found between library files ' . $libraryFile->getId() . ' and ' . $wrongFiles[$wrongFilePath]->getId() . '. Please review the database entries and ensure that the associated files are correct.');
            } else {
                $wrongFiles[$wrongFilePath] = $libraryFile;
            }

            // For all files for which the "wrong" filename exists and the "right" filename doesn't,
            // copy the "wrong" file over to the "right" one. This will leave the "wrong" file in
            // place, and won't disambiguate cases for which files were clobbered.
            if (file_exists($wrongFilePath) && !file_exists($rightFilePath)) {
                $libraryFileManager->copyFile($wrongFilePath, $rightFilePath);
            }
        }
        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\Installer', '\Installer');
    define('INSTALLER_ERROR_GENERAL', \Installer::INSTALLER_ERROR_GENERAL);
    define('INSTALLER_ERROR_DB', \Installer::INSTALLER_ERROR_DB);
    define('INSTALLER_DATA_DIR', \Installer::INSTALLER_DATA_DIR);
    define('INSTALLER_DEFAULT_LOCALE', \Installer::INSTALLER_DEFAULT_LOCALE);
}
