<?php

namespace Sabre\TzServer\TzDataParser;

class ZoneLine extends TZDataObj
{
    public $gmtoff;
    public $rules;
    public $format;
    public $until;

    /**
     * Returns the 'until' time as a unix timestamp.
     */
    public function getUntil()
    {
        return $this->parseTime($this->until, $this->getOffset());
    }

    /**
     * Returns the offset from gmt in seconds.
     *
     * @return int
     */
    public function getOffset()
    {
        return $this->parseOffset($this->gmtoff);
    }
}
