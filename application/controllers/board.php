<?php

class Board extends CI_Controller {
     
    function __construct() {
    		// Call the Controller constructor
	    	parent::__construct();
	    	session_start();
    } 
          
    public function _remap($method, $params = array()) {
	    	// enforce access control to protected functions	
    		
    		if (!isset($_SESSION['user']))
   			redirect('account/loginForm', 'refresh'); //Then we redirect to the index page again
 	    	
	    	return call_user_func_array(array($this, $method), $params);
    }
    
    
    function index() {
		$user = $_SESSION['user'];
    		    	
	    	$this->load->model('user_model');
	    	$this->load->model('invite_model');
	    	$this->load->model('match_model');
	    	
	    	$user = $this->user_model->get($user->login);

	    	$invite = $this->invite_model->get($user->invite_id);
	    	
	    	if ($user->user_status_id == User::WAITING) {
	    		$invite = $this->invite_model->get($user->invite_id);
	    		$otherUser = $this->user_model->getFromId($invite->user2_id);
	    	}
	    	else if ($user->user_status_id == User::PLAYING) {
	    		$match = $this->match_model->get($user->match_id);
	    		if ($match->user1_id == $user->id)
	    			$otherUser = $this->user_model->getFromId($match->user2_id);
	    		else
	    			$otherUser = $this->user_model->getFromId($match->user1_id);
	    	}
	    	
	    	$data['user']=$user;
	    	$data['otherUser']=$otherUser;
	    	
	    	switch($user->user_status_id) {
	    		case User::PLAYING:	
	    			$data['status'] = 'playing';
	    			break;
	    		case User::WAITING:
	    			$data['status'] = 'waiting';
	    			break;
	    	}
	    	
		$this->load->view('match/board',$data);
    }

 	function postMsg() {
 		$this->load->library('form_validation');
 		$this->form_validation->set_rules('msg', 'Message', 'required');
 		
 		if ($this->form_validation->run() == TRUE) {
 			$this->load->model('user_model');
 			$this->load->model('match_model');

 			$user = $_SESSION['user'];
 			 
 			$user = $this->user_model->getExclusive($user->login);
 			if ($user->user_status_id != User::PLAYING) {	
				$errormsg="Not in PLAYING state";
 				goto error;
 			}
 			
 			$match = $this->match_model->get($user->match_id);			
 			
 			$msg = $this->input->post('msg');
 			
 			if ($match->user1_id == $user->id)  {
 				$msg = $match->u1_msg == ''? $msg :  $match->u1_msg . "\n" . $msg;
 				$this->match_model->updateMsgU1($match->id, $msg);
 			}
 			else {
 				$msg = $match->u2_msg == ''? $msg :  $match->u2_msg . "\n" . $msg;
 				$this->match_model->updateMsgU2($match->id, $msg);
 			}
 				
 			echo json_encode(array('status'=>'success'));
 			 
 			return;
 		}
		
 		$errormsg="Missing argument";
 		
		error:
			echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}
 
	function getMsg() {
 		$this->load->model('user_model');
 		$this->load->model('match_model');
 			
 		$user = $_SESSION['user'];
 		 
 		$user = $this->user_model->get($user->login);
 		if ($user->user_status_id != User::PLAYING) {	
 			$errormsg="Not in PLAYING state";
 			goto error;
 		}
 		// start transactional mode  
 		$this->db->trans_begin();
 			
 		$match = $this->match_model->getExclusive($user->match_id);			
 			
 		if ($match->user1_id == $user->id) {
			$msg = $match->u2_msg;
 			$this->match_model->updateMsgU2($match->id,"");
 		}
 		else {
 			$msg = $match->u1_msg;
 			$this->match_model->updateMsgU1($match->id,"");
 		}

 		if ($this->db->trans_status() === FALSE) {
 			$errormsg = "Transaction error";
 			goto transactionerror;
 		}
 		
 		// if all went well commit changes
 		$this->db->trans_commit();
 		
 		echo json_encode(array('status'=>'success','message'=>$msg));
		return;
		
		transactionerror:
		$this->db->trans_rollback();
		
		error:
		echo json_encode(array('status'=>'failure','message'=>$errormsg));
 	}

