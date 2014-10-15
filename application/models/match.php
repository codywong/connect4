<?php
class Match  {
	const ACTIVE = 1;
	const U1WIN = 2;
	const U2WIN = 3;
	const TIE = 4;
	
	public $id;
	
	public $user1_id;  
	public $user2_id;

	public $board_state;
	
	public $match_status_id = self::ACTIVE;
		
}