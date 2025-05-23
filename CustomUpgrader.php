<?php

require_once __DIR__ . "/../config/config.inc.php";
class CustomUpgrader
{
    protected $pathToUpgradeScripts = __DIR__ . '/upgrade/';

    protected $destinationUpgradeVersion = "1.7.8.11";

    protected $db;

    protected  $currentVersion;

    protected $debug = false;

    public function __construct($currentVersion, $destinationUpgradeVersion)
    {
        $this->currentVersion = $currentVersion;
        $this->destinationUpgradeVersion = $destinationUpgradeVersion;
        $this->db = Db::getInstance();
    }

    public function activeDebug()
    {
        $this->debug = true;
        return $this;
    }
    public function debug()
    {
        $upgrade_dir_sql = $this->pathToUpgradeScripts . '/sql/';
        $sqlContentVersion = $this->applySqlParams(
            $this->getUpgradeSqlFilesListToApply($upgrade_dir_sql, $this->currentVersion)
        );
        dump($sqlContentVersion);
    }

    public function run()
    {
        $this->upgradeDb($this->currentVersion);
        $this->runRecurrentQueries();
    }

    protected function upgradeDb(string $oldversion): void
    {
        $upgrade_dir_sql = $this->pathToUpgradeScripts . '/sql/';
        $sqlContentVersion = $this->applySqlParams(
            $this->getUpgradeSqlFilesListToApply($upgrade_dir_sql, $oldversion)
        );

        foreach ($sqlContentVersion as $upgrade_file => $sqlContent) {
            foreach ($sqlContent as $query) {
                $this->runQuery($upgrade_file, $query);
            }
        }
    }

    /**
     * Replace some placeholders in the SQL upgrade files (prefix, engine...).
     *
     * @param array<string, string> $sqlFiles
     *
     * @return array<string, string[]> of SQL requests per version
     */
    protected function applySqlParams(array $sqlFiles): array
    {
        $search = ['PREFIX_', 'ENGINE_TYPE', 'DB_NAME'];
        $replace = [_DB_PREFIX_, (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'MyISAM'), _DB_NAME_];

        $sqlRequests = [];

        foreach ($sqlFiles as $version => $file) {
            $sqlContent = file_get_contents($file) . "\n";
            $sqlContent = str_replace($search, $replace, $sqlContent);
            $sqlContent = preg_split("/;\s*[\r\n]+/", $sqlContent);
            $sqlRequests[$version] = $sqlContent;
        }

        return $sqlRequests;
    }

    protected function getUpgradeSqlFilesListToApply(string $upgrade_dir_sql, string $oldversion): array
    {
        if (!file_exists($upgrade_dir_sql)) {
            throw new Exception('Unable to find upgrade directory in the installation path.');
        }

        $upgradeFiles = $neededUpgradeFiles = [];
        if ($handle = opendir($upgrade_dir_sql)) {
            while (false !== ($file = readdir($handle))) {
                if ($file[0] === '.') {
                    continue;
                }
                if (!is_readable($upgrade_dir_sql . $file)) {
                    throw new Exception('Error while loading SQL upgrade file "%s".', [$file]);
                }
                $upgradeFiles[] = str_replace('.sql', '', $file);
            }
            closedir($handle);
        }
        if (empty($upgradeFiles)) {
            throw new Exception('Cannot find the SQL upgrade files. Please check that the %s folder is not empty.', [$upgrade_dir_sql]);
        }
        natcasesort($upgradeFiles);

        foreach ($upgradeFiles as $version) {
            if (version_compare($version, $oldversion) == 1 && version_compare($this->destinationUpgradeVersion, $version) != -1) {
                $neededUpgradeFiles[$version] = $upgrade_dir_sql . $version . '.sql';
            }
        }

        return $neededUpgradeFiles;
    }

    protected function runQuery(string $upgrade_file, string $query): void
    {
        $query = trim($query);
        if (empty($query)) {
            return;
        }
        // If php code have to be executed
        if (strpos($query, '/* PHP:') !== false) {
            $this->runPhpQuery($upgrade_file, $query);

            return;
        }
        $this->runSqlQuery($upgrade_file, $query);
    }

