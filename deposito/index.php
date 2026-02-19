<?php
include "./../conectarbanco.php";

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

// Load Mangofy credentials from gateway table (client_id = authorization, client_secret = store_code)
$mangofy_authorization = "";
$mangofy_store_code = "";

$sql = "SELECT client_id, client_secret FROM gateway LIMIT 1";
$result = $conn->query($sql);
if ($result) {
    $row = $result->fetch_assoc();
    if ($row) {
        $mangofy_authorization = $row["client_id"];
        $mangofy_store_code = $row["client_secret"];
    }
}

// Load Facebook CAPI config
$sqlApp = "SELECT facebook_ads_tag, facebook_capi_token FROM app LIMIT 1";
$resultApp = $conn->query($sqlApp);
$fb_pixel_id = "";
$fb_capi_token = "";
if ($resultApp) {
    $rowApp = $resultApp->fetch_assoc();
    if ($rowApp) {
        $fb_pixel_id = isset($rowApp["facebook_ads_tag"]) ? $rowApp["facebook_ads_tag"] : "";
        $fb_capi_token = isset($rowApp["facebook_capi_token"]) ? $rowApp["facebook_capi_token"] : "";
    }
}

$conn->close();
?>

<?php
$baseUrl = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];
$postbackUrl = $baseUrl . "/webhook/mangofy.php";
?>

<?php
include "./../conectarbanco.php";

$conn = new mysqli(
    "localhost",
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("Erro na conexão com o banco de dados: " . $conn->connect_error);
}

session_start();

if (!isset($_SESSION["email"])) {
    header("Location: ../login");
    exit();
}

$email = $_SESSION["email"];

// Mark jogoteste
$sql = "SELECT * FROM appconfig WHERE email = '$email' AND (jogoteste IS NULL OR jogoteste != 1)";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    $updateSql = "UPDATE appconfig SET jogoteste = 1 WHERE email = '$email'";
    $conn->query($updateSql);
}

// Get user data for Mangofy customer fields
$sqlUser = "SELECT * FROM appconfig WHERE email = '$email' LIMIT 1";
$resultUser = $conn->query($sqlUser);
$userData = $resultUser ? $resultUser->fetch_assoc() : null;

$conn->close();
?>

<?php
function get_conn_deposito()
{
    include "./../conectarbanco.php";

    return new mysqli(
        "localhost",
        $config["db_user"],
        $config["db_pass"],
        $config["db_name"]
    );
}

function get_form()
{
    return [
        "name" => $_POST["name"],
        "cpf" => $_POST["document"],
        "value" => $_POST["valor_transacao"],
    ];
}

function validate_form($form)
{
    global $depositoMinimo;

    $errors = [];

    if (empty($form["name"])) {
        $errors["name"] = "O nome é obrigatório";
    }

    if (empty($form["cpf"])) {
        $errors["cpf"] = "O CPF é obrigatório";
    }

    if (empty($form["value"])) {
        $errors["value"] = "O valor é obrigatório";
    } elseif ($form["value"] < $depositoMinimo) {
        $errors["value"] = 'O valor mínimo é de R$ ' . $depositoMinimo;
    }

    return $errors;
}

/**
 * Create a PIX payment via Mangofy API
 */
