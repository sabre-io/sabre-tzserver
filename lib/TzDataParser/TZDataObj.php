<?php

namespace Sabre\TzServer\TzDataParser;

use Exception;
use InvalidArgumentException;

abstract class TZDataObj{

    public function __construct(array $ruleParts) {

        foreach($ruleParts as $k=>$v) {
            $this->$k = $v;
        }

    }

    /**
     * Parses a date-time string in the format:
     *
     * 2014 Jun 04 21:56:01
     *
     * Almost every component is optional and will default to 1 for day and
     * month, 0 for hour and minute.
     *
     * Returns a timestamp *in local time*.
     *
     * @param string $timeString
     * @return int
     */
    protected function parseTime($timeString) {

        if (!$timeString) return null;
        if (!preg_match('/^([0-9]{4})(?:\W)?([A-Za-z]{3})?(?:\W)*([0-9]+)?(?:\W)*([0-9]:[0-9]+)?(:[0-9]+)?$/', trim($timeString), $matches)) {
            echo "Unknown timeString: " . $timeString . "\n";
            return null;
        }

        $month = 1;
        if (isset($matches[2])) {
            $monthMap = [
                'Apr' => 4,
                'May' => 5,
                'Jul' => 7,
            ];
            if (isset($monthMap[$matches[2]])) {
                $month = $monthMap[$matches[2]];
            } else {
                echo "Unknown month: $matches[2] !\n";
            }
        }

        $time = isset($matches[4]) && $matches[4] ? $matches[4] : '00:00';
        $time.= isset($matches[5])?$matches[5]:':00';
        $time = explode(':', $time);

        // Even though time() works with UTC, our timestamps are actually local
        // timestamps.
        return gmmktime(
            $time[0],
            $time[1],
            $time[2],
            $month,
            isset($matches[3])?$matches[3]:1,
            $matches[1]
        );

    }

    /**
     * Parses an offset string in the format:
     *
     * -05:00:00
     * 03:00:00
     *
     * The second part is optional.
     *
     * Returns the offset in seconds from GMT.
     *
     * @param string $offsetString
     * @param int
     */
    protected function parseOffset($offsetString) {

        if ($offsetString==='0') {
            return 0;
        }
        if (!preg_match('#^ (-)? ([0-9]{1,2}) : ([0-9]{2}) (?: : ([0-9]{2}))? $ #x', $offsetString, $matches)) {
            throw new Exception('Unknown offset string: ' .$offsetString);
        }

        $time = isset($matches[4])?$matches[4]:0;
        $time+=$matches[3]*60;
        $time+=$matches[2]*3600;

        if ($matches[1]==='-') {
            $time = 0-$time;
        }
        return $time;

    }

    /**
     * Formats an offset in seconds to the following format:
     *
     * +0100
     * -0500
     * +002705
     *
     * @param int $offsetTime
     * @return string
     */
    protected function formatOffset($offsetTime) {

        if (!is_int($offsetTime)) {
            throw new InvalidArgumentException('offsetTime MUST be an integer');
        }

        $str = $offsetTime<0?'-':'+';
        $offsetTime = abs($offsetTime);

        $hours = floor($offsetTime/3600);
        $minutes = floor(($offsetTime / 60) % 60);
        $seconds = $offsetTime % 60;

        $str.=sprintf('%02d%02d', $hours, $minutes);
        if ($seconds>0) $str.=sprintf('%02d', $seconds);

        return $str;

    }

    /**
     * Returns the month number, based on a month string.
     */
    protected function getMonth($str) {

        $monthMap = [
            'Mar' => 3,
            'Apr' => 4,
            'May' => 5,
            'Jun' => 6,
            'Jul' => 7,
            'Sep' => 9, 
            'Oct' => 10,
        ];
        if (isset($monthMap[$str])) {
            return $monthMap[$str];
        }
        throw new Exception("Unknown month: $str");

    }

}
