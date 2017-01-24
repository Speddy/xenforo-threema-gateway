<?php
/**
 * Debug mode log option.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2016 rugk
 * @license MIT
 */

class ThreemaGateway_Option_DebugModeLog
{
    /**
     * @var string Default file path
     */
    const DEFAULT_PATH = 'internal_data/threemagateway/receivedmsgs.log';

    /**
     * Renders the debug mode log setting.
     *
     * Basically it just hides the setting if the debug mode of XenForo is disabled.
     *
     * @param XenForo_View $view           View object
     * @param string       $fieldPrefix    Prefix for the HTML form field name
     * @param array        $preparedOption Prepared option info
     * @param bool         $canEdit        True if an "edit" link should appear
     *
     * @return XenForo_Template_Abstract Template object
     */
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $preparedOption['option_value'] = self::correctOption($preparedOption['option_value']);

        // hide option when disabled and debug mode is off (so that users are not confused)
        if (!XenForo_Application::debugMode() && !$preparedOption['option_value']['enabled']) {
            return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal('threemagateway_option_list_option_hidden', $view, $fieldPrefix, $preparedOption, $canEdit);
        }

        // set options
        $preparedOption['edit_format']  = 'onofftextbox';
        $preparedOption['formatParams'] = [
            'onoff' => 'enabled',
            'value' => 'path',
            'type' => 'textbox',
            'default' => self::DEFAULT_PATH,
            'placeholder' => self::DEFAULT_PATH
        ];

        //pass this to the default handler
        return XenForo_ViewAdmin_Helper_Option::renderPreparedOptionHtml($view, $preparedOption, $canEdit);
    }

    /**
     * Verifies whether the dir of the file is valid (can be created) and is writable.
     *
     * @param string             $filepath   Input
     * @param XenForo_DataWriter $dataWriter
     * @param string             $fieldName  Name of field/option
     *
     * @return bool
     */
    public static function verifyOption(&$filepath, XenForo_DataWriter $dataWriter, $fieldName)
    {
        $filepath = self::correctOption($filepath);

        // check path & (create) dir
        $dirpath     = dirname($filepath['path']);
        $absoluteDir = XenForo_Application::getInstance()->getRootDir() . '/' . $dirpath;
        if (!ThreemaGateway_Handler_Validation::checkDir($absoluteDir)) {
            $dataWriter->error(new XenForo_Phrase('threemagw_invalid_debuglogpath'), $fieldName);
            return false;
        }

        // auto-remove existing file if necessary
        self::removeLog($filepath);

        return true;
    }

    /**
     * Remove the log file.
     *
     * @param  array $option option setting
     * @return bool
     */
    public static function removeLog($option)
    {
        // to be sure check the path again
        $option = self::correctOption($option);

        // check pre-conditions
        if (!$option['enabled'] || !file_exists($option['path'])) {
            return false;
        }

        // remove file
        return unlink(realpath($option['path']));
    }

    /**
     * Corrects the option array.
     *
     * @param  array  $option
     * @return string
     */
    protected static function correctOption($option)
    {
        // correct value
        if (empty($option)) {
            /** @var XenForo_Options $xenOptions */
            $xenOptions = XenForo_Application::getOptions();

            // save file path even if disabled
            $option['enabled'] = 0;
            $option['path']    = $xenOptions->threema_gateway_logreceivedmsgs['path'];
        }

        // set default value
        if (empty($option['path'])) {
            $option['path'] = self::DEFAULT_PATH;
        }

        // correct path
        if (substr($option['path'], 0, 1) == '/') {
            $option['path'] = substr($option['path'], 1);
        }

        return $option;
    }
}
