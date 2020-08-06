<?php
//Heatmap type question support code
//Functions for displaying heatmap question type
//Ver 1.0 by Ondrej Zjevik, August 2020

global $allowedmacros,$imasroot;
array_push($allowedmacros,"display","heatmap_grade");
function heatmap_grade($data_str, $stua_str){
	// Parse data
	$data = json_decode($data_str, true);
	$stua = explode(',',$stua_str);
	try {
		// Check the distance between student pt and heatmap pts
		foreach ($data['data'] as $key => $value) {
			$distance = sqrt(($stua[0]-$value['x'])**2+($stua[1]-$value['y'])**2);
			if($distance < $value['radius']){
				return 1;
			}
		}
	} catch (\Throwable $th) {
		throw $th;
	}
	return 0;
}

function display($pic) {
	$return = '<div class="questionContainer"><div class="heatmap" style="width: fit-content;">'.$pic.'</div></div>';
	$return .= "<script>
		$('.heatmap img').css('max-width', '100%');
		$('.heatmap img').attr('src',$('.heatmap img').attr('src'));
		$('.heatmap img').on('load', function() {
			var heatmapInstance = h337.create({
        		container: document.querySelector('.heatmap'),
				blur: 0.07,
				max: 100,
				min: 0,
				maxOpacity: .8,
				gradient: {
					'.0': 'red',
					'.5': 'lightgreen',
					'1': 'green'
				},
			});
			canvasCopy = $('.heatmap-canvas').clone();
			canvasCopy[0].id = 'canvas';
			$('.heatmap-canvas').parent().append(canvasCopy)
			
			// Code for placing marker to the canvas
			var canvas = document.getElementById('canvas');
			var ctx = canvas.getContext('2d');
			var Scanvas = $('#canvas');
			var canvasOffset = Scanvas.offset();
			var offsetX = canvasOffset.left;
			var offsetY = canvasOffset.top;
			var scrollX = Scanvas.scrollLeft();
			var scrollY = Scanvas.scrollTop();
			var cw = canvas.width;
			var ch = canvas.height;
			var isDown = false;
			var lastX;
			var lastY;

			var PI2 = Math.PI * 2;
			// variables relating to existing circles
			var circles = [];
			var stdRadius = 5;
			var stdColor = 'red';
			var draggingCircle = -1;

			// clear the canvas and redraw all existing circles
			function drawAll() {
				ctx.clearRect(0, 0, cw, ch);
				for (var i = 0; i < circles.length; i++) {
					var circle = circles[i];
					drawX(circle.x, circle.y);
					ctx.beginPath();
					ctx.arc(circle.x, circle.y, circle.radius, 0, PI2);
					ctx.closePath();
					ctx.fillStyle = 'red';
					ctx.fill();

					ctx.beginPath();
					ctx.arc(circle.x, circle.y, 1.5, 0, PI2);
					ctx.closePath();
					ctx.fillStyle = 'black';
					ctx.fill();


				}
				// Convert to Premille
				var ansX = circles[0].x/heatmapInstance._renderer._width*1000;
				var ansY = circles[0].y/heatmapInstance._renderer._width*1000;
				$('.question input[type=text]').val(ansX+','+ansY+','+heatmapInstance._renderer._width);
			}
			function drawX(x, y) {
				ctx.beginPath();
				ctx.strokeStyle = 'white';
				ctx.moveTo(x - 10, y - 10);
				ctx.lineTo(x + 10, y + 10);
				ctx.lineWidth = 4;
				ctx.stroke();
			
				ctx.moveTo(x + 10, y - 10);
				ctx.lineTo(x - 10, y + 10);
				ctx.stroke();

				ctx.beginPath();
				ctx.strokeStyle = 'black';
				ctx.moveTo(x - 10, y - 10);
				ctx.lineTo(x + 10, y + 10);
				ctx.lineWidth = 2;
				ctx.stroke();
			
				ctx.moveTo(x + 10, y - 10);
				ctx.lineTo(x - 10, y + 10);
				ctx.stroke();
				ctx.strokeStyle = 'white';
			}

			function handleMouseDown(e) {
				// tell the browser we'll handle this event
				e.preventDefault();
				e.stopPropagation();

				// save the mouse position
				// in case this becomes a drag operation
				offsetX = canvasOffset.left;
				offsetY = canvasOffset.top;

				lastX = parseInt(e.pageX - offsetX);
				lastY = parseInt(e.pageY - offsetY);

				// hit test all existing circles
				var hit = -1;
				for (var i = 0; i < circles.length; i++) {
					var circle = circles[i];
					var dx = lastX - circle.x;
					var dy = lastY - circle.y;
					if (dx * dx + dy * dy < circle.radius * circle.radius) {
						hit = i;
					}
				}

				// if no hits then add a circle
				// if hit then set the isDown flag to start a drag
				if (hit < 0) {
					if (circles.length < 1){
						circles.push({
							x: lastX,
							y: lastY,
							radius: stdRadius,
							color: stdColor
						});
						drawAll();
					} else{
						circles[0].x = lastX;
						circles[0].y = lastY;
						drawAll();
					}
				} else {
					draggingCircle = circles[hit];
					isDown = true;
				}

			}

			function handleMouseUp(e) {
				// tell the browser we'll handle this event
				e.preventDefault();
				e.stopPropagation();

				// stop the drag
				isDown = false;
			}

			function handleMouseMove(e) {

				// if we're not dragging, just exit
				if (!isDown) {
					return;
				}

				// tell the browser we'll handle this event
				e.preventDefault();
				e.stopPropagation();

				// get the current mouse position
				mouseX = parseInt(e.pageX - offsetX);
				mouseY = parseInt(e.pageY - offsetY);

				// calculate how far the mouse has moved
				// since the last mousemove event was processed
				var dx = mouseX - lastX;
				var dy = mouseY - lastY;

				// reset the lastX/Y to the current mouse position
				lastX = mouseX;
				lastY = mouseY;

				// change the target circles position by the 
				// distance the mouse has moved since the last
				// mousemove event
				draggingCircle.x += dx;
				draggingCircle.y += dy;

				// redraw all the circles
				drawAll();
			}

			// listen for mouse events
			$('#canvas').mousedown(function (e) {
				handleMouseDown(e);
			});
			$('#canvas').mousemove(function (e) {
				handleMouseMove(e);
			});
			$('#canvas').mouseup(function (e) {
				handleMouseUp(e);
			});
			$('#canvas').mouseout(function (e) {
				handleMouseUp(e);
			});

			// Add marker for previous answer
			$('.question input[type=text]').hide();
			studansArr = $('.question input[type=text]').val().split(',');
			if(studansArr.length == 3){
				// Convert from Permille
				studansArr[0] = studansArr[0]*studansArr[2]/1000;
				studansArr[1] = studansArr[1]*studansArr[2]/1000;

				circles.push({
					x: parseInt(studansArr[0]),
					y: parseInt(studansArr[1]),
					radius: stdRadius,
					color: stdColor
				});
				drawAll();
			}

			// Show answer code
			$('.sabtn').on('click',function(ev) {
				ev.stopImmediatePropagation();
				$('.question span[id*=ans]').hide();
				data = JSON.parse($('.question span[id*=ans]').text().trim());
				heatmapInstance.setData(data);
				
			});
		});
	  </script>";
	return $return;
}


?>