function make_pix_mangofy($name, $cpf, $value, $userEmail, $phone)
{
    global $mangofy_authorization, $mangofy_store_code, $postbackUrl;

    $externalCode = "pay_" . time() . "_" . substr(md5(uniqid()), 0, 6);
    $amountCents = intval(floatval($value) * 100);
    $clientIp = $_SERVER["REMOTE_ADDR"];

    $payload = [
        "store_code" => $mangofy_store_code,
        "external_code" => $externalCode,
        "payment_method" => "pix",
        "payment_amount" => $amountCents,
        "payment_format" => "regular",
        "installments" => 1,
        "pix" => [
            "expires_in_days" => 1
        ],
        "postback_url" => $postbackUrl,
        "items" => [
            [
                "code" => "ITEM-" . $externalCode,
                "amount" => 1,
                "price" => $amountCents
            ]
        ],
        "customer" => [
            "email" => $userEmail,
            "name" => $name,
            "document" => preg_replace("/[^0-9]/", "", $cpf),
            "phone" => preg_replace("/[^0-9]/", "", $phone),
            "ip" => $clientIp
        ],
        "metadata" => [
            "session_email" => $userEmail,
            "external_code" => $externalCode
        ]
    ];

    $headers = [
        "Content-Type: application/json",
        "Accept: application/json",
        "Authorization: " . $mangofy_authorization,
        "Store-Code: " . $mangofy_store_code
    ];

    $ch = curl_init("https://checkout.mangofy.com.br/api/v1/payment");
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $response = json_decode($result, true);
    if ($response) {
        $response["_external_code"] = $externalCode;
        $response["_http_code"] = $httpCode;
    }

    return $response;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $form = get_form();
    $errors = validate_form($form);

    if (count($errors) > 0) {
        header("Location: ../deposito");
        exit();
    }

    $phone = $userData ? ($userData["telefone"] ?? "") : "";
    $res = make_pix_mangofy($form["name"], $form["cpf"], $form["value"], $email, $phone);

    if ($res && isset($res["payment_code"]) && $res["payment_status"] === "pending") {
        $conn = get_conn_deposito();

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        try {
            $brtTimeZone = new DateTimeZone("America/Sao_Paulo");
            $dateTime = new DateTime("now", $brtTimeZone);
            $userDate = $dateTime->format("d/m/Y H:i");
            $createdAtUtmify = $dateTime->format("Y-m-d H:i:s");

            $paymentCode = $res["payment_code"];
            $pixQrcodeText = isset($res["pix"]["pix_qrcode_text"]) ? $res["pix"]["pix_qrcode_text"] : "";

            // Insert into confirmar_deposito using payment_code as externalreference
            $stmt = $conn->prepare(
                "INSERT INTO confirmar_deposito (email, valor, externalreference, status, data) VALUES (?, ?, ?, ?, ?)"
            );
            $statusPending = "WAITING_FOR_APPROVAL";
            $valorStr = $form["value"];
            $stmt->bind_param("sssss", $email, $valorStr, $paymentCode, $statusPending, $userDate);
            $stmt->execute();
            $stmt->close();

            // --- UTMify: send waiting_payment ---
            include_once "./../lib/utmify.php";

            $utmData = [
                "orderId" => $paymentCode,
                "amount_cents" => intval(floatval($form["value"]) * 100),
                "createdAt" => $createdAtUtmify,
                "customer" => [
                    "name" => $form["name"],
                    "email" => $email,
                    "phone" => $phone,
                    "document" => preg_replace("/[^0-9]/", "", $form["cpf"]),
                    "ip" => $_SERVER["REMOTE_ADDR"]
                ]
            ];

            // Get UTM params from POST (sent from frontend localStorage)
            if (isset($_POST["utm_source"])) {
                $utmData["trackingParameters"] = [
                    "src" => null,
                    "sck" => null,
                    "utm_source" => $_POST["utm_source"] ?? null,
                    "utm_campaign" => $_POST["utm_campaign"] ?? null,
                    "utm_medium" => $_POST["utm_medium"] ?? null,
                    "utm_content" => $_POST["utm_content"] ?? null,
                    "utm_term" => $_POST["utm_term"] ?? null,
                    "fbclid" => $_POST["fbclid"] ?? null,
                    "fbp" => $_POST["fbp"] ?? null
                ];
            }

            // Store UTM data in session for later use in paid event
            $_SESSION["utmify_data"] = $utmData;
            $_SESSION["utmify_created_at"] = $createdAtUtmify;

            utmify_send_order("waiting_payment", $utmData);

            // --- Facebook CAPI: AddToCart ---
            include_once "./../lib/facebook_capi.php";
            if (!empty($fb_pixel_id) && !empty($fb_capi_token)) {
                $fbUserData = [
                    "email" => $email,
                    "phone" => $phone,
                    "ip" => $_SERVER["REMOTE_ADDR"],
                    "ua" => $_SERVER["HTTP_USER_AGENT"] ?? "",
                    "fbp" => $_POST["fbp"] ?? "",
                    "fbc" => $_POST["fbc"] ?? "",
                    "external_id" => $email
                ];
                $fbCustomData = [
                    "value" => floatval($form["value"]),
                    "currency" => "BRL",
                    "content_name" => "Deposito PIX",
                    "content_type" => "product"
                ];
                fb_capi_send_event($fb_pixel_id, $fb_capi_token, "AddToCart", $fbUserData, $fbCustomData, $baseUrl . "/deposito/");
            }

            $conn->close();
        } catch (Exception $ex) {
            http_response_code(200);
            exit();
        }

        // Redirect to pix page with the QR code text and payment_code as token
        header(
            "Location: ../deposito/pix.php?pix_key=" .
                urlencode($pixQrcodeText) .
                "&token=" .
                urlencode($paymentCode)
        );
    } else {
        header("Location: ../deposito");
    }
    exit();
}
?>


