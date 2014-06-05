<?php

namespace Sabre\TzServer\TzDataParser;

class Rule extends TZDataObj {

    public $name;
    public $from;
    public $to;
    public $type;
    public $in;
    public $on;
    public $at;
    public $save;
    public $letter;

    public function getStartTime() {

        if (!preg_match('#^ [0-9]{4} $ #x', $this->from)) {
            throw new Exception('Invalid "from" field: ' . $this->from);
        }

        if (!$this->at) {
            $at = [0,0];
        } else {
            $at = explode(':', $this->at);
        }
        if (count($at)!==2) {
            throw new Exception('Unknown at: ' . $this->at);
        }

        return gmmktime($at[0], $at[1], 0, $this->getMonth($this->in), 1, $this->from);

    }

    public function isDst() {

        return $this->save!=='0';

    }

    /**
     * Returns a new offset time, based on the zone offset time.
     *
     * @param int $baseOffset
     * @return void
     */
    public function calculateNewOffset($zoneOffset, $strFormat = false) {

        $offset = $zoneOffset + $this->parseOffset($this->save);
        if ($strFormat) {
            $offset = $this->formatOffset($offset);
        }
        return $offset;

    }

}
