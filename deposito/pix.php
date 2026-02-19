<?php
include "../conectarbanco.php";

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
?>

<?php
session_start();
if (!isset($_SESSION["email"])) {
    header("Location: /login");
    die();
}
$email = $_SESSION["email"];
?>

<!DOCTYPE html>
<html lang="pt-br" class="w-mod-js wf-spacemono-n4-active wf-spacemono-n7-active wf-active w-mod-ix">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        .wf-force-outline-none[tabindex="-1"]:focus {
            outline: none;
        }
    </style>
    <meta charset="pt-br">
    <title><?= $nomeUnico ?></title>

    <meta name="twitter:image" content="../img/logo.png">
    <meta content="summary_large_image" name="twitter:card">

    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>

    <meta content="width=device-width, initial-scale=1" name="viewport">
    <link href="./arquivos/page.css" rel="stylesheet" type="text/css">
    <link href="./arquivos/alert.css" rel="stylesheet" type="text/css">

    <script type="text/javascript">
        WebFont.load({
            google: {
                families: ["Space Mono:regular,700"]
            }
        });
    </script>
    <script type="text/javascript">
        !function (o, c) {
            var n = c.documentElement,
                t = " w-mod-";
            n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n
                .className += t + "touch")
        }(window, document);
    </script>
    <link rel="apple-touch-icon" sizes="180x180" href="../img/logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./img/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./img/logo.png">

    <link rel="stylesheet" href="./arquivos/css" media="all">

    <!-- UTMify Pixel -->
    <script>
      window.pixelId = "698535fcd111bd400675768d";
      var a = document.createElement("script");
      a.setAttribute("async", "");
      a.setAttribute("defer", "");
      a.setAttribute("src", "https://cdn.utmify.com.br/scripts/pixel/pixel.js");
      document.head.appendChild(a);
    </script>

    <style>
        h1 {
            color: #333;
        }

        label {
            display: block;
            margin-bottom: 5px;
        }

        input[type="text"] {
            margin-top: 10px;
            border-radius: 6px;
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
        }

        .divqr {
            align-items: center;
            padding: 20px;
            background-color: #ffffff;
        }

        #qrcode {
            padding: 10px;
            border: 5px solid #1fbffe;
            border-radius: 10px;
            display: inline-block;
        }

        #qr-code-text {
            margin-top: 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            background-color: #e4e2e2;
            border: 2px solid #1fbffe;
            padding: 10px;
            word-break: break-all;
            font-size: 12px;
            max-height: 80px;
            overflow-y: auto;
        }

        #copy-button {
            background-color: #1fbffe;
            border-radius: 6px;
            color: #fff;
            padding: 10px 80px;
            border: none;
            cursor: pointer;
            margin-top: 10px;
            animation: pulse 2s infinite;
            margin: 0 auto;
        }

        .redirectButton {
            background-color: #5a9759;
            border-radius: 6px;
            color: #fff;
            padding: 10px 120px;
            border: none;
            cursor: pointer;
            margin-top: 15px;
        }

        #payment-status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
        }

        .status-checking {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>

<body class="no-touch">
    <div>
        <div data-collapse="small" data-animation="default" data-duration="400" role="banner" class="navbar w-nav">
            <div class="container w-container">
                <a href="/painel" aria-current="page" class="brand w-nav-brand" aria-label="home">
                    <img src="../img/logo.png" loading="lazy" height="28" alt="" class="image-6">
                    <div class="nav-link logo"><?= $nomeUnico ?></div>
                </a>
                <nav role="navigation" class="nav-menu w-nav-menu">
                    <a href="../painel" class="nav-link w-nav-link" style="max-width: 940px;">Jogar</a>
                    <a href="../saque" class="nav-link w-nav-link" style="max-width: 940px;">Saque</a>
                    <a href="../afiliate" class="nav-link w-nav-link" style="max-width: 940px;">Indique e Ganhe</a>
                    <a href="../logout.php" class="nav-link w-nav-link" style="max-width: 940px;">Sair</a>
                    <a href="../deposito" class="button nav w-button w--current">Depositar</a>
                </nav>
                <div class="w-nav-button" style="-webkit-user-select: text;" aria-label="menu" role="button"
                    tabindex="0" aria-controls="w-nav-overlay-0" aria-haspopup="menu" aria-expanded="false">
                    <div class="" style="-webkit-user-select: text;">
                        <a href="../deposito" class="menu-button w-nav-dep nav w-button w--current">DEPOSITAR</a>
                    </div>
                </div>
                <div class="menu-button w-nav-button" style="-webkit-user-select: text;" aria-label="menu" role="button"
                    tabindex="0" aria-controls="w-nav-overlay-0" aria-haspopup="menu" aria-expanded="false">
                    <div class="icon w-icon-nav-menu"></div>
                </div>
            </div>
            <div class="w-nav-overlay" data-wf-ignore="" id="w-nav-overlay-0"></div>
        </div>
        <div class="nav-bar">
            <a href="../painel" class="link-block rarity w-inline-block">
                <div>Jogar</div>
            </a>
            <a href="../saque" class="link-block last w-inline-block">
                <div class="text-block-8">Saque</div>
            </a>
            <a href="../logout.php" class="link-block last w-inline-block">
                <div class="text-block-8">Sair</div>
            </a>
            <a href="../deposito" class="button w-button w--current">Depositar</a>
        </div>

        <div id="deposito">
            <section id="hero" class="hero-section dark wf-section"
                style="background-image: url('/af835635b84ba0916d7c0ddd4e0bd25b.jpg') !important; background-attachment: fixed !important; background-position: center; background-size: cover;">
                <div class="minting-container w-container">
                    <img src="../img/ok.webp" loading="lazy" width="240" alt="Roboto #6340" class="mint-card-image">
                    <h2>ESCANEIE O QR CODE
                        <br>OU COPIE O CÓDIGO PIX
                    </h2>
                    <p>PIX: depósitos instantâneos com uma pitada de diversão e muita praticidade.<br></p>

                    <script>
                        const now = new Date().getTime();
                        const targetTime = now + 10 * 60 * 1000;

                        const countdown = document.getElementById('countdown');
                        const x = setInterval(function () {
                            const currentTime = new Date().getTime();
                            const distance = targetTime - currentTime;

                            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                            if (countdown) {
                                countdown.innerHTML = minutes + ":" + (seconds < 10 ? "0" : "") + seconds + " ";
                            }

                            if (distance < 0) {
                                clearInterval(x);
                                if (countdown) {
                                    countdown.innerHTML = "EXPIRADO";
                                    countdown.style.color = 'red';
                                }
                            }
                        }, 1000);
                    </script>

                    <div class="conteiner">
                        <div id="qrcode"></div>
                    </div>

                    <div class="divqr">
                        <div id="qr-code-text"></div>
                        <button id="copy-button">Copiar Código Pix</button>
                        <br>
                        <div id="payment-status" class="status-checking">Aguardando pagamento...</div>
                        <br>
                        <button class="redirectButton" id="redirectButton">Já paguei - Verificar</button>
                    </div>

                    <script>
                        document.getElementById('redirectButton').addEventListener('click', function () {
                            checkPaymentNow();
                        });
                    </script>

                    <script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>

                    <script>
                        // Get params from URL
                        const urlParams = new URLSearchParams(window.location.search);
                        const pixKey = urlParams.get('pix_key');
                        const token = urlParams.get('token');

                        // Generate QR code from pix_qrcode_text
                        if (pixKey) {
                            var qrcode = new QRCode(document.getElementById("qrcode"), {
                                text: pixKey,
                                width: 256,
                                height: 256,
                                colorDark: "#000000",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.M
                            });

                            // Show the PIX copia-e-cola text
                            document.getElementById('qr-code-text').innerText = pixKey;

                            // Copy button
                            document.getElementById("copy-button").addEventListener("click", function () {
                                if (navigator.clipboard) {
                                    navigator.clipboard.writeText(pixKey).then(function() {
                                        alert("Código PIX copiado!");
                                    });
                                } else {
                                    var textArea = document.createElement("textarea");
                                    textArea.value = pixKey;
                                    document.body.appendChild(textArea);
                                    textArea.select();
                                    document.execCommand("copy");
                                    document.body.removeChild(textArea);
                                    alert("Código PIX copiado!");
                                }
                            });
                        } else {
                            document.getElementById('qr-code-text').innerText = 'Código PIX não encontrado.';
                        }

                        // Polling for payment status every 5 seconds
                        async function checkPaymentNow() {
                            if (!token) return;
                            const url = '../deposito/consultarpagamento.php?token=' + encodeURIComponent(token);
                            try {
                                const resp = await fetch(url);
                                const data = await resp.json();
                                if (data.status === 'PAID_OUT') {
                                    document.getElementById('payment-status').className = 'status-paid';
                                    document.getElementById('payment-status').innerText = 'Pagamento confirmado! Redirecionando...';
                                    setTimeout(function() {
                                        window.location.href = '../obrigado/';
                                    }, 1500);
                                    return true;
                                }
                            } catch (e) {
                                console.log('Erro ao verificar pagamento:', e);
                            }
                            return false;
                        }

                        async function pollPayment() {
                            const maxTime = 10 * 60 * 1000; // 10 minutes
                            const startTime = Date.now();
                            const interval = 5000; // 5 seconds

                            while (Date.now() - startTime < maxTime) {
                                const paid = await checkPaymentNow();
                                if (paid) return;
                                await new Promise(resolve => setTimeout(resolve, interval));
                            }

                            document.getElementById('payment-status').innerText = 'Tempo expirado. Recarregue a página ou gere um novo PIX.';
                        }

                        setTimeout(pollPayment, 2000);
                    </script>

                </div>
            </section>
        </div>
        <div class="intermission wf-section"></div>
        <div id="about" class="comic-book white wf-section">
            <div class="minting-container left w-container">
                <div class="w-layout-grid grid-2">
                    <img src="arquivos/money.png" loading="lazy" width="240" alt="Roboto #6340"
                        class="mint-card-image v2">
                    <div>
                        <h2>Indique um amigo e ganhe R$ no PIX</h2>
                        <h3>Como funciona?</h3>
                        <p>Convide seus amigos que ainda não estão na plataforma. Você receberá R$ por cada amigo que
                            se inscrever e fizer um depósito.</p>
                        <h3>Como recebo o dinheiro?</h3>
                        <p>O saldo é adicionado diretamente ao seu saldo no painel, com o qual você pode sacar via PIX.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-section wf-section">
            <div class="domo-text"><?= $nomeUm ?> <br></div>
            <div class="domo-text purple"><?= $nomeDois ?> <br></div>
            <div class="follow-test">© Copyright xlk Limited, with registered offices at Dr. M.L. King Boulevard 117,
                accredited by license GLH-16289876512. </div>
            <div class="follow-test">
                <a href="/legal"><strong class="bold-white-link">Termos de uso</strong></a>
            </div>
            <div class="follow-test">contato@<?php $nomeUnico = strtolower(str_replace(' ', '', $nomeUnico)); echo $nomeUnico; ?>.com</div>
        </div>
    </div>

</body>
</html>
