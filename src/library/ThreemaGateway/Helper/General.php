<?php
/**
 * General template helpers.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2015-2016 rugk
 * @license MIT
 */

class ThreemaGateway_Helper_General
{
    /**
     * XenForo template helper: threemaregex.
     *
     * Returns the regular expression for a group of Threema IDs from
     * ThreemaGateway_Constants::REGEX_THREEMA_ID.
     *
     * @param  string $idgroup gateway, personal or any
     * @return string
     */
    public static function threemaRegEx($idgroup)
    {
        return ThreemaGateway_Constants::REGEX_THREEMA_ID[$idgroup];
    }

    /**
     * XenForo template helper: threemaidverify.
     *
     * Checks whether a passed string is a Threema ID. This uses
     * ThreemaGateway_Handler->checkThreemaId().
     *
     * @param  string $threemaid      The Threema ID to check.
     * @param  string $type           The type of the Threema ID: personal,
     *                                gateway, any (default: personal)
     * @param  bool   $checkExistence Whether not only formal aspects should
     *                                be checked, but also the existence of the
     *                                ID.
     * @return bool
     */
    public static function isThreemaId($threemaid, $type = 'personal', $checkExistence)
    {
        /** @var array $error */
        $error = []; //error array is not used anyway
        return ThreemaGateway_Handler_Validation::checkThreemaId($threemaid, $type, $error, $checkExistence);
    }

    /**
     * XenForo template helper: threemagwcensor.
     *
     * Censores the beginning of a string.
     *
     * @param string $string     The string to censor.
     * @param int    $charsLeave (optional) How much characters should *not* be
     *                           censored at the end of the string
     * @param string $censorChar (optional) The char which should be used to
     *                           censor the string.
     *
     * @return string
     */
    public static function censorString($string, $charsLeave = 0, $censorChar = '*')
    {
        /** @var int $length The length to censor */
        $length = strlen($string) - $charsLeave;
        if ($length <= 0) { //error
            return $string;
        }
        /** @var string $orgstr The original/unmodified string part */
        $orgstr    = str_split($string, $length);
        /** @var string $censorstr The censored string part */
        $censorstr = str_repeat($censorChar, $length);

        if (count($orgstr) < 2) {
            //happens if $charsLeave = 0 -> censor whole string
            return $censorstr;
        }
        return $censorstr . $orgstr[1];
    }

    /**
     * Rounds a given timestamp (unix time) to the day.
     *
     * @param int $time
     * @param bool $roundUp set to true to round up to the next day
     *
     * @return int
     */
    public static function roundToDay($time, $roundUp = false)
    {
        // calculate days and round down to previous day time
        /** @var int $days */
        $days = floor($time / 60 / 60 / 24);

        // round up if needed
        if ($roundUp) {
            $days += 1;
        }

        // calculate seconds again
        return $days * 24 * 60 * 60;
    }

    /**
     * Rounds a given amount of minutes.
     *
     * Also just uses {@see roundToDay()} in the background, but minutes must be
     * given as a parameter.
     *
     * @return int
     */
    public static function roundToDayRelative($minutes, $roundUp = false)
    {
        return self::roundToDay($minutes * 60, $roundUp);
    }
}
