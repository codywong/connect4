
<!DOCTYPE html>

<html>
	<head>
	<script src="http://code.jquery.com/jquery-latest.js"></script>
	<script src="<?= base_url() ?>/js/jquery.timers.js"></script>
	<script>

		var otherUser = "<?= $otherUser->login ?>";
		var user = "<?= $user->login ?>";
		var status = "<?= $status ?>";
		
		$(function(){
			// status of game
			// 0 = tie
			// 1 = u1win
			// 2 = u2win
			var winner = -1;

			$('body').everyTime(1000,function(){
					if (status == 'waiting') {
						$.getJSON('<?= base_url() ?>arcade/checkInvitation',function (data, text, jqZHR){
								if (data && data.status=='rejected') {
									alert("Sorry, your invitation to play was declined!");
									window.location.href = '<?= base_url() ?>arcade/index';
								}
								if (data && data.status=='accepted') {
									status = 'playing';
									$('#status').html('Playing ' + otherUser);
								}
								
						});
					}
					var url = "<?= base_url() ?>board/getMsg";
					$.getJSON(url, function (data,text,jqXHR){
						if (data && data.status=='success') {
							var conversation = $('[name=conversation]').val();
							var msg = data.message;
							if (msg.length > 0)
								$('[name=conversation]').val(conversation + "\n" + otherUser + ": " + msg);
						}
					});

					if (winner < 0) {
						var arguments = { column: -1 }
						var url = "<?= base_url() ?>board/performMove";
		    			$.post(url, arguments, function (ret) {
		    				var data = JSON.parse(ret);
		    				console.log(ret);
		    				winner = data.winner;
		    				drawBoard(data.board, winner);
		    			});
		    		}
			});

			$('form').submit(function(){
				var arguments = $(this).serialize();
				var url = "<?= base_url() ?>board/postMsg";
				$.post(url,arguments, function (data,textStatus,jqXHR){
						var conversation = $('[name=conversation]').val();
						var msg = $('[name=msg]').val();
						$('[name=conversation]').val(conversation + "\n" + user + ": " + msg);
						});
				return false;
				});	

			
			// Read input click, and attempt to apply the move at column selected
			$('#myCanvas').click(function(e){
				if (winner < 0) {
					var x = Math.floor(e.pageX - $("#myCanvas").offset().left);
	    			var col = Math.floor(x/100); // convert x to a column number (0-6)

					var arguments = { column: col }
					var url = "<?= base_url() ?>board/performMove";
	    			
	    			$.post(url, arguments, function (ret){
	    				var data = JSON.parse(ret);
	    				if (data && data.status=="success") {
							winner = data.winner;
							// if move succeeded, re-draw board (instead of waiting up to 2000ms)
							drawBoard(data.board, winner);
						} 
						else {
							console.log("Could not move! " + data.message);
						}
					});	
					console.log( "X is :  " + x + "   col is: " + col);
				}
			});


			function drawBoard(board_state, winner) {
			// Do something.
				console.log(board_state);
				for(i=0;i<7;i++)
				{
					for(j=0;j<6;j++)
					{
						ctx.drawImage(image,(board_state[i][j])*100,0,100,100,i*100, (5-j)*100,100,100);
					}
				}
				if (winner == 0) {
					ctx.font="100px Georgia";
					ctx.fillStyle="black";
					ctx.fillText("Tie!",10,200);
				} else if (winner > 0) {
					ctx.font="100px Georgia";
					ctx.fillStyle="black";
					ctx.fillText("Player " + winner + " wins!",10,200);
				}
			}
		});

	</script>
	</head> 
<body>  
	<h1>Game Area</h1>

	<div>
	Hello <?= $user->fullName() ?>  <?= anchor('account/logout','(Logout)') ?>  
	</div>
	
	<div id='status'> 
	<?php 
		if ($status == "playing")
			echo "Playing " . $otherUser->login;
		else
			echo "Wating on " . $otherUser->login;
	?>
	</div>
	
<canvas id="myCanvas" width="700" height="600" style="border:1px solid #000000;left: 100px">
	   	 Your browser does not support the HTML5 canvas tag.
</canvas>
<br>
<script>
	canvas = document.getElementById('myCanvas');	
	ctx = canvas.getContext && canvas.getContext('2d');
	image = new Image();
	image.src = "<?php echo base_url('images/pieces.png'); ?>"
</script>

<?php 
	
	echo form_textarea('conversation');
	
	echo form_open();
	echo form_input('msg');
	echo form_submit('Send','Send');
	echo form_close();
	
?>

</body>

</html>

