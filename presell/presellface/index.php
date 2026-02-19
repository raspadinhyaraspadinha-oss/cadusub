<?php
include "../../conectarbanco.php";

$conn = new mysqli(
    "localhost",
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("Conexão falhou: " . $conn->connect_error);
}

$sql = "SELECT nome_unico, nome_um, nome_dois FROM app";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $nomeUnico = $row["nome_unico"];
    $nomeUm = $row["nome_um"];
    $nomeDois = $row["nome_dois"];
} else {
    $nomeUnico = "SubwayPay";
    $nomeUm = "";
    $nomeDois = "";
}

$conn->close();

// Set indicator (saldo demo) to 30 in localStorage via JS
$saldoDemo = 30;
?>
<html lang="pt-br" class="w-mod-js w-mod-ix wf-spacemono-n7-active wf-spacemono-n4-active wf-active"><head><script>
	const indicator = <?= $saldoDemo ?>

	if (indicator) {
		localStorage.setItem('indicator', indicator)
	}
</script>
<style>
	#wins {
		display: block;
		width: 240px;
		min-height: 50px;
		font-size: 12px;
		line-height: 2;
		padding: 5px 0;
		text-align: center;
		background-color: #FFC107;
		border: 3px solid #1f2024;
		border-radius: 0 0 20px 20px;
		box-shadow: -3px 3px 0 0px #1f2024;
		z-index: 10;
	}

	.balance {
		width: max-content;
		background: #FFC107;
		box-shadow: -3px 3px 0 0px #1f2024;
		border: 3px solid #1f2024;
		border-radius: 20px 20px 0 0;
		padding: 1rem 2rem;
		font-family: right grotesk, sans-serif;
	}

	.minting-container.w-container.max-width-700px {
		max-width: 700px
	}

	.minting-container.w-container .properties {
		width: 100%
	}
</style>
<script>
	const nomesJogadores = [
		'João', 'Maria', 'Pedro', 'Ana', 'Carlos',
		'Lúcia', 'Ricardo', 'Fernanda', 'Rodrigo',
		'Isabela',
		'Paulo', 'Cristina', 'Luiz', 'Mariana', 'Vitor',
		'Aline', 'Gustavo', 'Tatiana', 'Bruno', 'Laura'
	];

	function gerarValorAleatorio() {
		return (Math.random() * (25 - 10) + 10).toFixed(2);
	}

	function atualizarValoresAleatorios() {
		const randomPlayer = Math.floor(Math.random() *
			nomesJogadores.length);
		const nomeAleatorio = nomesJogadores[randomPlayer];
		const valorAleatorio = gerarValorAleatorio();

		document.getElementById('wins').innerHTML = `
            ${nomeAleatorio}
            <br>
            Ganhou: R$ ${valorAleatorio}
        `

	}

	setInterval(atualizarValoresAleatorios, 3000);

	document.addEventListener('DOMContentLoaded', function() {
		const winners = document.getElementById("wins")
		if (winners) {
			winners.innerHTML =
				"Carregando Ganhadores..."
		}
	})
</script>

	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<style>
		.wf-force-outline-none[tabindex="-1"]:focus {
			outline: none;
		}
	</style>
	<meta charset="pt-br">

	<title><?= htmlspecialchars($nomeUnico) ?> - Rodada grátis</title>

	<meta name="description" content="A Aventura Urbana Espera por Você! - Deslize pelas trilhas da cidade, desviando de obstáculos e coletando tesouros. Quão longe você consegue correr? Entre e descubra!">

	<meta content="width=device-width, initial-scale=1" name="viewport">

	<link href="css/page.css" rel="stylesheet" type="text/css">
	<link rel="stylesheet" href="css/fonts.css" media="all">

	<script type="text/javascript">
		! function(o, c) {
			var n = c.documentElement,
				t = " w-mod-";
			n.className += t + "js", ("ontouchstart" in o || o
				.DocumentTouch && c instanceof DocumentTouch
			) && (n
				.className += t + "touch")
		}(window, document);
	</script>

	<meta property="og:image" content="images/banner.png">

	<link rel="apple-touch-icon" sizes="180x180" href="images/icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="images/icon.png">
	<link rel="icon" type="image/png" sizes="16x16" href="images/icon.png">

	<link href="css/toastify.min.css" rel="stylesheet">
	<script src="js/toastify-js.js"></script>

	<?php include "../../pixels.php"; ?>

	<!-- UTMify Pixel -->
	<script>
	  window.pixelId = "698535fcd111bd400675768d";
	  var a = document.createElement("script");
	  a.setAttribute("async", "");
	  a.setAttribute("defer", "");
	  a.setAttribute("src", "https://cdn.utmify.com.br/scripts/pixel/pixel.js");
	  document.head.appendChild(a);
	</script>

	<!-- Capture UTMs from URL and persist to localStorage -->
	<script>
	(function() {
		var params = new URLSearchParams(window.location.search);
		var keys = ['utm_source', 'utm_campaign', 'utm_medium', 'utm_content', 'utm_term', 'fbclid'];
		var utms = {};
		var hasUtm = false;
		keys.forEach(function(k) {
			var v = params.get(k);
			if (v) { utms[k] = v; hasUtm = true; }
		});
		if (hasUtm) {
			localStorage.setItem('utms', JSON.stringify(utms));
		}
	})();
	</script>

