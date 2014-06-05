<?php

namespace Sabre\TzServer\TzDataParser;

use DateTime;
use DateTimeZone;
use Exception;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component;

class VTimeZoneGenerator {

    protected $parser;

    function __construct(Parser $parser) {

        $this->parser = $parser;

    }

    function generate($tzid) {

        if (!isset($this->parser->zones[$tzid])) {
            throw new Exception('TZID not in zones list: ' .$tzid);
        }

        $zoneInfo = $this->parser->zones[$tzid];

        $newTzData = [
            'name'  => $zoneInfo->name,
            'rules' => [],
        ];

        $lastLine = null;
        $lastOffset = null;

        // Timezone data is split over two pieces of data:
        // zone info and 'named rules'.
        //
        // The zone spans a time, the 'named rules' specify transitions within
        // this time. To make things more interesting.. transitions can repeat.
        // We are first mapping out just the zones, and the first occurence of
        // every transition, and build from there.
        foreach($zoneInfo->rules as $zoneLine) {

            // The first 'line' is not really a zone beause there's no start
            // date. We skip it, but keep the end date.
            if (is_null($lastLine)) {
                $lastLine = $zoneLine;
                $lastOffset = $zoneLine->getOffset();
                continue;
            }

            // If we didn't have an offset from a previous rule.. we just use
            // the current offset.
            if (is_null($lastOffset)) $lastOffset = $zoneLine->getOffset();

            // When did the last rule end?
            $zoneLineStart = $lastLine->getUntil();

            // The start of a new zone means that any previous rules should get
            // their until date set.
            foreach($newTzData['rules'] as $rule) {
                if (!$rule['until']) $rule['until'] = $zoneLineStart;
            }

            if ($zoneLine->rules==='-') {
                // A simple case.. the zone just lasts until '$until'.
                $newTzData['rules'][] = [
                    'start' => $zoneLineStart,
                    'end'   => $zoneLine->getUntil(),
                    'until' => $zoneLine->getUntil(),
                    'offsetFrom' => $lastOffset,
                    'offsetTo' => $zoneLine->getOffset(),
                    'comment' => 'dash',
                ];

            } else {
                // There are transitions!!
                // First we check if there is a transition before the zone
                // starts, because we will then use that as our 'start' data.
                $transitions = $this->parser->rules[$zoneLine->rules];
                foreach($transitions as $transition) {
                    $transition->zoneContext = $zoneLine;
                }

                $firstTransition = null;
                $mostRecent = null;
                foreach($transitions as $tRule) {
                    $occ = $transition->getMostRecentOccurenceSince($zoneLineStart);
                    if (is_null($mostRecent) || $mostRecent < $occ) {
                        $mostRecent = $occ;
                        $firstTransition = $tRule;
                    }
                }

                $firstRuleTransition = null;
                // Going through all the transitions, and expanding their
                // dates.
                $addedRules = [];
                foreach($transitions as $tRule) {
                    $newRule = [
                        'start' => $tRule->getStartTime(),
                        'end'   => null,
                        'until' => $tRule->getEndTime(),
                        'offsetFrom' => $lastOffset,
                        'offsetTo' => $tRule->getOffset(),
                        'comment' => $tRule->name,
                        //'tRule' => $tRule,
                    ];
                    if (!is_null($zoneLine->getUntil()) && $newRule['start'] > $zoneLine->getUntil()) {
                        // The rule starts after the zone.. pointless to
                        // continue.
                        break;
                    }
                    if (is_null($firstRuleTransition) && $newRule['start'] < $firstRuleTransition) {
                        $firstRuleTransition = $newRule['start'];
                    }
                    $addedRules[] = $newRule;

                }

                // No rules fell into this zone.
                if (!count($addedRules)) {
                    $newTzData['rules'][] = [
                        'start' => $zoneLineStart,
                        'end'   => $zoneLine->getUntil(),
                        'until' => $zoneLine->getUntil(),
                        'offsetFrom' => $lastOffset,
                        'offsetTo' => $zoneLine->getOffset(),
                        'comment' => 'no-rules',
                    ];

                } else {

                    // If this is the very first zone, we need to preamble the
                    // data
                    if(!$firstRuleTransition && !$newTzData['rules']) {
                        $newTzData['rules'][] = [
                            'start' => $zoneLineStart,
                            'end'   => $firstRuleTransition,
                            'until' => $firstRuleTransition,
                            'offsetFrom' => $lastOffset,
                            'offsetTo' => $zoneLine->getOffset(),
                            'comment' => 'zone-preamble',
                        ];
                    }

                    foreach($addedRules as $addedRule) {

                        if ($addedRule['start'] < $zoneLineStart) {
                            $addedRule['start'] = $zoneLineStart;
                        }
                        // Finding the 'end' for every rule.
                        // At the very least, it should end when the zone ends.
                        $endTime = $zoneLine->getUntil();
                        foreach($transitions as $transition) {
                            $occ = $transition->getNextOccurenceAfter($addedRule['start']);
                            if (is_null($endTime) || $occ < $endTime) {
                                $endTime = $occ;
                            }
                        }
                        $addedRule['end'] = $endTime;
                        $newTzData['rules'][] = $addedRule;

                    }


                }

            }
            $lastLine = $zoneLine;
            $lastOffset = $zoneLine->getOffset();

        }

        $this->prettyPrint($newTzData);

    }

    function prettyPrint($newTzData) {

        $formatOffset = function($offsetTime) {

            $str = $offsetTime<0?'-':'+';
            $offsetTime = abs($offsetTime);

            $hours = floor($offsetTime/3600);
            $minutes = floor(($offsetTime / 60) % 60);
            $seconds = $offsetTime % 60;

            $str.=sprintf('%02d%02d', $hours, $minutes);
            if ($seconds>0) $str.=sprintf('%02d', $seconds);

            return $str;

        };

        echo $newTzData['name'], "\n\n";
        foreach($newTzData['rules'] as $rule) {
            echo gmdate("Y-m-d H:i:s", $rule['start']) . ' until ';
            if (isset($rule['end'])) {
                echo gmdate("Y-m-d H:i:s", $rule['end']);
            } else {
                echo "forever            ";
            }
            echo " " . $formatOffset($rule['offsetFrom']) . ' -> ' . $formatOffset($rule['offsetTo']) . " ";
            /*
            echo "repeat until ";
            if (isset($rule['until'])) {
                echo gmdate("Y-m-d H:i:s", $rule['until']);
            } else {
                echo "forever            ";
            }
             */
            echo " " . $rule['comment'];
            echo "\n";
        }

    }

}
