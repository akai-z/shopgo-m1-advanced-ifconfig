<?php
/**
 * ShopGo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GNU General Public License (GPLv2)
 * that is bundled with this package in the file COPYING.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @category    Shopgo
 * @package     Shopgo_AdvIfconfig
 * @copyright   Copyright (c) 2014 Shopgo. (http://www.shopgo.me)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License (GPLv2)
 */


/**
 * Data helper
 *
 * @category    Shopgo
 * @package     Shopgo_AdvIfconfig
 * @author      Ammar <ammar@shopgo.me>
 */
class Shopgo_AdvIfconfig_Helper_Data extends Shopgo_Core_Helper_Abstract
{
    /**
     * Check system config node depends
     *
     * @param string $section
     * @param string $group
     * @param string $field
     * @param boolean $result
     * @return boolean
     */
    public function checkSystemConfigNodeDepends($section, $group, $field, $result = false)
    {
        $depends = $this->getSystemConfigNodeDepends($section, $group, $field);

        foreach ((array)$depends as $fieldName => $fieldValue) {
            $path = $section . '/' . $group . '/' . $fieldName;
            $dependValid = $fieldValue == Mage::getStoreConfigFlag($path);
            $result = $result && $this->checkSystemConfigNodeDepends(
                $section, $group, $fieldName, $dependValid
            );
        }

        return $result;
    }

    /**
     * Get system config node depends
     *
     * @param string $sectionName
     * @param string $groupName
     * @param string $fieldName
     * @return object
     */
    public function getSystemConfigNodeDepends($sectionName, $groupName = null, $fieldName = null)
    {
        $config = Mage::getSingleton('adminhtml/config');
        $sectionName = trim($sectionName, '/');
        $path = '//sections/' . $sectionName;
        $groupNode = $fieldNode = null;
        $sectionNode = $config->getSections()->xpath($path);
        if (!empty($groupName)) {
            $groupPath = $path .= '/groups/' . trim($groupName, '/');
            $groupNode = $config->getSections()->xpath($path);
        }
        if (!empty($fieldName)) {
            if (!empty($groupName)) {
                $path .= '/fields/' . trim($fieldName, '/');
                $fieldNode = $config->getSections()->xpath($path);
            }
            else {
                Mage::throwException(
                    $this->__('The group node name must be specified with field node name.')
                );
            }
        }
        $path .= '/depends';
        $dependsNode = $config->getSections()->xpath($path);
        foreach ($dependsNode as $node) {
            return $node;
        }
        return null;
    }

    /**
     * Get store config with depends flag
     *
     * @param string $configPath
     * @param array $requiredDepends
     * @param string $type
     * @return boolean
     */
    public function getStoreConfigWithDependsFlag($configPath, $requiredDepends = array(), $type = 'tree')
    {
        $ifConfig = Mage::getStoreConfigFlag($configPath);

        if ($ifConfig) {
            if ($type == 1 || $type == 'tree') {
                $configPath = explode('/', $configPath);
                $ifConfig = $ifConfig
                    && $this->checkSystemConfigNodeDepends(
                    $configPath[0], // Section
                    $configPath[1], // Group
                    $configPath[2], // Field
                    $ifConfig
                );
            }

            if ($type == 1 || $type == 'required') {
                if (gettype($requiredDepends) == 'string') {
                    $requiredDepends = array_map('trim', explode(',', $requiredDepends));
                }

                foreach ($requiredDepends as $depend) {
                    $ifConfig = $ifConfig && Mage::getStoreConfigFlag($depend);
                }
            }
        }

        return $ifConfig;
    }
}