</head>

<body>

<style>
	.button.main-nav-button {
		background: #5aff8e;
		color: #1f2024;
	}

	.nav-bar {
		display: block;
		background-color: #333;
		padding: 20px;
		width: 90%;
		position: fixed;
		top: 5em;
		left: 0;
		z-index: 1000;
		transform: translateY(-100%) scale(0);
		opacity: 0;
		transition: all .3s ease-in-out;
	}

	.nav-bar.active {
		opacity: 1;
		transform: translateY(1em) scale(1);
	}

	.nav-bar a {
		color: white;
		text-decoration: none;
		padding: 10px;
		display: block;
		margin-bottom: 10px;
	}

	.nav-link {
		transition: all .2s ease-in-out;
	}

	.nav-link:hover {
		color: #ddd !important;
	}

	.w-nav-overlay {
		background: #0005;
		display: block;
		position: fixed;
		top: 0;
		left: 0;
		width: 100vw;
		height: 100vh;
		z-index: 999;
		pointer-events: none;
		opacity: 0;
		transition: all .3s ease-in-out;
	}

	.w-nav-overlay.active {
		pointer-events: all;
		opacity: 1;
	}

	.off-black {
		background: #1f2024;
	}

	@media (min-width: 768px) {
		.nav-bar {
			display: none;
		}

		.w-nav-overlay {
			display: none;
		}
	}
</style>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const menuButton = document.querySelector('#menu-button');
		const navBar = document.querySelector('#nav-bar');
		const navOverlay = document.querySelector('.w-nav-overlay');

		if (menuButton) {
			menuButton.addEventListener('click', function() {
				navBar.classList.toggle('active');
				navOverlay.classList.toggle('active');
			});
		}

		if (navOverlay) {
			navOverlay.addEventListener('click', function() {
				navBar.classList.toggle('active');
				navOverlay.classList.toggle('active');
			});
		}
	});
</script>

<div data-collapse="small" data-animation="default" data-duration="400" role="banner" class="navbar w-nav">
	<div class="w-container container">
		<a href="/cadastrar/" aria-current="page" class="brand w-nav-brand" aria-label="home">
			<img src="images/logo_full-removebg-preview.png" loading="lazy" height="28" alt="" class="image-6">
			<div class="nav-link logo"><?= htmlspecialchars($nomeUnico) ?></div>
		</a>
		<nav role="navigation" class="nav-menu w-nav-menu">
			<a href="/login/" class="nav-link w-nav-link">Login</a>
			<a href="/cadastrar/" class="nav-link w-nav-link">Cadastrar</a>
			<a href="/cadastrar/" class="button nav w-button">Jogar</a>
		</nav>
		<div class="">
			<div class="">
				<a href="/cadastrar/" class="menu-button w-nav-dep nav w-button off-black main-nav-button">CADASTRAR</a>
			</div>
		</div>
		<div class="menu-button w-nav-button off-black" aria-label="menu" role="button" tabindex="0" id="menu-button" aria-controls="w-nav-overlay-0" aria-haspopup="menu" aria-expanded="false">
			<div class="icon w-icon-nav-menu"></div>
		</div>
	</div>
	<div class="w-nav-overlay" data-wf-ignore="" id="w-nav-overlay-0"></div>
	<div class="nav-bar off-black" id="nav-bar">
		<a href="/login/" class="nav-link w-nav-link">Login</a>
		<a href="/cadastrar/" class="nav-link w-nav-link">Cadastrar</a>
		<a href="/cadastrar/" class="button nav w-button">Jogar</a>
	</div>
