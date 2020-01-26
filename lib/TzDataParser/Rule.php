<?php

namespace Sabre\TzServer\TzDataParser;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * A rule is a transition. It changes from one timezone specification to
 * another.
 *
 * It often just represents the change between DST and non-DST, but many other
 * situations are captured by these rules.
 */
class Rule extends TZDataObj
{
    /**
     * Name of the rule such as "Chicago".
     */
    public $name;

    /**
     * Year when the transition happens.
     */
    public $from;

    /**
     * If the rule repeats, this will contain the end year.
     *
     * If the rule does not repeat, this will contain the string 'only'.
     * If the rule repeats forever, this will contain 'max'.
     */
    public $to;
    public $type;
    public $in;
    public $on;
    public $at;
    public $save;
    public $letter;

    public $zoneContext;

    /**
     * Returns the time and date when the rule first kicks in.
     *
     * Returned in UTC.
     */
    public function getStartTime()
    {
        return $this->getOccurenceTime($this->from);
    }

    /**
     * Returns the last occurence of the rule.
     *
     * Returned in UTC.
     */
    public function getEndTime()
    {
        $year = $this->to;
        if ('only' === $year) {
            $year = $this->from;
        }
        if ('max' === $year) {
            return null;
        }

        return $this->getOccurenceTime($year);
    }

    /**
     * Finds the most recent timestamp, starting counting from the given
     * timestamp.
     *
     * If there was no occurence (transition) before the timestamp, null is
     * returned.
     *
     * @param int $timeStamp
     *
     * @return void
     */
    public function getMostRecentOccurenceSince($timeStamp)
    {
        $startTime = $this->getStartTime();
        if ($timeStamp < $startTime) {
            return null;
        }
        $year = date('Y', $timeStamp);
        while (true) {
            $occurence = $this->getOccurenceTime($year);
            if ($occurence < $timeStamp) {
                return $occurence;
            }
            --$year;
            if ($year < $this->from) {
                throw new Exception('This normally should not happen.');
            }
        }
    }

    /**
     * Finds the next occurence, right after the given timestamp.
     * If there is no occurence after the timestamp, return null.
     *
     * @param int $timeStamp
     *
     * @return void
     */
    public function getNextOccurenceAfter($timeStamp)
    {
        $endTime = $this->getEndTime();
        $startTime = $this->getStartTime();
        if ($timeStamp > $endTime) {
            return null;
        }

        $year = date('Y', $timeStamp > $startTime ? $startTime : $timeStamp);
        while (true) {
            $occurence = $this->getOccurenceTime($year);
            if ($occurence > $timeStamp) {
                return $occurence;
            }
            ++$year;
            if ($year > $this->to) {
                return null;
            }
        }
    }

    /**
     * Calculates the rule offset.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->zoneContext->getOffset() + $this->parseOffset($this->save);
    }

    /**
     * Returns an occurence of a transition in the specified year.
     */
    protected function getOccurenceTime($year)
    {
        if (!preg_match('#^ [0-9]{4} $ #x', $year)) {
            throw new Exception('Invalid year field: '.$year);
        }
        $month = $this->getMonth($this->in);

        if (preg_match('#^ [0-9]{1,2} $ #x', $this->on)) {
            $time = gmmktime(0, 0, 0, $month, $this->on, $year);
        } elseif (preg_match('#^ ([A-Z][a-z]{2})>=([0-9]{1,2}) $#x', $this->on, $matches)) {
            $minDay = $matches[2];
            if ($minDay <= 7) {
                $ord = 'first';
            } elseif ($minDay <= 14) {
                $ord = 'second';
            } elseif ($minDay <= 21) {
                $ord = 'third';
            } else {
                throw new exception('error calculating '.$this->on);
            }
            $dt = new DateTime($ord.' '.strtolower($matches[1]).' of '.$this->in.' '.$year, new DateTimeZone('UTC'));
            if ($dt->format('d') < $minDay) {
                $dt->modify('+1 week');
            }
            $time = $dt->getTimeStamp();
        } elseif (preg_match('#^ last([A-Z][a-z]{2}) $#x', $this->on, $matches)) {
            $time = strtotime('last '.strtolower($matches[1]).' of '.$this->in.' '.$year);
        } else {
            throw new \Exception('Unknown "on" format: '.$this->on);
        }

        $time += $this->getAt();

        return $time;
    }

    /*
    public function getRRule() {

        if ($this->to === 'only') {
            return null;
        }
        if (preg_match('#^ [0-9]{4} $#x', $this->to)) {
            $at = $this->getAt();
            $time = gmmktime(floor($at / 3600) , floor(($at / 60) % 60), $at % 60, $this->getMonth($this->in), 1, $this->to);
            // Time is now a 'local' timestamp, not utc. We need to deduct the
            // offset.
            $time+$this->zoneContext->getOffset();
            return 'FREQ=YEARLY;UNTIL=' . gmdate('Ymd\\This') . 'Z';
        }
        throw new Exception('Invalid "to" format: ' . $this->to);

    }


    public function isDst() {

        return $this->save!=='0';

    }*/

    /**
     * Returns the time of the day, in seconds from midnight, in utc.
     *
     * Note that the number can be negative. The time of the day is always
     * positive in local time, but it could be a negative number of seconds
     * when calculated from utc.
     *
     * @return int
     */
    private function getAt()
    {
        if (!preg_match('#^ ([0-9]{1,2}):([0-9]{2})(s|u)? $ #x', $this->at, $matches)) {
            throw new Exception('Invalid "at" format: '.$this->at);
        }
        $at = $matches[1] * 3600 + $matches[2] * 60;
        switch (isset($matches[3]) ? $matches[3] : '') {
            case '':
                // Wall time. Deduct offset and dst.
                $at -= $this->zoneContext->getOffset();
                //$at -= $this->parseOffset($this->save);
                break;
            case 's':
                // Standard time. Deduct offset
                $at -= $this->zoneContext->getOffset();
                break;
            case 'u':
                // UTC.. do nothing
                $at += $this->zoneContext->getOffset();
                break;
            default:
                throw new \Exception('Unknown at postfix: '.$matches[3]);
        }

        return $at;
    }
}