    protected function runPhpQuery(string $upgrade_file, string $query): void
    {
        // Parsing php code
        $pos = strpos($query, '/* PHP:') + strlen('/* PHP:');
        $phpString = substr($query, $pos, strlen($query) - $pos - strlen(' */;'));
        $php = explode('::', $phpString);
        preg_match('/\((.*)\)/', $phpString, $pattern);
        $paramsString = trim($pattern[0], '()');
        preg_match_all('/([^,]+),? ?/', $paramsString, $parameters);
        // TODO: Could be `$parameters = $parameters[1] ?? [];` if PHP min version was > 7.0
        $parameters = isset($parameters[1]) ?
            $parameters[1] :
            [];
        foreach ($parameters as &$parameter) {
            $parameter = str_replace('\'', '', $parameter);
        }

        // reset phpRes to a null value
        $phpRes = null;
        // Call a simple function
        if (strpos($phpString, '::') === false) {
            $func_name = str_replace($pattern[0], '', $php[0]);
            $pathToPhpDirectory = $this->pathToUpgradeScripts . 'php/';

            if (!file_exists($pathToPhpDirectory . strtolower($func_name) . '.php')) {
                dump('[ERROR] ' . $pathToPhpDirectory . strtolower($func_name) . ' PHP - missing file ' . $query);

                return;
            }
            if ($this->debug) {
                dump("require_once $pathToPhpDirectory" . strtolower($func_name) . ".php");
                dump("call_user_func_array($func_name)");
                dump($parameters);
                return;
            }

            require_once $pathToPhpDirectory . strtolower($func_name) . '.php';
            $phpRes = call_user_func_array($func_name, $parameters);
        }
        // Or an object method
        else {
            $func_name = [$php[0], str_replace($pattern[0], '', $php[1])];
            dump('[ERROR] ' . $upgrade_file . ' PHP - Object Method call is forbidden (' . $php[0] . '::' . str_replace($pattern[0], '', $php[1]) . ')');

            return;
        }

        if (isset($phpRes) && (is_array($phpRes) && !empty($phpRes['error'])) || $phpRes === false) {
            dump('
                [ERROR] PHP ' . $upgrade_file . ' ' . $query . "\n" . '
                ' . (empty($phpRes['error']) ? '' : $phpRes['error'] . "\n") . '
                ' . (empty($phpRes['msg']) ? '' : ' - ' . $phpRes['msg'] . "\n"));
        } else {
            dump('<div class="upgradeDbOk">[OK] PHP ' . $upgrade_file . ' : ' . $query . '</div>');
        }
    }

    protected function runSqlQuery(string $upgrade_file, string $query): void
    {
        if ($this->debug) {
            dump($query);
            return;
        }

        if (strstr($query, 'CREATE TABLE') !== false) {
            $pattern = '/CREATE TABLE.*[`]*' . _DB_PREFIX_ . '([^`]*)[`]*\s\(/';
            preg_match($pattern, $query, $matches);
            if (!empty($matches[1])) {
                $drop = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $matches[1] . '`;';
                if ($this->db->execute($drop, false)) {
                    dump('<div class="upgradeDbOk">' . '[DROP] SQL %s table has been dropped.', ['`' . _DB_PREFIX_ . $matches[1] . '`']) . '</div>';
                }
            }
        }

        if ($this->db->execute($query, false)) {
            dump('<div class="upgradeDbOk">[OK] SQL ' . $upgrade_file . ' ' . $query . '</div>');

            return;
        }


        $error = $this->db->getMsgError();
        $error_number = $this->db->getNumberError();
        dump('
            <div class="upgradeDbError">
            [WARNING] SQL ' . $upgrade_file . '
            ' . $error_number . ' in ' . $query . ': ' . $error . '</div>');

        $duplicates = ['1050', '1054', '1060', '1061', '1062', '1091'];
        if (!in_array($error_number, $duplicates)) {
            dump('SQL ' . $upgrade_file . ' ' . $error_number . ' in ' . $query . ': ' . $error);
        }
    }

    protected function runRecurrentQueries(): void
    {
        if ($this->debug) {
            return;
        }

        $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET `name` = \'PS_LEGACY_IMAGES\' WHERE name LIKE \'0\' AND `value` = 1');
        $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET `value` = 0 WHERE `name` LIKE \'PS_LEGACY_IMAGES\'');
        if ($this->db->getValue('SELECT COUNT(id_product_download) FROM `' . _DB_PREFIX_ . 'product_download` WHERE `active` = 1') > 0) {
            $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET `value` = 1 WHERE `name` LIKE \'PS_VIRTUAL_PROD_FEATURE_ACTIVE\'');
        }

        // Exported from the end of doUpgrade()
        $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET value="0" WHERE name = "PS_HIDE_OPTIMIZATION_TIS"', false);
        $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET value="1" WHERE name = "PS_NEED_REBUILD_INDEX"', false);
        $this->db->execute('UPDATE `' . _DB_PREFIX_ . 'configuration` SET value="' . $this->destinationUpgradeVersion . '" WHERE name = "PS_VERSION_DB"', false);
    }


}