</div>
	<div>
	<section id="hero" class="hero-section wf-section dark" style="background: url(images/banner.png); background-size: cover; background-position: center">

		<div class="balance">
			SALDO: <b>R$
			<?= number_format($saldoDemo, 2, ',', '.') ?>
			</b>
		</div>
		<div class="minting-container w-container max-width-700px">
			<h2>JOGUE AGORA!</h2>
			<p>
				Corra com seu surfista pelas ruas movimentadas e colete
				moedas! Evite os obstáculos no caminho.
			</p>
			<div id="mensagemContainer"></div>

			<a type="submit" href="/presell/jogoteste/" class="primary-button w-button">
				Iniciar Rodada!
			</a>
		</div>

		<div id="wins">
			Carregando
			<br>
			Ganhos...
		</div>

	</section>

	<div id="about" class="comic-book white wf-section">
		<div class="minting-container w-container">
			<img src="images/sprite_character_king.webp" loading="lazy" width="240" class="mint-card-image">
			<div>
				<h2>Como Jogar?</h2>
				<h3>Guia Passo a Passo</h3>
				<p>Prepare-se para correr pelas ruas movimentadas de
					Subway Surfers e coletar moedas valiosas. Siga nosso
					guia passo a passo e torne-se um mestre na arte de
					desviar dos obstáculos e deslizar pelos trilhos!</p>

				<h3>1. Objetivo do Jogo</h3>
				<p>Seu objetivo principal é correr o máximo que puder
					pelas ruas movimentadas, coletando moedas e evitando
					obstáculos. Desafie-se a alcançar a maior pontuação
					possível enquanto percorre o metrô!</p>

				<h3>2. Deslize para Esquerda ou Direita</h3>
				<p>Para mover seu surfista para a esquerda ou direita,
					deslize o dedo na direção desejada na tela. Evite
					colidir com obstáculos e trilhos de trem enquanto
					coleta moedas.</p>

				<h3>3. Pule e Role</h3>
				<p>Para pular sobre obstáculos, toque na tela com um
					toque rápido. Você também pode rolar sob obstáculos
					deslizando o dedo para baixo. Use essas habilidades
					para evitar ser pego pelo guarda de segurança e seus
					cães.</p>

				<h3>4. Colete Moedas</h3>
				<p>Ao longo do percurso, você encontrará muitas moedas.
					Certifique-se de coletá-las, pois são essenciais para
					comprar power-ups e desbloquear personagens adicionais.
				</p>

				<h3>5. Power-Ups</h3>
				<p>Às vezes, você encontrará caixas de power-up ao longo
					do caminho. Pegue essas caixas para obter vantagens
					temporárias, como ímãs de moedas, hoverboards e muito
					mais.</p>

				<h3>6. Desafios Diários e Missões</h3>
				<p>Complete os desafios diários e missões para ganhar
					recompensas adicionais e aumentar sua pontuação.</p>

				<h3>7. Competição Global</h3>
				<p>Desafie jogadores de todo o mundo para ver quem pode correr
					mais longe e alcançar a maior pontuação.</p>

				<h3>8. Desbloqueie Novos Personagens</h3>
				<p>Use suas moedas para desbloquear novos personagens,
					cada um com habilidades especiais.</p>

				<h3>9. Divirta-se!</h3>
				<p>Aproveite a emoção de correr pelas ruas, coletar moedas e superar desafios. Quanto mais
					você joga, melhor você fica!</p>

			</div>
		</div>
	</div>
</div>

<script>
var toast = {
	error: function(message) {
		Toastify({
			text: message,
			duration: 3000,
			close: true,
			gravity: "bottom",
			position: "center",
			stopOnFocus: true,
			style: {
				background: "linear-gradient(to right, #ff7b72, #c32a22)",
			},
			onClick: function() {}
		}).showToast();
	},
	info: function(message) {
		Toastify({
			text: message,
			duration: 3000,
			close: true,
			gravity: "bottom",
			position: "center",
			stopOnFocus: true,
			style: {
				background: "linear-gradient(to right, #00b09b, #96c93d)",
			},
			onClick: function() {}
		}).showToast();
	},
	success: function(message) {
		Toastify({
			text: message,
			duration: 3000,
			close: true,
			gravity: "bottom",
			position: "center",
			stopOnFocus: true,
			style: {
				background: "linear-gradient(to right, #00b09b, #96c93d)",
			},
			onClick: function() {}
		}).showToast();
	}
}
</script>


<div class="footer-section wf-section">
	<div class="domo-text" style="font-size: 10vw;">
		<?= htmlspecialchars($nomeUm) ?> <br></div>
	<div class="domo-text purple" style="font-size: 10vw;">
		<?= htmlspecialchars($nomeDois) ?> <br></div>
	<div class="follow-test">
		© Copyright <?= htmlspecialchars($nomeUnico) ?>,
		Todos os direitos reservados.
	</div>
	<div class="follow-test">
		<center>
			<a href="/legal">
				<strong class="bold-white-link">Termos de uso</strong>
			</a> | <a href="/legal">
				<strong class="bold-white-link">Política de Privacidade</strong>
			</a>
		</center>
	</div>
	<div class="follow-test">contato@<?php echo strtolower(str_replace(" ", "", $nomeUnico)); ?>.com</div>
</div>

</body></html>
