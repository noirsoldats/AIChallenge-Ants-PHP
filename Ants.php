<?php

define('MY_ANT', 0);
define('ANTS', 0);
define('DEAD', -1);
define('LAND', -2);
define('FOOD', -3);
define('WATER', -4);
define('UNSEEN', -5);

class Ants
{
    public $roundStart = 0;
    public $debugFlag = false;
    public $visTool = false;
    public $turns = 0;
    public $currentTurn = 0;
    public $rows = 0;
    public $cols = 0;
    public $loadtime = 0;
    public $turntime = 0;
    public $viewradius2 = 0;
    public $attackradius2 = 0;
    public $spawnradius2 = 0;
    public $map;
    public $myAnts = array();
    public $enemyAnts = array();
    public $myHills = array();
    public $enemyHills = array();
    public $deadAnts = array();
    public $food = array();

    public $AIM = array(
        'n' => array(-1, 0),
        'e' => array(0, 1),
        's' => array(1, 0),
        'w' => array(0, -1) );
    public $RIGHT = array (
        'n' => 'e',
        'e' => 's',
        's' => 'w',
        'w' => 'n');
    public $LEFT = array (
        'n' => 'w',
        'e' => 'n',
        's' => 'e',
        'w' => 's');
    public $BEHIND = array (
        'n' => 's',
        's' => 'n',
        'e' => 'w',
        'w' => 'e'
        );


    public function issueOrder($aRow, $aCol, $direction)
    {
        printf("o %s %s %s\n", $aRow, $aCol, $direction);
		$this->debug("Order: $aRow, $aCol, $direction\n");
        flush();
    }

    public function finishTurn()
    {
        echo("go\n");
        $this->debug("Remaining Time: ".($this->timeRemaining())."\n");
        $this->debug("-------TURN-------\n");
        flush();
    }
    
    public function timeRemaining(){
        $endTime = microtime(true);
        return (1.0 - ($endTime - $this->roundStart));
    }
    
    public function setup($data)
    {
        foreach ( $data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);
                $key = $tokens[0];
                if (property_exists($this, $key)) {
                    $this->{$key} = (int)$tokens[1];
                }
            }
        }
        for ( $row=0; $row < $this->rows; $row++) {
            for ( $col=0; $col < $this->cols; $col++) {
                $this->map[$row][$col] = LAND;
            }
        }
    }

    /** not tested */


    public function update($data)
    {
        // clear ant and food data
        foreach ( $this->myAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->myAnts = array();

        foreach ( $this->enemyAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->enemyAnts = array();

        foreach ( $this->deadAnts as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->deadAnts = array();

        foreach ( $this->food as $ant ) {
            list($row,$col) = $ant;
            $this->map[$row][$col] = LAND;
        }
        $this->food = array();
        
        $this->myHills = array();
        $this->enemyHills = array();

        # update map and create new ant and food lists
        foreach ( $data as $line) {
            if (strlen($line) > 0) {
                $tokens = explode(' ',$line);

                if (count($tokens) >= 3) {
                    $row = (int)$tokens[1];
                    $col = (int)$tokens[2];
                    if ($tokens[0] == 'a') {
                        $owner = (int)$tokens[3];
                        $this->map[$row][$col] = $owner;
                        if( $owner === 0) {
                            $this->myAnts []= array($row,$col);
                        } else {
                            $this->enemyAnts []= array($row,$col);
                        }
                    } elseif ($tokens[0] == 'f') {
                        $this->map[$row][$col] = FOOD;
                        $this->food []= array($row, $col);
                    } elseif ($tokens[0] == 'w') {
                        $this->map[$row][$col] = WATER;
                    } elseif ($tokens[0] == 'd') {
                        if ($this->map[$row][$col] === LAND) {
                            $this->map[$row][$col] = DEAD;
                        }
                        $this->deadAnts []= array($row,$col);
                    } elseif ($tokens[0] == 'h') {
                        $owner = (int)$tokens[3];
                        if ($owner === 0) {
                            $this->myHills []= array($row,$col);
                        } else {
                            $this->enemyHills []= array($row,$col);
                        }
                    }
                }
            }
        }
    }


    public function passable($row, $col)
    {
        return $this->map[$row][$col] > WATER;
    }

    public function unoccupied($row, $col) {
        return in_array($this->map[$row][$col], array(LAND, DEAD));
    }

    public function destination($row, $col, $direction)
    {
        list($dRow, $dCol) = $this->AIM[$direction];
        $nRow = ($row + $dRow) % $this->rows;
        $nCol = ($col +$dCol) % $this->cols;
        if ($nRow < 0) $nRow += $this->rows;
        if ($nCol < 0) $nCol += $this->cols;
        return array( $nRow, $nCol );
    }

    public function distance($row1, $col1, $row2, $col2) {
        $dRow = abs($row1 - $row2);
        $dCol = abs($col1 - $col2);

        $dRow = min($dRow, $this->rows - $dRow);
        $dCol = min($dCol, $this->cols - $dCol);

        return sqrt($dRow * $dRow + $dCol * $dCol);
    }

    public function direction($row1, $col1, $row2, $col2) {
        $d = null;
        $inverse = false;
        $max_row = $this->rows;
        $max_col = $this->cols;

        if(abs($row1-$row2) > 1){
            $inverse = true;
        }
        if(abs($col1-$col2) > 1){
            $inverse = true;
        }

        $row1 = $row1 % $this->rows;
        $row2 = $row2 % $this->rows;
        $col1 = $col1 % $this->cols;
        $col2 = $col2 % $this->cols;
        if ($row1 < $row2) {
            $d = $inverse ? 'n' : 's';
        }elseif ($row1 > $row2) {
            $d = $inverse ? 's' : 'n';
        }elseif ($col1 < $col2) {
            $d = $inverse ? 'w' : 'e';
        }elseif ($col1 > $col2) {
            $d = $inverse ? 'e' : 'w';
        }
        $this->debug("Direction($d): $row1, $col1 | $row2, $col2\n");
        return $d;

    }

    public function debug($output){
        if($this->debugFlag){
            file_put_contents($_SERVER['PWD']."/debug_ants.log", $output, LOCK_EX|FILE_APPEND);
        }
    }

    public static function run($bot)
    {
        global $argv,$argc;
        $ants = new Ants();
        $map_data = array();
	$round = 0;
        if(in_array("--debug", $argv)){
            $ants->debugFlag = true;
        }
        if(in_array("--visTool", $argv)){
            $ants->visTool = true;
        }
        if($ants->debugFlag){
            unlink($_SERVER['PWD']."/debug_ants.log");
        }
        while(true) {
            $current_line = fgets(STDIN,1024);
            $current_line = trim($current_line);
            if ($current_line === 'ready') {
                $ants->setup($map_data);
                $ants->finishTurn();
                $map_data = array();
            } elseif ($current_line === 'go') {
                $round++;
                $ants->currentTurn = $round;
                $ants->roundStart = microtime(true);
                if($round == 1){
                        $bot->doSetup($ants);
                }
                $ants->update($map_data);
                $bot->doTurn($ants);
                $ants->finishTurn();
                $map_data = array();
            } else {
                $map_data []= $current_line;
            }
        }

    }
}
