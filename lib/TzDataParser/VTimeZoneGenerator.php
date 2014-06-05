<?php

namespace Sabre\TzServer\TzDataParser;

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
        $until = null;
        $lastTzBlock = null;

        $vcal = new VCalendar();
        $vtimezone = $vcal->add('VTIMEZONE', [
            'TZID' => $zoneInfo['name'],
        ]);


        foreach($zoneInfo['rules'] as $zoneLine) {

            $start = $until;
            $until = $zoneLine->getUntil();

            if (is_null($start)) {
                continue;
            }

            $newOffset = $zoneLine->getOffset();

            $lastOffset = isset($lastTzBlock)?(string)$lastTzBlock->TZOFFSETTO:$newOffset;

            $tzBlock = new Component($vcal, 'STANDARD', [
                'DTSTART' => gmdate('Ymd\\THis', $start),
                'TZOFFSETFROM' => $lastOffset,
                'TZOFFSETTO' => $newOffset, 
            ]);
            $vtimezone->add($tzBlock);

            if (!isset($this->parser->rules[$zoneLine->rules])) {
                throw new Exception('Could not find ruleset:' . $zoneLine->rules);
            }

            foreach($this->parser->rules[$zoneLine->rules] as $rule) {

                $ruleStartTime = $rule->getStartTime();

                if ($ruleStartTime > $until) {
                    // The rule only goes in effect after this line in the zone 
                    // has already ended... so we stop parsing rules.
                    break;
                }
                if ($ruleStartTime > $start) {
                    $tzBlock->DTEND = gmdate('Ymd\\THis', $ruleStartTime);
                    $lastTzBlock = $tzBlock;

                    $componentType = $rule->isDst()?'DAYLIGHT':'STANDARD'; 

                    $tzBlock = new Component($vcal, $componentType, [
                        'DTSTART' => gmdate('Ymd\\THis', $ruleStartTime),
                        'TZOFFSETFROM' => (string)$lastTzBlock->TZOFFSETTO,
                        'TZOFFSETTO' => $rule->calculateNewOffset($zoneLine->getOffset(), true),
                    ]);
                    $vtimezone->add($tzBlock);

                } else {
                    die('Don\'t know what to do');
                }

            }

            $lastTzBlock = $tzBlock;

        }

        echo $vtimezone->serialize();

    }

}
