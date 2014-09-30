<?php

class Shopgo_AdvIfconfig_Model_Magento_Core_Layout extends Mage_Core_Model_Layout
{
    /**
     * Enter description here...
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

            if ($nodeArray['depends_check'] && $ifConfig) {
                if ($nodeArray['depends_check'] == 1 || $nodeArray['depends_check'] == 'tree') {
                    $configPath = explode('/', $configPath);
                    $ifConfig = $ifConfig
                        && Mage::helper('advifconfig')->checkSystemConfigNodeDepends(
                            $configPath[0], // Section
                            $configPath[1], // Group
                            $configPath[2], // Field
                            $ifConfig
                        );
                }

                if (($nodeArray['depends_check'] == 1 || $nodeArray['depends_check'] == 'required')
                    && $nodeArray['required_depends']) {
                    $additionalDepends = array_map('trim',
                        explode(',', $nodeArray['required_depends'])
                    );

                    foreach ($additionalDepends as $depend) {
                        $ifConfig = $ifConfig && Mage::getStoreConfigFlag($depend);
                    }
                }
            }

            if (!$ifConfig) {
                return $this;
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

            if (isset($args['depends_check'])) {
                unset($args['depends_check']);
            }

            if (isset($args['required_depends'])) {
                unset($args['required_depends']);
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