 	// performs a move onto the board.
 	function performMove() {
		$this->load->model('user_model');
 		$this->load->model('match_model');
 		
 		// get current user
		$user = $_SESSION['user'];
 		$user = $this->user_model->get($user->login);
 		// get current match and determine current player number
		$match = $this->match_model->get($user->match_id);
		$board_state = json_decode($match->board_state);


		if (!$match || $match->match_status_id != Match::ACTIVE) {

			if ($match->match_status_id == Match::U1WIN) {
				$winner = 1;
			}
			else if ($match->match_status_id == Match::U2WIN) {
				$winner = 2;
			}
			else {// tie
				$winner = 0;
			}
			goto done;

		}

		if ($board_state == null) {
			$board_state = array(
				// each of these arrays is a column, where index 0 = bottom
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				array(0, 0, 0, 0, 0, 0),
				);
		} 

		// get input column number
 		$column = $this->input->post('column');
 		if ($column < 0 ) { // indicator that request only wants board state)
			$winner = -1;
			goto done;
		}


		// validate it is your turn.
		$player_num = $match->user1_id == $user->id? 1 : 2;
		if (!$this->yourTurn($board_state, $player_num))
		{
			$errormsg="Not your Turn";
			goto error;
		}

		
 		$moved = false;
 		for ($i = 0; $i < 6; $i++) {
 			// if spot is empty, put player's piece there
 			if ($board_state[$column][$i] == 0) {
 				$board_state[$column][$i] = $player_num;
 				$row = $i;
 				$moved = true;
 				break;
 			}
 		}

 		// if player did not move, return msg saying its invalid
 		if (!$moved) {
 			$errormsg="Not a valid move";
			goto error;
 		}

		// start transactional mode  
		$this->db->trans_begin();

 		// check if most recent move was a winning move
 		if ($this->isWinner($board_state, $player_num, $row, $column)) {
 			$winner = $player_num;
 			if($player_num == 1) {
 				$this->match_model->updateStatus($match->id, Match::U1WIN);
 			} else { // player_num == 2
 				$this->match_model->updateStatus($match->id, Match::U2WIN);
 			}
 		} 
 		// check if tie
 		else if ($this->tie($board_state)) {
 			$this->match_model->updateStatus($match->id, Match::TIE);
 			$winner = 0;
 		}
 		// game has not yet ended
 		else {
 			$winner = -1;
 		}


 		// save the board
 		$encoded_board_state = json_encode($board_state);
 		$this->match_model->updateBoard($match->id, $encoded_board_state);

 		if ($this->db->trans_status() === FALSE) {
 			$errormsg = "Transaction error";
 			goto transactionerror;
 		}

 		$this->db->trans_commit();

 		done: 
 		// pass board to the view
		echo json_encode(array('status'=>'success','board'=>$board_state, 'winner'=>$winner));
 			 
 		return;

		transactionerror:
			$this->db->trans_rollback();

 		error:
			echo json_encode(array('status'=>'failure','message'=>$errormsg));
			return;
 	}

 	// counts number of 1's and 2's and returns true if its your turn next
 	function yourTurn($board_state, $player_num) {
 		// count number of pieces each player has
 		$u1_pieces_count = 0;
 		$u2_pieces_count = 0;
 		foreach($board_state as $col) {
 			foreach($col as $val) {
 				if ($val == 1) {
 					$u1_pieces_count++;
 				}
 				else if ($val == 2) {
 					$u2_pieces_count++;
 				}
 			}
 		}

 		// figure out who's turn it is
 		if ($u1_pieces_count >= $u2_pieces_count) {
 			$player_turn = 2;
 		} else {
 			$player_turn = 1;
 		}
 		return ($player_turn == $player_num);
 	}

 	// checks if there are any empty spots left on the board.
 	// To check for tie, must check if player has won first
 	function tie($board_state) {
 		foreach($board_state as $col) {
 			foreach($col as $val) {
 				if ($val == 0) {
 					return false;
 				}
 			}
 		}
 		return true;
 	}

 	// checks if player has won the game - r,c is location of
 	// most recent move
 	function isWinner($board_state, $player, $r, $c) {
 					// check for win in all 4 possible directions
		if ( $this->winInDir($board_state, $player, $r, $c, 1, 0) ||
			 $this->winInDir($board_state, $player, $r, $c, 0, 1) ||
			 $this->winInDir($board_state, $player, $r, $c, 1, 1) ||
			 $this->winInDir($board_state, $player, $r, $c, -1, 1) ) {
				return true;
 		}

 		return false;
 	}

 	// checks for a win along the dirR, dirC vector
 	// should be called where board_state[c][r] = player
 	function winInDir($board_state, $player, $r, $c, $dirR, $dirC) {
 		// count of number of pieces in positive/negative directions along dirR, dirC
 		$countPos = 0;
 		$countNeg = 0;

 		// current position being examined
 		$curPos_r = $r + $dirR;
 		$curPos_c = $c + $dirC;

 		while (	// indexes examined are still on the board
 				$curPos_r < 6 && $curPos_r >=0 
 				&& $curPos_c < 7 && $curPos_c >=0
 				// and pieces in direction are of the player
 				&& $board_state[$curPos_c][$curPos_r] == $player ) {

			$countPos++;
			// increment currentPos
			$curPos_r += $dirR;
 			$curPos_c += $dirC;

 		}

 		// check negative direction
 		$curPos_r = $r - $dirR;
 		$curPos_c = $c - $dirC;
		while (	// indexes examined are still on the board
 				$curPos_r < 6 && $curPos_r >=0 
 				&& $curPos_c < 7 && $curPos_c >=0
 				// and pieces in direction are of the player
 				&& $board_state[$curPos_c][$curPos_r] == $player ) {

			$countNeg++;
			// decrement currentPos
			$curPos_r -= $dirR;
 			$curPos_c -= $dirC;

 		}

 		// return true if there is at least 3 pieces (not including board_state[c][r]
 		// that belong to the player
 		return (($countPos + $countNeg) >= 3);

 	}
}


