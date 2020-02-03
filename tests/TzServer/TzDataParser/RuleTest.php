<?php

namespace Sabre\TzServer\TzDataParser;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase
{
    public function getRule($yearUntil = 'only')
    {
        $zoneLine = new ZoneLine([
            'gmtoff' => '-6:00',
            'rules' => 'Chicago',
            'format' => 'C%sT',
            'until   => 1963 Mar  1 2:00',
        ]);

        $rule = new Rule([
            'name' => 'Chicago',
            'from' => '1920',
            'to' => $yearUntil,
            'type' => '-',
            'in' => 'Jun',
            'on' => '13',
            'at' => '2:00',
            'save' => '1:00',
            'letter' => 'D',
        ]);
        $rule->zoneContext = $zoneLine;

        return $rule;
    }

    public function testGetStartTime()
    {
        $rule = $this->getRule();
        $time = $rule->getStartTime();

        $expected = new DateTime('1920-06-13 02:00', new DateTimeZone('-06:00'));
        $this->assertEquals($expected->getTimeStamp(), $time, 'Got: '.gmdate(DATE_ATOM, $time));
    }

    public function testGetEndTimeOnly()
    {
        $rule = $this->getRule();
        $time = $rule->getStartTime();

        $expected = new DateTime('1920-06-13 02:00', new DateTimeZone('-06:00'));
        $this->assertEquals($expected->getTimeStamp(), $time, 'Got: '.gmdate(DATE_ATOM, $time));
    }

    public function testGetEndTime()
    {
        $rule = $this->getRule('1960');
        $time = $rule->getStartTime();

        $expected = new DateTime('1920-06-13 02:00', new DateTimeZone('-06:00'));
        $this->assertEquals($expected->getTimeStamp(), $time, 'Got: '.gmdate(DATE_ATOM, $time));
    }
}