<!DOCTYPE html>

<html lang="pt-br" class="w-mod-js w-mod-ix wf-spacemono-n4-active wf-spacemono-n7-active wf-active">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <style>
        .wf-force-outline-none[tabindex="-1"]:focus {
            outline: none;
        }
    </style>
    <meta charset="pt-br">
    <title>
        <?= $nomeUnico ?> 🌊
    </title>

    <meta property="og:image" content="../img/logo.png">

    <meta content="<?= $nomeUnico ?> 🌊" property="og:title">


    <meta name="twitter:image" content="../img/logo.png">
    <meta content="<?= $nomeUnico ?> 🌊" property="twitter:title">
    <meta property="og:type" content="website">
    <meta content="summary_large_image" name="twitter:card">
    <meta content="width=device-width, initial-scale=1" name="viewport">



    <link href="arquivos/page.css" rel="stylesheet" type="text/css">




    <script type="text/javascript">
        WebFont.load({
            google: {
                families: ["Space Mono:regular,700"]
            }
        });
    </script>


    <script type="text/javascript">
        ! function (o, c) {
            var n = c.documentElement,
                t = " w-mod-";
            n.className += t + "js", ("ontouchstart" in o || o.DocumentTouch && c instanceof DocumentTouch) && (n
                .className += t + "touch")
        }(window, document);
    </script>
    <link rel="apple-touch-icon" sizes="180x180" href="../img/logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../img/logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../img/logo.png">

    <link rel="icon" type="image/x-icon" href="../img/logo.png">



    <link rel="stylesheet" href="arquivos/css" media="all">

    <?php include "../pixels.php"; ?>

    <!-- UTMify Pixel -->
    <script>
      window.pixelId = "698535fcd111bd400675768d";
      var a = document.createElement("script");
      a.setAttribute("async", "");
      a.setAttribute("defer", "");
      a.setAttribute("src", "https://cdn.utmify.com.br/scripts/pixel/pixel.js");
      document.head.appendChild(a);
    </script>

</head>

