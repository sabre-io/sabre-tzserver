<?php

namespace Sabre\TzServer\TzDataParser;

use Exception;

class Parser {

    public $rules = array();
    public $zones = array();
    protected $stream;

    function parseFile($file) {

        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new Exception("Could not open file: $file");
        }
        $this->stream = $handle;
        $this->parseStream();

    }

    function parseStream() {

        while($line = $this->getLine()) {

            $this->parseLine($line);

        }

    }

    protected function parseLine(array $line) { 

        switch($line[0]) {
            case 'Rule' :
                $this->parseRule($line);
                break;
            case 'Zone' :
                $this->parseZone($line);
                break;
            case 'Link' :
                break;
            default :
                echo "Unknown line: " . implode("\t", $line), "\n";
                break;

        }

    }

    protected function parseRule(array $line) {

        if (count($line) < 9) {
            echo "Invalid rule: " . implode("--t--", $line);
            return;
        }
        if (!isset($this->rules[$line[1]])) {
            $this->rules[$line[1]] = [];
        } 
        $this->rules[$line[1]][] = new Rule([
            'name'   => $line[1],
            'from'   => $line[2],
            'to'     => $line[3],
            'type'   => $line[4],
            'in'     => $line[5],
            'on'     => $line[6],
            'at'     => $line[7],
            'save'   => $line[8],
            'letter' => $line[9],
        ]);

    }

    protected function parseZone(array $line) {

        $zone = [
            'name' => $line[1],
            'rules' => [],
        ];

        $lastLine = null;
        do {
            if (count($line) < 5) {
                echo "Invalid zone: " . implode("|", $line), ". Last line: " . implode("|", $lastLine) . "\n";
                print_r($line);
                print_r($zone);
                die();
                break; 
            }
            $until = null;
            if (isset($line[5])) {
                $until = implode(' ', array_slice($line,5));
            }
            $zone['rules'][] = new ZoneLine([
                'gmtoff' => $line[2],
                'rules' => $line[3],
                'format' => $line[4],
                'until' => $until,
            ]);
            if ($until) {

                // If there was an 'until' clause, it means we need to 
                // parse the next line as well.
                $lastLine = $line;
                $line = $this->getLine();
                if (is_null($line)) break;

                // Sometimes there's 2, sometimes theres 3 empty parts 
                // before the continuation rules starts. So we're 
                // removing all empty parts, and addin them again.
                while($line[0]==="") {
                    array_shift($line);
                }
                // Adding two empty parts again.
                array_unshift($line, "", "");
                
            }
        } while($until);
        $this->zones[$zone['name']] = new Zone($zone);

    }

    protected function getLine() {

        do {
            $line = fgets($this->stream);
            if ($line===false) return;
            if ($line[0]==='#' || trim($line)=="") {
                continue;
            }
            // If we got here.. it's a valid line
          
            // Stripping comments
            if (strpos($line,'#')!==false) {
                $line = rtrim(substr($line, 0, strpos($line,'#')));
            }
            // Is thee anything left?
            if (!trim($line,"\t ")) {
                continue;
            }
            break;
        } while(true);

        $line = preg_split("/[\s]+/", $line);
        return $line;

    }

}
