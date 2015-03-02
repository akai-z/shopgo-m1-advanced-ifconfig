<?php
/**
 * ShopGo
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category    Shopgo
 * @package     Shopgo_AdvIfconfig
 * @copyright   Copyright (c) 2014 Shopgo. (http://www.shopgo.me)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Layout model
 *
 * @category    Shopgo
 * @package     Shopgo_AdvIfconfig
 * @author      Ammar <ammar@shopgo.me>
 */
class Shopgo_AdvIfconfig_Model_Magento_Core_Layout extends Mage_Core_Model_Layout
{
    /**
     * Modified core generate action method
     *
     * @param Varien_Simplexml_Element $node
     * @param Varien_Simplexml_Element $parent
     * @return Mage_Core_Model_Layout
     */
    protected function _generateAction($node, $parent)
    {
        if (isset($node['ifconfig']) && ($configPath = (string)$node['ifconfig'])) {
            $ifConfig = Mage::getStoreConfigFlag($configPath);
            $nodeArray = (array)$node;

            $advIfconfig = null;
            $dependsCheck = false;
            $requiredDepends = false;

            if (isset($nodeArray['adv_ifconfig'])) {
                $advIfconfig = (array)$nodeArray['adv_ifconfig'];

                if (isset($advIfconfig['depends_check'])) {
                    $dependsCheck = $advIfconfig['depends_check'];
                }

                if (isset($advIfconfig['required_depends'])) {
                    $requiredDepends = $advIfconfig['required_depends'];
                }
            }

            if ($ifConfig && $dependsCheck) {
                if ($dependsCheck == 1 || $dependsCheck == 'tree') {
                    $configPath = explode('/', $configPath);
                    $ifConfig = $ifConfig
                        && Mage::helper('advifconfig')->checkSystemConfigNodeDepends(
                            $configPath[0], // Section
                            $configPath[1], // Group
                            $configPath[2], // Field
                            $ifConfig
                        );
                }

                if (($dependsCheck == 1 || $dependsCheck == 'required')
                    && $requiredDepends) {
                    $additionalDepends = array_map('trim',
                        explode(',', $requiredDepends)
                    );

                    foreach ($additionalDepends as $depend) {
                        $ifConfig = $ifConfig && Mage::getStoreConfigFlag($depend);
                    }
                }
            }

            if (!$ifConfig) {
                return $this;
            }

            if (isset($advIfconfig['custom_rules'])) {
                $data = array_merge(
                    array('ifconfig' => false), // Default value for custom rules ifconfig
                    (array)$advIfconfig['custom_rules']
                );
                $data = new Varien_Object($data);

                Mage::dispatchEvent('adv_ifconfig_custom_rules', $data);

                if (!$data->getIfconfig()) {
                    return $this;
                }
            }
        }

        $method = (string)$node['method'];
        if (!empty($node['block'])) {
            $parentName = (string)$node['block'];
        } else {
            $parentName = $parent->getBlockName();
        }

        $_profilerKey = 'BLOCK ACTION: '.$parentName.' -> '.$method;
        Varien_Profiler::start($_profilerKey);

        if (!empty($parentName)) {
            $block = $this->getBlock($parentName);
        }
        if (!empty($block)) {

            $args = (array)$node->children();
            unset($args['@attributes']);

            if (isset($args['adv_ifconfig'])) {
                unset($args['adv_ifconfig']);
            }

            foreach ($args as $key => $arg) {
                if (($arg instanceof Mage_Core_Model_Layout_Element)) {
                    if (isset($arg['helper'])) {
                        $helperName = explode('/', (string)$arg['helper']);
                        $helperMethod = array_pop($helperName);
                        $helperName = implode('/', $helperName);
                        $arg = $arg->asArray();
                        unset($arg['@']);
                        $args[$key] = call_user_func_array(array(Mage::helper($helperName), $helperMethod), $arg);
                    } else {
                        /**
                         * if there is no helper we hope that this is assoc array
                         */
                        $arr = array();
                        foreach($arg as $subkey => $value) {
                            $arr[(string)$subkey] = $value->asArray();
                        }
                        if (!empty($arr)) {
                            $args[$key] = $arr;
                        }
                    }
                }
            }

            if (isset($node['json'])) {
                $json = explode(' ', (string)$node['json']);
                foreach ($json as $arg) {
                    $args[$arg] = Mage::helper('core')->jsonDecode($args[$arg]);
                }
            }

            $this->_translateLayoutNode($node, $args);
            call_user_func_array(array($block, $method), $args);
        }

        Varien_Profiler::stop($_profilerKey);

        return $this;
    }
}