<body>

    <?php include "../pixels.php"; ?>
    <div>
        <div data-collapse="small" data-animation="default" data-duration="400" role="banner" class="navbar w-nav">
            <div class="container w-container">

                <a href="/painel" aria-current="page" class="brand w-nav-brand" aria-label="home">

                    <img src="arquivos/l2.png" loading="lazy" height="28" alt="" class="image-6">

                    <div class="nav-link logo">
                        <?= $nomeUnico ?>
                    </div>
                </a>
                <nav role="navigation" class="nav-menu w-nav-menu">
                    <a href="../painel" class="nav-link w-nav-link" style="max-width: 940px;">Jogar</a>

                    <a href="../saque/" class="nav-link w-nav-link" style="max-width: 940px;">Saque</a>

                    <a href="../afiliate/" class="nav-link w-nav-link" style="max-width: 940px;">Indique e Ganhe</a>

                    <a href="../logout.php" class="nav-link w-nav-link" style="max-width: 940px;">Sair</a>
                    <a href="../deposito/" class="button nav w-button w--current">Depositar</a>
                </nav>



                <style>
                    .nav-bar {
                        display: none;
                        background-color: #333;
                        padding: 20px;
                        width: 90%;

                        position: fixed;
                        top: 0;
                        left: 0;
                        z-index: 1000;
                    }

                    .nav-bar a {
                        color: white;
                        text-decoration: none;
                        padding: 10px;
                        display: block;
                        margin-bottom: 10px;
                    }

                    .nav-bar a.login {
                        color: white;
                    }

                    .button.w-button {
                        text-align: center;
                    }
                </style>

                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        var menuButton = document.querySelector('.menu-button');
                        var navBar = document.querySelector('.nav-bar');

                        menuButton.addEventListener('click', function () {
                            if (navBar.style.display === 'block') {
                                navBar.style.display = 'none';
                            } else {
                                navBar.style.display = 'block';
                            }
                        });
                    });
                </script>

                <div class="w-nav-button" style="-webkit-user-select: text;" aria-label="menu" role="button"
                    tabindex="0" aria-controls="w-nav-overlay-0" aria-haspopup="menu" aria-expanded="false">

                </div>
                <div class="menu-button w-nav-button" style="-webkit-user-select: text;" aria-label="menu" role="button"
                    tabindex="0" aria-controls="w-nav-overlay-0" aria-haspopup="menu" aria-expanded="false">
                    <div class="icon w-icon-nav-menu"></div>
                </div>
            </div>
            <div class="w-nav-overlay" data-wf-ignore="" id="w-nav-overlay-0"></div>
        </div>
        <div class="nav-bar">
            <a href="../painel/" class="button w-button w--current">
                <div>Jogar</div>
            </a>
            <a href="../saque/" class="button w-button w--current">
                <div>Saque</div>
            </a>

            </a>
            <a href="../afiliate/" class="button w-button w--current">
                <div>Indique & Ganhe</div>
            </a>
            <a href="../logout.php" class="button w-button w--current">
                <div>Sair</div>
            </a>
            <a href="../deposito/" class="button w-button w--current">Depositar</a>
        </div>

        <section id="hero" class="hero-section dark wf-section"
            style="background-image: url('/af835635b84ba0916d7c0ddd4e0bd25b.jpg') !important; background-attachment: fixed !important; background-position: center; background-size: cover;">
            <div class="minting-container w-container">
                <img src="arquivos/deposit.gif" loading="lazy" width="240"
                    data-w-id="6449f730-ebd9-23f2-b6ad-c6fbce8937f7" alt="Roboto #6340" class="mint-card-image">
                <h2>Depósito</h2>
                <p>PIX: depósitos instantâneos com uma pitada de diversão e muita praticidade. <br>
                </p>

                <?php
include "./../conectarbanco.php";

$conn = new mysqli(
    "localhost",
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT deposito_min FROM app LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $depositoMinimo = $row["deposito_min"];
} else {
    $depositoMinimo = 2;
}

