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

/**
 * In Prestashop 1.7.5 the supplier_rule and manufacturer_rule have been modified:
 *      {id}__{rewrite} => supplier/{id}-{rewrite}
 *      {id}_{rewrite}  => brand/{id}-{rewrite}
 *
 * If the merchant kept the original routes the former urls won't be reachable any
 * more and SEO will be lost. So we force a custom rule matching the former format.
 *
 * If the route was customized, no need to do anything. We don't change anything for
 * multi shop either since it will be used it the merchant has already changed them.
 */
function add_supplier_manufacturer_routes()
{
    Configuration::loadConfiguration();
    $legacyRoutes = [
        'supplier_rule' => '{id}__{rewrite}',
        'manufacturer_rule' => '{id}_{rewrite}',
    ];
    foreach ($legacyRoutes as $routeId => $rule) {
        if (!Configuration::get('PS_ROUTE_' . $routeId, null, 0, 0)) {
            Configuration::updateGlobalValue('PS_ROUTE_' . $routeId, $rule);
        }
    }
}
