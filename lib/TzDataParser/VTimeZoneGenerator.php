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

        $previousLine = null;
        $lastOffset = null;
        $newRule = null;

        foreach($zoneInfo->rules as $zoneLine) {

            if (!$previousLine) {
                // this is the first line.
                $previousLine = $zoneLine;
                continue;
            }

            $ruleStart = $previousLine->getUntil();
            if (!is_null($newRule)) {
                // Closing and saving the previous rule.
                if (!isset($newRule['end'])) {
                    $newRule['end'] = $ruleStart;
                }
                $newTzData['rules'][] = $newRule;
            }
            // Making sure all previous rules have 'until' set to this point.
            foreach($newTzData['rules'] as &$v){
                if (!isset($v['until'])) $v['until'] = $ruleStart;
            }
            $newRule = [
                'start' => $ruleStart,
                'comment' => 'zone ' . $zoneInfo->name,
                'isDst' => 0,
            ];
            if (is_null($lastOffset)) {
                $newRule['offsetFrom'] = $zoneLine->getOffset();
            } else {
                $newRule['offsetFrom'] = $lastOffset;
            }
            $newRule['offsetTo'] = $zoneLine->getOffset();
            $lastOffset = $zoneLine->getOffset();

            if ($zoneLine->rules === '-') {
                $namedRules = [];
            } elseif (isset($this->parser->rules[$zoneLine->rules])) {
                $namedRules = $this->parser->rules[$zoneLine->rules];
            } else {
                throw new Exception('Unknown named rule: ' .$zoneLine->rules);
            }
            foreach($namedRules as $namedRule) {

                $namedRule->zoneContext = $zoneLine;

                // Every 'named rule' represents a transition.
                $ruleStart = $namedRule->getStartTime();
                $ruleEnd = $namedRule->getEndTime();

                // If the start of the rule is beyond the end of the zone, we
                // can stop parsing rules.
                if (!is_null($zoneLine->getUntil()) && $ruleStart > $zoneLine->getUntil()) {
                    break;
                }
                // If the end of the rule is below the start of the current 
                // zone, we should just skip the rule.
                if (!is_null($ruleEnd) && $ruleEnd < $newRule['start']) {
                    continue;
                }

                // Saving the old rule.
                if (!isset($newRule['end'])) {
                    $newRule['end'] = $ruleStart;
                }
                $newTzData['rules'][] = $newRule;

                // New rule starts here.
                $newRule = [
                    'start' => $ruleStart,
                    'offsetFrom' => $lastOffset,
                    'offsetTo' => $namedRule->getOffset(),
                    'comment' => 'namedrule ' . $namedRule->name,
                    'isDst' => $namedRule->save!=='0',
                    'end' => $ruleEnd, 
                ];
                $lastOffset = $namedRule->getOffset();

            }

            $previousLine = $zoneLine;

        }

        $newTzData['rules'][] = $newRule;
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
            echo "repeat until ";
            if (isset($rule['until'])) {
                echo gmdate("Y-m-d H:i:s", $rule['until']);
            } else {
                echo "forever            ";
            }
            echo " " . $rule['comment'] . "\n";
        }

    }

}