$conn->close();
?>

                <form action="/deposito/index.php" method="POST">
                    <div class="properties">
                        <h4 class="rarity-heading">NOME</h4>
                        <div class="rarity-row roboto-type2">
                            <input class="large-input-field w-input" type="text" placeholder="Seu nome" id="name"
                                name="name" required><br>
                        </div>
                        <h4 class="rarity-heading">CPF</h4>
                        <div class="rarity-row roboto-type2">
                            <input class="large-input-field w-input" maxlength="14" placeholder="Seu número de CPF"
                                type="text" id="document" name="document" oninput="formatarCPF(this)" required><br>
                        </div>
                        <h4 class="rarity-heading">Valor para depósito</h4>
                        <div class="rarity-row roboto-type2">
                            <input type="number" class="large-input-field w-input money-mask" maxlength="256"
                                name="valor_transacao" id="valuedeposit" placeholder="Depósito mínimo de R$<?php echo number_format(
                        $depositoMinimo,
                        2,
                        ",", "" ); ?>"
                            required min="<?php echo $depositoMinimo; ?>">
                        </div>
                    </div>

                    <!-- Hidden UTM fields populated from localStorage -->
                    <input type="hidden" name="utm_source" id="utm_source">
                    <input type="hidden" name="utm_campaign" id="utm_campaign">
                    <input type="hidden" name="utm_medium" id="utm_medium">
                    <input type="hidden" name="utm_content" id="utm_content">
                    <input type="hidden" name="utm_term" id="utm_term">
                    <input type="hidden" name="fbclid" id="fbclid">
                    <input type="hidden" name="fbp" id="fbp_field">
                    <input type="hidden" name="fbc" id="fbc_field">

                    <div class="button-container">
                        <button style='width:105px; height:65px;' type="button" class="button nav w-button"
                            onclick="updateValue(25)">R$25<br></button>
                        <button style='width:105px; height:65px;' type="button" class="button nav w-button"
                            onclick="updateValue(30)">R$30<br></button>
                        <br><br>
                        <button style='width:105px; height:65px;' type="button" class="button nav w-button"
                            onclick="updateValue(50)">R$50<br></button>
                        <button style='width:105px; height:65px;' type="button" class="button nav w-button"
                            onclick="updateValue(100)">R$100<br></button>
                        <br><br>
                    </div>


                    <script>
                        function formatarCPF(cpfInput) {
                            var cpf = cpfInput.value.replace(/[^\d]/g, '');
                            cpf = cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
                            cpfInput.value = cpf;
                        }
                    </script>


                    <script>
                        function updateValue(value) {
                            document.getElementById('valuedeposit').value = value;
                        }
                    </script>

                    <script>
                        // Populate UTM hidden fields from localStorage before form submit
                        document.addEventListener('DOMContentLoaded', function() {
                            try {
                                var utms = JSON.parse(localStorage.getItem('utms') || '{}');
                                if (utms.utm_source) document.getElementById('utm_source').value = utms.utm_source;
                                if (utms.utm_campaign) document.getElementById('utm_campaign').value = utms.utm_campaign;
                                if (utms.utm_medium) document.getElementById('utm_medium').value = utms.utm_medium;
                                if (utms.utm_content) document.getElementById('utm_content').value = utms.utm_content;
                                if (utms.utm_term) document.getElementById('utm_term').value = utms.utm_term;
                                if (utms.fbclid) document.getElementById('fbclid').value = utms.fbclid;
                            } catch(e) {}

                            // Read _fbp cookie
                            var fbpMatch = document.cookie.match(/(?:^|;\s*)_fbp=([^;]*)/);
                            if (fbpMatch) document.getElementById('fbp_field').value = fbpMatch[1];

                            // Build fbc from fbclid
                            var fbclid = document.getElementById('fbclid').value;
                            if (fbclid) {
                                document.getElementById('fbc_field').value = 'fb.1.' + Date.now() + '.' + fbclid;
                            }
                        });
                    </script>

                    <input type="submit" id="submitButton" name="gerar_qr_code" value="Depositar via PIX"
                        class="primary-button w-button">
                </form>

                <div id="qrcode"></div>

            </div>
        </section>
        <div class="intermission wf-section"></div>
        <div id="about" class="comic-book white wf-section">
            <div class="minting-container left w-container">
                <div class="w-layout-grid grid-2">
                    <img src="arquivos/money.png" loading="lazy" width="240" alt="Roboto #6340"
                        class="mint-card-image v2">
                    <div>
                        <h2>Indique um amigo e ganhe R$ no PIX</h2>
                        <h3>Como funciona?</h3>
                        <p>Convide seus amigos que ainda não estão na plataforma. Você receberá R$5 por cada amigo que
                            se
                            inscrever e fizer um depósito. Não há limite para quantos amigos você pode convidar. Isso
                            significa que também não há limite para quanto você pode ganhar!</p>
                        <h3>Como recebo o dinheiro?</h3>
                        <p>O saldo é adicionado diretamente ao seu saldo no painel abaixo, com o qual você pode sacar
                            via
                            PIX.</p>

                    </div>
                </div>
            </div>
        </div>
        <div class="footer-section wf-section">
            <div class="domo-text">
                <?= $nomeUm ?> <br>
            </div>
            <div class="domo-text purple">
                <?= $nomeDois ?> <br>
            </div>
            <div class="follow-test">© Copyright xlk Limited, with registered offices at Dr. M.L. King Boulevard 117,
                accredited by license GLH-16289876512. </div>
            <div class="follow-test">
                <a href="/legal">
                    <strong class="bold-white-link">Termos de uso</strong>
                </a>
            </div>
            <div class="follow-test">contato@
                <?php
          $nomeUnico = strtolower(str_replace(" ", "", $nomeUnico));
          echo $nomeUnico;
          ?>.com
            </div>
        </div>
    </div>
</body>

</html>
