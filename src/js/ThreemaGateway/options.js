/**
 * @file JS helpers for handling options in ACP.
 *
 * @author rugk
 * @copyright Copyright (c) 2016 rugk
 * @license MIT
 */

jQuery(document).ready(function() {
    'use strict';

    ReceiveCallback.init();
});

/**
 * ReceiveCallback - Handling the option threema_gateway_receivecallback.
 *
 * @return {object} Methods: update
 */
var ReceiveCallback = (function () {
    'use strict';
    var me = {};
    var $inputElem;
    var $hiddenElem;

    /**
     * getOrgData - Returns the input data of the field the user can see.
     *
     * @private
     * @return {string}
     */
    function getOrgData() {
        return $inputElem.text();
    }

    /**
     * setOrgData - Sets the value of the input field the user can see.
     *
     * @private
     * @param  {string} data The data to set.
     * @return {string}
     */
    function setOrgData(data) {
        if ($inputElem.text() !== data) {
            return $inputElem.text(data);
        }
    }

    /**
     * setHiddenData - Change data of the hidden input.
     *
     * @private
     * @param  {string} data The data to set.
     * @return {string}
     */
    function setHiddenData(data) {
        return $hiddenElem.val(data);
    }

    /**
     * filterData - Filter the input data.
     *
     * @private
     * @param  {string} data The data to filter.
     * @return {string}
     */
    function filterData(data) {
        // remove all bad characters
        // https://regex101.com/r/g4Dkb7/1
        return data.replace(/[^\w_-]+/ig, '');
    }

    /**
     * init - Initialize handler.
     *
     */
    me.init = function init() {
        // set variables
        $inputElem = $('.threemagw_receivecallback_input');
        $hiddenElem = $('.threemagw_receivecallback_hiddeninput');

        // register input trigger/event
        $inputElem.on('input', me.update);
    };

    /**
     * update - Update the output field (and, if necessary, also the output
     * field)
     *
     */
    me.update = function update() {
        var data;

        data = filterData(getOrgData());
        setOrgData(data);
        setHiddenData(data);
    };

    return me;
})();
