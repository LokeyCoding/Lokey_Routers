<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt
 *
 * @category   Mage
 * @package    OpenMage_Routers
 * @copyright  Copyright (c) 2012 Lokey Coding, LLC <ip@lokeycoding.com>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author     Lee Saferite <lee.saferite@lokeycoding.com>
 */

class OpenMage_Routers_Standard extends Mage_Core_Controller_Varien_Router_Standard
{
    /**
     * @var bool Flag indicating if the extended matching code should be active
     */
    protected $_extendedSearchActive = false;

    /**
     * Match the request
     *
     * This is actually a wrapper for the real match method
     * The wrapper adds the ability of having '/' characters
     * in the frontName
     *
     * @param Zend_Controller_Request_Http $request
     *
     * @return boolean
     */
    public function match(Zend_Controller_Request_Http $request)
    {
        // There is no need to run this code if there are no extended frontNames or a manually selected module name
        if (!$this->_extendedSearchActive || $request->getModuleName()) {
            return parent::match($request);
        }

        // This check will be run twice due to the unobtrusive way we are extending the core router
        if (!$this->_beforeModuleMatch()) {
            return false;
        }

        $originalPath = trim($request->getPathInfo(), '/');

        // There is no need to run this if it's an empty path
        if ($originalPath === '') {
            return parent::match($request);
        }

        $found = false;
        $frontNames = $this->_extractFrontNames($originalPath);
        foreach ($frontNames as $frontName => $workingPath) {
            // Force usage of the frontName we detected
            $request->setModuleName($frontName);

            // Change request PATH_INFO to replace real frontName with fake one
            $request->setPathInfo('[PARSED]/' . $workingPath);

            // Use detected frontName to try and resolve the request
            $found = parent::match($request);

            // Reset request PATH_INFO (just in case)
            $request->setPathInfo($originalPath);

            // If found, stop looping
            if ($found) {
                break;
            }

            // Reset module name as a cleanup
            $request->setModuleName(null);
        }

        if (!$found) {
            $found = parent::match($request);
        }

        return $found;
    }

    /**
     * Parse the PATH_INFO string passed looking for the best frontName
     *
     * This method will support frontNames with '/' characters
     * It matches the longest possible valid frontName
     *
     * NB: This WILL NOT match frontNames without a slash. This use case is handled via the parent match method
     *
     * @param string $path
     *
     * @return array
     */
    protected function _extractFrontNames($path)
    {
        $found = array();

        $p = explode('/', $path);

        $frontName = array_shift($p);
        while (count($p) > 0) {
            $frontNames = preg_grep('/^' . preg_quote($frontName . '/' . $p[0], '/') . '.*/', $this->_routes);
            if (count($frontNames) == 0) {
                break;
            }
            $frontName .= '/' . array_shift($p);
            $found[$frontName] = implode('/', $p);
        }

        $found = array_reverse($found, true);

        return $found;
    }

    /**
     * Extend the addModule method to check for a "/" character in frontNames
     *
     * @param string $frontName
     * @param string $moduleName
     * @param string $routeName
     *
     * @return Mage_Core_Controller_Varien_Router_Standard
     */
    public function addModule($frontName, $moduleName, $routeName)
    {
        if (strpos($frontName, '/') !== false) {
            $this->_extendedSearchActive = true;
        }

        return parent::addModule($frontName, $moduleName, $routeName);
    }
}
