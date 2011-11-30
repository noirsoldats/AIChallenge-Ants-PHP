<?php

require_once 'Ants.php';

class MyBot
{
    private $directions = array('n','e','s','w');
    private $unseen = array();
    private $orders = array();
    private $ants;
    private $foods = array();
    private $enemy_hills = array();
    private $killed_hills = array();
    private $old_pos = array();
    private $new_pos = array();
    private $gatherers = array();
    private $hunters = array();
    private $guards = array();
    private $path_cache = array();
    
    private $explorer_map = array();
    private $diffusion_map = array();
    
    public function debug($output){
        file_put_contents("/home/dustin/Projects/git/php_starter_package/debug.log", $output, LOCK_EX);
    }
    
    public function doSetup($ants){
        foreach(range(0, $ants->rows) as $row){
            foreach(range(0, $ants->cols) as $col){
                $value = ($ants->map[$row][$col] == WATER) ? 9999999 : 0;
                $this->explorer_map[$row][$col] = $value;
                $this->diffusion_map[$row][$col] = array('food' => 0, 'hill' => 0);
            }
        }
    }

    public function doTurn( $ants )
    {
        $this->ants = $ants;
        $this->orders = array();
        $this->build_diffusion();
        $this->debug(var_export($this->diffusion_map, true));
        //$this->debug(var_export($ants, true));
        // Prevent Stepping on own hills
        foreach($ants->myHills as $hill){
            $this->orders[$hill[0]][$hill[1]] = "None";
        }
        // Determine Movements
        foreach ( $ants->myAnts as $ant ) {
            list ($aRow, $aCol) = $ant;
            $destinations = array();
            $highest = 0;
            
            if($this->diffusion_map[$aRow][$aCol]['food'] > 0.01){
                // Determine Highest Scent
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;
    
                        $dest_row = ($row + $aRow) % $ants->rows;
                        $dest_col = ($col + $aCol) % $ants->cols;
                        
                        if(($this->diffusion_map[$dest_row][$dest_col]['food'] > $highest) && ($this->move_okay($dest_row, $dest_col))){
                            $highest = $this->diffusion_map[$dest_row][$dest_col]['food'];
                        }
                    }
                }

                // Build list of possible destinations
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;
    
                        $dest_row = ($row + $aRow) % $ants->rows;
                        $dest_col = ($col + $aCol) % $ants->cols;
                        
                        if(($this->diffusion_map[$dest_row][$dest_col]['food'] >= $highest) && ($this->move_okay($dest_row, $dest_col))){
                            $destinations[] = array($dest_row, $dest_col);
                        }
                    }
                }
                
                // Choose a random destination from possible destinations
                $destination_order = array_rand($destinations, count($destinations));
                foreach($destination_order as $dest_idx){
                    list($dRow, $dCol) = $destinations[$dest_idx];
                    if($this->do_move_location($aRow, $aCol, $dRow, $dCol)){
                        break;
                    }
                }
            }else{
                $least = 9999999;
                // Determine Exploration
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;
    
                        $dest_row = ($row + $aRow) % $ants->rows;
                        $dest_col = ($col + $aCol) % $ants->cols;
                        
                        if(($this->explorer_map[$dest_row][$dest_col] < $least) && ($this->move_okay($dest_row, $dest_col))){
                            $least = $this->explorer_map[$dest_row][$dest_col];
                        }
                    }
                }

                // Build list of possible destinations
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;
    
                        $dest_row = ($row + $aRow) % $ants->rows;
                        $dest_col = ($col + $aCol) % $ants->cols;
                        
                        if(($this->explorer_map[$dest_row][$dest_col] <= ($least+1)) && ($this->move_okay($dest_row, $dest_col))){
                            $destinations[] = array($dest_row, $dest_col);
                        }
                    }
                }
                
                // Choose a random destination from possible destinations
                $destination_order = array_rand($destinations, count($destinations));
                foreach($destination_order as $dest_idx){
                    list($dRow, $dCol) = $destinations[$dest_idx];
                    if($this->do_move_location($aRow, $aCol, $dRow, $dCol)){
                        $this->explorer_map[$dest_row][$dest_col] += 1;
                        break;
                    }
                }
            }
            /*foreach ($this->directions as $direction) {
                list($dRow, $dCol) = $ants->destination($aRow, $aCol, $direction);
                if ($ants->passable($dRow, $dCol)) {
                    //$ants->issueOrder($aRow, $aCol, $direction);
                    break;
                }
            }*/
        }
    }
    
    private function do_move_location($cRow, $cCol, $dRow, $dCol){
        $directions = $this->ants->direction($cRow, $cCol, $dRow, $dCol);
        foreach($directions as $direction){
            if($this->move_okay($dRow, $dCol)){
                $this->ants->issueOrder($cRow, $cCol, $direction);
                $this->orders[$dRow][$dCol] = array($cRow, $cCol);
                return true;
            }
        }
        return false;
    }
    
    private function move_okay($dest_row, $dest_col){
        if($this->ants->unoccupied($dest_row, $dest_col) && $this->ants->passable($dest_row, $dest_col) &&  !isset($this->orders[$dest_row][$dest_col])){
            return true;
        }
        return false;
    }
    
    private function build_diffusion(){
        $this->foods = $this->ants->food;
        $this->enemy_hills = $this->ants->enemyHills;
        
        // Reset Diffusion Map Food and deprioritize Hills
        foreach(range(0, $this->ants->rows) as $row){
            foreach(range(0, $this->ants->cols) as $col){
                $this->diffusion_map[$row][$col]['food'] = 0;
                $this->diffusion_map[$row][$col]['hill'] = $this->diffusion_map[$row][$col]['hill']/4;
            }
        }
        
        foreach($this->foods as $food){
            $food_boost = 1000 + rand(0, 250);
            
            // Diffuse Food Scent in a radius of 10 units
            foreach(range(-10, 10) as $row_mod){
                foreach(range(-10, 10) as $col_mod){
                    if((abs($row_mod) + abs($col_mod)) > 10){
                        // Shape Scents in to a DIAMOND!
                        continue;
                    }

                    $water_found = false;
                    $col_step = $col_mod < 0 ? -1 : 1;
                    $row_step = $row_mod < 0 ? -1 : 1;
                    
                    foreach(range(0, ($col_mod+$col_step), $col_step) as $check_c){
                        foreach(range(0, ($row_mod+$row_step), $row_step) as $check_r){
                            $check_row = ($check_r + $food[0]) % $this->ants->rows;
                            $check_col = ($check_c + $food[1]) % $this->ants->cols;
                            if($this->ants->map[$check_row][$check_col] == WATER){
                                $water_found = true;
                                break 2;
                            }
                        }
                    }
                    
                    if($water_found){
                        continue;
                    }
                    
                    $dest_row = ($row_mod + $food[0]) % $this->ants->rows;
                    $dest_col = ($col_mod + $food[1]) % $this->ants->cols;
                    
                    if($this->ants->map[$dest_row][$dest_col] == WATER){
                        continue;
                    }
                    
                    $boost = $food_boost / (abs($row_mod) + abs($col_mod) + 1);
                    
                    if($this->diffusion_map[$dest_row][$dest_col]['food'] > $boost){
                        continue;
                    }
                    
                    $this->diffusion_map[$dest_row][$dest_col]['food'] = $boost;
                }
            }
        }
    }
    
}

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
    Ants::run( new MyBot() );
}