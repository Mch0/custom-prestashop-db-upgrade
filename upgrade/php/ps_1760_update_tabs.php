<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

use PrestaShop\Module\AutoUpgrade\Database\DbWrapper;

/**
 * File copied from ps_1750_update_module_tabs.php and modified to add new roles
 *
 * @throws \PrestaShop\Module\AutoUpgrade\Exceptions\UpdateDatabaseException
 */
function ps_1760_update_tabs()
{
    // STEP 1: Add new sub menus for modules (tab may exist but we need authorization roles to be added as well)
    $moduleTabsToBeAdded = [
        'AdminMailThemeParent' => [
            'translations' => 'en:Email Themes',
            'parent' => 'AdminParentThemes',
        ],
        'AdminMailTheme' => [
            'translations' => 'en:Email Themes',
            'parent' => 'AdminMailThemeParent',
        ],
        'AdminModulesUpdates' => [
            'translations' => 'en:Updates|fr:Mises à jour|es:Actualizaciones|de:Aktualisierung|it:Aggiornamenti',
            'parent' => 'AdminModulesSf',
        ],
        'AdminModulesNotifications' => [
            'translations' => 'en:Updates|fr:Mises à jour|es:Actualizaciones|de:Aktualisierung|it:Aggiornamenti',
            'parent' => 'AdminModulesSf',
        ],
    ];

    include_once 'add_new_tab.php';
    foreach ($moduleTabsToBeAdded as $className => $tabDetails) {
        add_new_tab_17($className, $tabDetails['translations'], 0, false, $tabDetails['parent']);
        DbWrapper::execute(
            'UPDATE `' . _DB_PREFIX_ . 'tab` SET `active`= 1 WHERE `class_name` = "' . pSQL($className) . '"'
        );
    }
}
