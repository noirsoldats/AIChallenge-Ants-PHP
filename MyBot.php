<?php

require_once 'Ants.php';

class MyBot
{
    private $directions = array('n','e','s','w');
    private $type_short = array(0 => "A", -1 => "D", -2 => "L", -3 => "F", -4 => "W", -5 => "U");
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
//        file_put_contents($_SERVER['PWD']."/debug.log", $output, LOCK_EX|FILE_APPEND);
    }

    public function outputMap($map){
/*        $display = "\n".str_pad("", 4, " ", STR_PAD_BOTH);
        foreach(range(0, $this->ants->cols-1) AS $c1){
            $display .= "| ".str_pad($c1, 6, " ", STR_PAD_BOTH)."|";
        }
        $display .= "\n";
        foreach($map as $ri => $row){
            $display .= str_pad($ri, 4, " ", STR_PAD_BOTH);
            foreach($row as $ci => $col){
                $display .= "|".$this->type_short[$this->ants->map[$ri][$ci]]."".str_pad(round($col['food'], 0), 6, " ", STR_PAD_BOTH)."|";
//				$display .= "|".$this->type_short[$this->ants->map[$ri][$ci]]."".str_pad($this->explorer_map[$ri][$ci], 6, " ", STR_PAD_BOTH)."|";
            }
            $display .= "\n";
        }
        file_put_contents($_SERVER['PWD']."/map.log", $display, LOCK_EX|FILE_APPEND);*/
    }

    public function doSetup($ants){
		$start = microtime(true);
//        unlink($_SERVER['PWD']."/debug.log");
//        unlink($_SERVER['PWD']."/map.log");
//        $this->debug("Ant Map:\n");
//        $this->debug(var_export($ants->map, true));
        foreach(range(0, $ants->rows-1) as $row){
            foreach(range(0, $ants->cols-1) as $col){
                $value = ($ants->map[$row][$col] == WATER) ? 9999999 : 0;
//				$this->debug("{$ants->map[$row][$col]} | $value\n");
                $this->explorer_map[$row][$col] = $value;
                $this->diffusion_map[$row][$col] = array('food' => 0, 'hill' => 0);
            }
        }
//        $this->debug("Explorer Map:\n");
//        $this->debug(var_export($this->explorer_map, true));
//        $this->debug("Diffusion Map:\n");
//        $this->debug(var_export($this->diffusion_map, true));
		$end = microtime(true);
		$this->debug("Setup Time: ".($end-$start)."\n");
    }

    public function doTurn( $ants ){
		$this->debug("-------TURN {$ants->currentTurn}-------\n");
		$this->debug("Num Ants: ".count($ants->myAnts)."\n");
        $this->ants = $ants;
        $this->orders = array();
        $this->build_diffusion();
        $this->outputMap($this->diffusion_map);
        //$this->debug(var_export($this->diffusion_map, true));
        //$this->debug(var_export($ants, true));
        // Prevent Stepping on own hills
        foreach($ants->myHills as $hill){
            $this->orders[$hill[0]][$hill[1]] = "None";
        }
        // Determine Movements
        $start = microtime(true);
        foreach ( $ants->myAnts as $ant ) {
            list ($aRow, $aCol) = $ant;
//            $this->explorer_map[$aRow][$aCol] += 1;
            $destinations = array();
            $highest = 0;

            if($this->diffusion_map[$aRow][$aCol]['hill'] > 0.01){
                // Determine Highest Scent
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));

                        if(($this->diffusion_map[$dest_row][$dest_col]['hill'] > $highest) && ($this->move_okay($dest_row, $dest_col))){
                            $highest = $this->diffusion_map[$dest_row][$dest_col]['hill'];
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

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));

                        if(($this->diffusion_map[$dest_row][$dest_col]['hill'] >= $highest) && ($this->move_okay($dest_row, $dest_col))){
                            $destinations[] = array($dest_row, $dest_col);
                        }
                    }
                }
            }elseif($this->diffusion_map[$aRow][$aCol]['food'] > 0.01){
                // Determine Highest Scent
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));

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

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));

                        if(($this->diffusion_map[$dest_row][$dest_col]['food'] >= $highest) && ($this->move_okay($dest_row, $dest_col))){
                            $destinations[] = array($dest_row, $dest_col);
                        }
                    }
                }
            }else{
                $least = 9999999;
                // Determine Exploration
                $this->debug("Exploration Chosen!\n");
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));
                        $this->debug("Checking1: $dest_row, $dest_col\n");

                        if(($this->explorer_map[$dest_row][$dest_col] < $least) && ($this->move_okay($dest_row, $dest_col))){
                            $least = $this->explorer_map[$dest_row][$dest_col];
                        }
                    }
                }
                $this->debug("Lowest: $least\n");

                // Build list of possible destinations
                foreach(range(-1, 1) as $row){
                    foreach(range(-1, 1) as $col){
                        if($row == 0 && $col == 0)
                            continue;
                        if($row !=0 && $col != 0)
                            continue;

                        list($dest_row, $dest_col) = $this->map_wrap(($row + $aRow), ($col + $aCol));
    			$this->debug("Checking2: $dest_row, $dest_col\n");

                        if(($this->explorer_map[$dest_row][$dest_col] <= ($least)) && ($this->move_okay($dest_row, $dest_col))){
                            $destinations[] = array($dest_row, $dest_col);
                        }
                    }
                }
            }

            // Choose a random destination from possible destinations
            $this->debug("DestinationsB: \n".var_export($destinations, true)."\n");
            $start_shuffle = microtime(true);
            shuffle($destinations);
            $end_shuffle = microtime(true);
            $this->debug("Shuffle Weight: ".($end_shuffle-$start_shuffle)."\n");
            $this->debug("DestinationsA: \n".var_export($destinations, true)."\n");
            list($dRow, $dCol) = $destinations[0];
            if($this->do_move_location($aRow, $aCol, $dRow, $dCol)){
                $this->debug("Dest: $dRow, $dCol\n");
                $this->explorer_map[$dRow][$dCol] += 1;
            }
            /*foreach ($this->directions as $direction) {
                list($dRow, $dCol) = $ants->destination($aRow, $aCol, $direction);
                if ($ants->passable($dRow, $dCol)) {
                    //$ants->issueOrder($aRow, $aCol, $direction);
                    break;
                }
            }*/
        }
		$end = microtime(true);
		$this->debug("Ant Movement: ".($end-$start)."\n");
