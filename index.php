<html>

<head>
	<style>
		.container {
			position: relative;
		}

		#text {
			position: absolute;
			left: 500px;
			top: 200px;
			z-index: 5;
		}

		#score {
			position: absolute;
			left: 450px;
			top: 10px;
			z-index: 5;
		}
	</style>


	<script type="text/javascript" src="glMatrix-0.9.5.min.js"></script>
	<script type="text/javascript" src="Mat3Translate.js"></script>
	<script type="text/javascript" src="webgl-utils.js"></script>

	<!--Shaders-->
	<script id="shader-vs" type="x-shader/x-vertex">
			uniform vec2 uResolution;
			
			attribute vec2 aVertexPosition;
			attribute vec4 aVertexColor;

			uniform mat4 uMVMatrix;

			varying vec4 vColor;

			void main(void) {
				// Convert the rectangle from pixels to 0.0 to 1.0
				vec2 zeroToOne = aVertexPosition / uResolution;
				// Convert from 0->1 to 0->2
				vec2 zeroToTwo = zeroToOne * 2.0;
				// Convert from 0->2 to -1->+1 (clipspace)
				vec2 clipSpace = zeroToTwo - 1.0;
				gl_Position = vec4(clipSpace * vec2(1, -1), 0.0, 1.0);
				gl_Position = uMVMatrix * vec4(clipSpace * vec2(1, -1), 0.0, 1.0);
				vColor = aVertexColor;
			}
		</script>

	<script id="shader-fs" type="x-shader/x-fragment">
			precision mediump float;

			varying vec4 vColor;

			void main(void) {
				gl_FragColor = vColor;
			}
		</script>
	<!--/Shaders-->

	<script type="text/javascript">
		function Player(locStart, color) {
			this.locStart = Object.create(locStart);
			this.location = Object.create(locStart);
			this.width = 20;
			this.height = 200;

			this.velocity = 6.0;

			this.mvMatrix = mat4.create();
			mat4.identity(this.mvMatrix);
			mat4.translate(this.mvMatrix, normalToClip(Object.create(locStart)));

			this.vertices = [
				-10.0, 100.0,
				10.0, 100.0,
				-10.0, -100.0,
				10.0, -100.0
			];
			this.color = color;
			initBuffers(this);
		}

		function Ball(canvas) {
			locStart = [(canvas.width / 2) - 10, (canvas.height / 2) - 10, 0.0];
			color = [0.0, 0.0, 1.0, 1.0];
			this.location = Object.create(locStart);
			this.width = 20;
			this.height = 20;

			let side = [5, -5]

			this.velocity = [side[Math.floor((Math.random() * 2) + 1) - 1], side[Math.floor((Math.random() * 2) + 1) - 1], 0.0];

			this.mvMatrix = mat4.create();
			mat4.identity(this.mvMatrix);
			mat4.translate(this.mvMatrix, normalToClip(Object.create(locStart)));

			this.vertices = [
				-10.0, 10.0,
				10.0, 10.0,
				-10.0, -10.0,
				10.0, -10.0
			];
			this.color = color;
			initBuffers(this);
		}

		var debug = 0;

		var gl;
		var shaderProgram;
		var resolution;
		var mvMatrix;
		var drawingMVMatrices = [];
		var drawingVertexBuffers = [];
		var drawingColorBuffers = [];
		var currentlyPressedKeys = {};

		var player1;
		var player2;
		var ball;
		var scorePlayer1 = 0;
		var scorePlayer2 = 0;

		function initGL(canvas) {
			try {
				gl = canvas.getContext("webgl") || canvas.getContext("experimental-webgl");
				gl.viewportWidth = canvas.width;
				gl.viewportHeight = canvas.height;
				resolution = [canvas.width, canvas.height, 1.0];
			} catch (e) {
			}
			if (!gl) {
				alert("Could not initialize WebGL, sorry :-(");
			}
		}

		function getShader(gl, id) {
			var shaderScript = document.getElementById(id);
			if (!shaderScript) {
				return null;
			}

			var str = "";
			var k = shaderScript.firstChild;
			while (k) {
				if (k.nodeType == 3) {
					str += k.textContent;
				}
				k = k.nextSibling;
			}

			var shader;
			if (shaderScript.type == "x-shader/x-fragment") {
				shader = gl.createShader(gl.FRAGMENT_SHADER);
			} else if (shaderScript.type == "x-shader/x-vertex") {
				shader = gl.createShader(gl.VERTEX_SHADER);
			} else {
				return null;
			}

			gl.shaderSource(shader, str);
			gl.compileShader(shader);

			if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
				alert(gl.getShaderInfoLog(shader));
				return null;
			}
			return shader;
		}

		function initShaders() {
			var fragmentShader = getShader(gl, "shader-fs");
			var vertexShader = getShader(gl, "shader-vs");

			shaderProgram = gl.createProgram();
			gl.attachShader(shaderProgram, vertexShader);
			gl.attachShader(shaderProgram, fragmentShader);
			gl.linkProgram(shaderProgram);

			if (!gl.getProgramParameter(shaderProgram, gl.LINK_STATUS)) {
				alert("Could not initialize shaders");
			}

			gl.useProgram(shaderProgram);
			shaderProgram.resolutionUniform = gl.getUniformLocation(shaderProgram, "uResolution")	// Resolution

			shaderProgram.vertexPositionAttribute = gl.getAttribLocation(shaderProgram, "aVertexPosition");
			gl.enableVertexAttribArray(shaderProgram.vertexPositionAttribute);

			shaderProgram.vertexColorAttribute = gl.getAttribLocation(shaderProgram, "aVertexColor");
			gl.enableVertexAttribArray(shaderProgram.vertexColorAttribute);

			shaderProgram.mvMatrixUniform = gl.getUniformLocation(shaderProgram, "uMVMatrix");
		}

		function setMatrixUniforms() {
			gl.uniform2f(shaderProgram.resolutionUniform, canvas.width, canvas.height);	// Resolution
			gl.uniformMatrix4fv(shaderProgram.mvMatrixUniform, false, mvMatrix);
		}

		function initObjects(canvas) {
			player1 = new Player([15.0, 100.0, 0.0], [1.0, 0.0, 0.0, 1.0]);
			player2 = new Player([canvas.width - 15.0, 100.0, 0.0], [0.0, 1.0, 0.0, 1.0]);
			ball = new Ball(canvas);
		}

		function initBuffers(object) {
			object.vertexBuffer = gl.createBuffer();
			gl.bindBuffer(gl.ARRAY_BUFFER, object.vertexBuffer);
			gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(object.vertices), gl.STATIC_DRAW);
			object.vertexBuffer.itemSize = 2;
			object.vertexBuffer.numItems = 4;

			object.colors = [];
			for (var i = 0; i < 4; i++) {
				object.colors = object.colors.concat(object.color);
			}
			object.colorBuffer = gl.createBuffer();
			gl.bindBuffer(gl.ARRAY_BUFFER, object.colorBuffer);
			gl.bufferData(gl.ARRAY_BUFFER, new Float32Array(object.colors), gl.STATIC_DRAW);
			object.colorBuffer.itemSize = 4;
			object.colorBuffer.numItems = 4;

			drawingMVMatrices.push(object.mvMatrix);
			drawingVertexBuffers.push(object.vertexBuffer);
			drawingColorBuffers.push(object.colorBuffer);
		}

		function refillBuffers(object) {
			drawingMVMatrices.push(object.mvMatrix);
			drawingVertexBuffers.push(object.vertexBuffer);
			drawingColorBuffers.push(object.colorBuffer);
		}

		function handleKeyDown(event) {
			currentlyPressedKeys[event.keyCode] = true;
		}

		function handleKeyUp(event) {
			currentlyPressedKeys[event.keyCode] = false;
		}

		function handleKeys() {
			if (currentlyPressedKeys[87]) {
				// W key
				mat4.translate(player1.mvMatrix, normalToClip([0.0, -player1.velocity, 0.0]));
				player1.location = vec3.add(player1.location, [0.0, -player1.velocity, 0.0]);
			}
			if (currentlyPressedKeys[83]) {
				// S key
				mat4.translate(player1.mvMatrix, normalToClip([0.0, player1.velocity, 0.0]));
				player1.location = vec3.add(player1.location, [0.0, player1.velocity, 0.0]);
			}
			if (currentlyPressedKeys[38]) {
				// Up cursor key
				mat4.translate(player2.mvMatrix, normalToClip([0.0, -player2.velocity, 0.0]));
				player2.location = vec3.add(player2.location, [0.0, -player2.velocity, 0.0]);
			}
			if (currentlyPressedKeys[40]) {
				// Down cursor key
				mat4.translate(player2.mvMatrix, normalToClip([0.0, player2.velocity, 0.0]));
				player2.location = vec3.add(player2.location, [0.0, player2.velocity, 0.0]);
			}
		}

		function checkCollision() {
			if (player1.location[1] - (player1.height / 2.0) <= 0) {
				mat4.translate(player1.mvMatrix, normalToClip([0, (player1.height / 2.0) - player1.location[1], 0]));
				player1.location[1] = (player1.height / 2.0);
			}
			if (player1.location[1] + (player1.height / 2.0) >= canvas.height) {
				mat4.translate(player1.mvMatrix, normalToClip([0, canvas.height - (player1.location[1] + (player1.height / 2.0)), 0]));
				player1.location[1] = canvas.height - (player1.height / 2.0);
			}
			if (player2.location[1] - (player2.height / 2.0) <= 0) {
				mat4.translate(player2.mvMatrix, normalToClip([0, (player2.height / 2.0) - player2.location[1], 0]));
				player2.location[1] = (player2.height / 2.0);
			}
			if (player2.location[1] + (player2.height / 2.0) >= canvas.height) {
				mat4.translate(player2.mvMatrix, normalToClip([0, canvas.height - (player2.location[1] + (player2.height / 2.0)), 0]));
				player2.location[1] = canvas.height - (player2.height / 2.0);
			}
			if (ball.location[0] < 0) {
				ball = new Ball(canvas);
				scorePlayer2++;
				if (!isMenu)
					playGol();
			}
			if (ball.location[0] > canvas.width - 16) {
				ball = new Ball(canvas);
				scorePlayer1++;
				if (!isMenu)
					playGol();
			}
			// console.log(ball.location);
			if (Math.abs(ball.location[0] - player1.location[0]) <= (ball.width / 2.0) + (player1.width / 2.0) &&
				ball.location[0] - player1.location[0] >= 0 &&
				Math.abs(ball.location[1] - player1.location[1]) <= (ball.height / 2.0) + (player1.height / 2.0)) {
				vec3.multiply(ball.velocity, [-1.0, 1.0, 1.0]);
				vec3.multiply(ball.velocity, [1.1, 1.1, 1.0]);
				mat4.translate(ball.mvMatrix, normalToClip([(((ball.width / 2.0) + (player1.width / 2.0)) - Math.abs(ball.location[0] - player1.location[0])), 0, 0]));
				ball.location[0] = player1.location[0] + (ball.width / 2.0) + (player1.width / 2.0);
			}
			if (Math.abs(ball.location[0] - player2.location[0]) <= (ball.width / 2.0) + (player2.width / 2.0) &&
				ball.location[0] - player2.location[0] <= 0 &&
				Math.abs(ball.location[1] - player2.location[1]) <= (ball.height / 2.0) + (player2.height / 2.0)) {
				vec3.multiply(ball.velocity, [-1.0, 1.0, 1.0]);
				vec3.multiply(ball.velocity, [1.1, 1.1, 1.0]);
				mat4.translate(ball.mvMatrix, normalToClip([-(((ball.width / 2.0) + (player2.width / 2.0)) - Math.abs(ball.location[0] - player2.location[0])), 0, 0]));
				ball.location[0] = player2.location[0] - (ball.width / 2.0) - (player2.width / 2.0);
			}
			if ((ball.location[1] - (ball.height / 2.0)) < 0.0 || (ball.location[1] + (ball.height / 2.0)) >= canvas.height) {
				vec3.multiply(ball.velocity, [1.0, -1.0, 1.0]);
			}
		}
		var isMenu = true;
		function drawScene() {
			gl.viewport(0, 0, gl.viewportWidth, gl.viewportHeight);
			gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);
			ctxScore.clearRect(0, 0, ctxScore.canvas.width, ctxScore.canvas.height);
			ctxText.clearRect(0, 0, ctxText.canvas.width, ctxText.canvas.height);
			if (scorePlayer1 > 4 || scorePlayer2 > 4) {
				isMenu = true;
				scorePlayer1 = 0;
				scorePlayer2 = 0;
			}

			if (isMenu) {
				ctxText.fillStyle = "#ffffff";
				ctxText.font = "20px Verdana";
				// ctxScore.fillText('escreva o placar aqui', window.innerWidth/2, window.innerHeight/2);
				ctxText.fillText(' Bong!! \n Play', 10, 20);
				return;
			}



			var currentMVMatrix;
			var vertexBuffer;
			var colorBuffer;
			for (var i = drawingVertexBuffers.length; i > 0; i--) {
				mvMatrix = drawingMVMatrices.pop();
				vertexBuffer = drawingVertexBuffers.pop();
				colorBuffer = drawingColorBuffers.pop();
				gl.bindBuffer(gl.ARRAY_BUFFER, vertexBuffer);
				gl.vertexAttribPointer(shaderProgram.vertexPositionAttribute, vertexBuffer.itemSize, gl.FLOAT, false, 0, 0);
				gl.bindBuffer(gl.ARRAY_BUFFER, colorBuffer);
				gl.vertexAttribPointer(shaderProgram.vertexColorAttribute, colorBuffer.itemSize, gl.FLOAT, false, 0, 0);
				setMatrixUniforms();
				gl.drawArrays(gl.TRIANGLE_STRIP, 0, vertexBuffer.numItems);
			}

			ctxScore.fillStyle = "#ffffff";
			ctxScore.font = "20px Verdana";
			let a = document.getElementById('player2').value;
			let b = document.getElementById('player1').value;

			ctxScore.fillText(b + ' ' + scorePlayer1 + ' X ' + a + ' ' + scorePlayer2 + '  | ' + printTime / 100 + 's', 30, 40);
		}



		function normalToClip(src) {
			var zeroToOne = vec3.divide(src, resolution);
			var zeroToTwo = vec3.multiply(zeroToOne, [2.0, 2.0, 2.0]);
			var dest = vec3.multiply(zeroToTwo, [1.0, -1.0, 0.0]);
			return dest;
		}

		function tick() {
			requestAnimFrame(tick);
			handleKeys();
			checkCollision();
			drawScene();
			animate();
		}

		var lastTime = 0;
		var printTime;
		function animate() {
			var timeNow = new Date().getTime();
			if (lastTime != 0) {
				var elapsed = timeNow - lastTime;

				mat4.translate(ball.mvMatrix, normalToClip(Object.create(ball.velocity)));
				ball.location = vec3.add(ball.location, ball.velocity);
				printTime++;
			}
			lastTime = timeNow;

			refillBuffers(player1);
			refillBuffers(player2);
			refillBuffers(ball);
		}
		var ctxScore;
		var ctxText;
		var canvas;

		function playMusic() {
			var audio = new Audio('audio.mp3');
			audio.volume = 0.5;
			console.log(audio.volume);
			audio.play();
		}

		function playGol() {
			var audiogol = new Audio('gol.mp3');
			audiogol.play();
		}

		function webGLStart() {
			playMusic();
			// look up the text canvas.
			ctxScore = document.getElementById("score").getContext("2d");
			ctxText = document.getElementById("text").getContext("2d");

			canvas = document.getElementById("canvas");
			canvas.width = window.innerWidth - 16;
			canvas.height = window.innerHeight - 16;
			initGL(canvas);
			initShaders();
			initObjects(canvas);

			gl.clearColor(0.0, 0.0, 0.0, 1.0);
			gl.enable(gl.DEPTH_TEST);

			document.onkeydown = handleKeyDown;
			document.onkeyup = handleKeyUp;
			ctxText.canvas.addEventListener('click', function () {
				console.log('click'); isMenu = false;
				printTime = 0;
			}, false);

			tick();


		}

	</script>

</head>

<body onload="webGLStart();">

	<canvas id="canvas" style="border: none;"></canvas>
	<canvas id="text"></canvas>
	<canvas id="score"></canvas>
	<label>Player1:</label>
	<input type="text" id="player1" value="Player1">
	<br>
	<label>Player2:</label>
	<input type="text" id="player2" value="Player2">
</body>

</html>