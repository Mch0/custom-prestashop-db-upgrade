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
 * @return bool
 *
 * @throws \PrestaShop\Module\AutoUpgrade\Exceptions\UpdateDatabaseException
 */
function add_hook($hook, $title, $description, $position = 1)
{
    return (bool) DbWrapper::execute('
        INSERT INTO `' . _DB_PREFIX_ . 'hook` (`name`, `title`, `description`, `position`)
        VALUES ("' . pSQL($hook) . '", "' . pSQL($title) . '", "' . pSQL($description) . '", ' . (int) $position . ')
        ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `description` = VALUES(`description`)    
    ');
}