//        $this->debug(var_export($this->orders, true));
    }
    
    private function map_wrap($dest_row, $dest_col){
//        $this->debug("Map_Wrap Args: $dest_row, $dest_col\n");
//        $this->debug("Map_Wrap Ants: {$this->ants->rows}, {$this->ants->cols}\n");
        $dest_row = $dest_row % $this->ants->rows;
        $dest_col = $dest_col % $this->ants->cols;
//        $this->debug("Map_Wrap Stp1: $dest_row, $dest_col\n");
        $dest_row = ($dest_row < 0) ? $dest_row + $this->ants->rows : $dest_row;
        $dest_col = ($dest_col < 0) ? $dest_col + $this->ants->cols : $dest_col;
//        $this->debug("Map_Wrap Retr: $dest_row, $dest_col\n");
        return array($dest_row, $dest_col);
    }

    private function do_move_location($cRow, $cCol, $dRow, $dCol){
        $direction = $this->ants->direction($cRow, $cCol, $dRow, $dCol);
//        foreach($directions as $direction){
            if($this->move_okay($dRow, $dCol)){
                $this->ants->issueOrder($cRow, $cCol, $direction);
                $this->debug("Move Issued: $cRow,$cCol - $direction\n");
                $this->orders[$dRow][$dCol] = array($cRow, $cCol);
                return true;
            }
//        }
        return false;
    }

    private function move_okay($dest_row, $dest_col){
        if($this->ants->unoccupied($dest_row, $dest_col) && $this->ants->passable($dest_row, $dest_col) &&  !isset($this->orders[$dest_row][$dest_col])){
            return true;
        }
        return false;
    }
	
	private function getScentableTiles($food_row, $food_col, $radius){
		$scentable = array(array($food_row, $food_col));
		$min_row_div = -$radius;
		$max_row_div = $radius;
		$min_col_div = -$radius;
		$max_col_div = $radius;
		// Let's Try North
		foreach(range($food_row, ($food_row+$min_row_div)) as $check_row){
			list($check_row, $a) = $this->map_wrap($check_row, $food_col);
			if($this->ants->map[$check_row][$food_col] == WATER){
//				$min_row_div = ($check_row + 1) - $food_row;
				break;
			}
			// And West
			foreach(range($food_col, ($food_col + $min_col_div)) as $check_col){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$min_col_div = ($check_col + 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
			// And East
			foreach(range($food_col, ($food_col + $max_col_div)) as $check_col){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$max_col_div = ($check_col - 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
		}
		// Let's Try South
		foreach(range($food_row, ($food_row+$max_row_div)) as $check_row){
			list($check_row, $a) = $this->map_wrap($check_row, $food_col);
			if($this->ants->map[$check_row][$food_col] == WATER){
//				$max_row_div = ($check_row + 1) - $food_row;
				break;
			}
			// And West
			foreach(range($food_col, ($food_col + $min_col_div)) as $check_col){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$min_col_div = ($check_col + 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
			// And East
			foreach(range($food_col, ($food_col + $max_col_div)) as $check_col){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$max_col_div = ($check_col - 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
		}
		// Let's Try West
		foreach(range($food_col, ($food_col+$min_col_div)) as $check_col){
			list($a, $check_col) = $this->map_wrap($food_row, $check_col);
			if($this->ants->map[$food_row][$check_col] == WATER){
//				$max_row_div = ($check_row + 1) - $food_row;
				break;
			}
			// And North
			foreach(range($food_row, ($food_row + $min_row_div)) as $check_row){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$min_col_div = ($check_col + 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
			// And South
			foreach(range($food_row, ($food_row + $max_row_div)) as $check_row){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$max_col_div = ($check_col - 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
		}
		// Let's Try East
		foreach(range($food_col, ($food_col+$max_col_div)) as $check_col){
			list($a, $check_col) = $this->map_wrap($food_row, $check_col);
			if($this->ants->map[$food_row][$check_col] == WATER){
//				$max_row_div = ($check_row + 1) - $food_row;
				break;
			}
			// And North
			foreach(range($food_row, ($food_row + $min_row_div)) as $check_row){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$min_col_div = ($check_col + 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
			// And South
			foreach(range($food_row, ($food_row + $max_row_div)) as $check_row){
				list($check_row, $check_col) = $this->map_wrap($check_row, $check_col);
				if($this->ants->map[$check_row][$check_col] == WATER){
//					$max_col_div = ($check_col - 1) - $food_col;
					break;
				}else{
					$scentable[] = array($check_row, $check_col);
				}
			}
		}
//            $this->debug("Scentable Cnt: ".count($scentable)."\n");
		$scentable = array_unique($scentable, SORT_REGULAR);
//		$this->debug("Scentable: \n".var_export($scentable, true)."\n");
//            $this->debug("Scentable UCnt: ".count($scentable)."\n");
            return $scentable;
	}

    private function build_diffusion(){
		$start = microtime(true);
        $this->foods = $this->ants->food;
		$this->debug("Num Foods: ".count($this->foods)."\n");
        $this->enemy_hills = $this->ants->enemyHills;
        
        // Reset Diffusion Map Food and deprioritize Hills
		$start_reset = microtime(true);
		// @TODO Try Array_Map inside Array_Map to see if faster
        foreach(range(0, $this->ants->rows-1) as $row){
            foreach(range(0, $this->ants->cols-1) as $col){
                $this->diffusion_map[$row][$col]['food'] = 0;
                $this->diffusion_map[$row][$col]['hill'] = $this->diffusion_map[$row][$col]['hill']/4;
            }
        }
		$end_reset = microtime(true);
		$this->debug("Reset Time: ".($end_reset-$start_reset)."\n");

        foreach($this->foods as $food){
            $food_boost = 1000 + rand(0, 250);
            list($food_row, $food_col) = $food;
            // Diffuse Food Scent in a radius of 10 units
            $scentable = $this->getScentableTiles($food_row, $food_col, 10);
            foreach($scentable as $scent){
                $row_mod = $scent[0] - $food_row;
                $col_mod = $scent[1] - $food_col;
                $boost = $food_boost / ($this->ants->distance($food_row, $food_col, $scent[0], $scent[1]));

                if($this->diffusion_map[$scent[0]][$scent[1]]['food'] > $boost){
                        continue;
                }

                $this->diffusion_map[$scent[0]][$scent[1]]['food'] = $boost;
            }
        }
        
        foreach($this->enemy_hills as $hill){
            $hill_boost = 10000 + rand(0, 2500);
            list($hill_row, $hill_col) = $hill;
            // Diffuse Food Scent in a radius of 10 units
            $scentable = $this->getScentableTiles($hill_row, $hill_col, 20);
            foreach($scentable as $scent){
                $row_mod = $scent[0] - $hill_row;
                $col_mod = $scent[1] - $hill_col;
                $boost = $food_boost / ($this->ants->distance($hill_row, $hill_col, $scent[0], $scent[1]));

                if($this->diffusion_map[$scent[0]][$scent[1]]['hill'] > $boost){
                        continue;
                }

                $this->diffusion_map[$scent[0]][$scent[1]]['hill'] = $boost;
            }
        }
		$end = microtime(true);
		$this->debug("Diffusion Time: ".($end-$start)."\n");
    }
}

/**
 * Don't run bot when unit-testing
 */
if( !defined('PHPUnit_MAIN_METHOD') ) {
    Ants::run( new MyBot() );
}