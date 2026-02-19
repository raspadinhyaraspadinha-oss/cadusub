<?php
/**
 * Mangofy Postback/Webhook Endpoint
 *
 * Receives 2 POST requests from Mangofy when payment is approved:
 *   1. Raw format with payment_status = "approved"
 *   2. Sale format with type = "sale" and payment_status = "approved"
 *
 * Identifies the transaction by payment_code (stored as externalreference in confirmar_deposito).
 * IMPORTANT: metadata comes back empty from Mangofy, so we identify by payment_code.
 */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(200);
    exit();
}

$payload = file_get_contents("php://input");
$data = json_decode($payload, true);

if (is_null($data)) {
    http_response_code(200);
    exit();
}

// Accept both POST formats from Mangofy
$paymentCode = isset($data["payment_code"]) ? $data["payment_code"] : null;
$paymentStatus = isset($data["payment_status"]) ? $data["payment_status"] : null;

if (empty($paymentCode) || $paymentStatus !== "approved") {
    // Return 200 OK even for non-approved statuses to prevent re-sends
    http_response_code(200);
    exit();
}

function get_conn_webhook()
{
    include "./../conectarbanco.php";

    return new mysqli(
        "localhost",
        $config["db_user"],
        $config["db_pass"],
        $config["db_name"]
    );
}

$conn = get_conn_webhook();

if ($conn->connect_error) {
    http_response_code(200);
    exit();
}

// Find the deposit by payment_code (stored as externalreference)
$stmt = $conn->prepare("SELECT * FROM confirmar_deposito WHERE externalreference = ?");
$stmt->bind_param("s", $paymentCode);
$stmt->execute();
$result = $stmt->get_result();
$depositRecord = $result->fetch_assoc();
$stmt->close();

if (!$depositRecord) {
    // Session not found - return 200 to avoid Mangofy retries
    http_response_code(200);
    exit();
}

// Idempotency: if already marked as PAID_OUT, skip
if ($depositRecord["status"] === "PAID_OUT") {
    http_response_code(200);
    exit();
}

// Update deposit status to PAID_OUT
$stmtUpdate = $conn->prepare("UPDATE confirmar_deposito SET status = 'PAID_OUT' WHERE externalreference = ?");
$stmtUpdate->bind_param("s", $paymentCode);
$stmtUpdate->execute();
$stmtUpdate->close();

$valor_depositado = $depositRecord["valor"];
$email = $depositRecord["email"];

// Get user data
$stmtUser = $conn->prepare("SELECT * FROM appconfig WHERE email = ?");
$stmtUser->bind_param("s", $email);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
$resultUser = $resultUser->fetch_assoc();
$stmtUser->close();

// Get app config
$sqlApp = "SELECT * FROM app LIMIT 1";
$resultApp = $conn->query($sqlApp);
$resultApp = $resultApp->fetch_assoc();

// Count deposits for this user
$stmtCount = $conn->prepare("SELECT count(*) as total FROM confirmar_deposito WHERE email = ?");
$stmtCount->bind_param("s", $email);
$stmtCount->execute();
$resultDeposito = $stmtCount->get_result()->fetch_assoc();
$stmtCount->close();

// Update user's depositou field
$stmtDep = $conn->prepare("UPDATE appconfig SET depositou = depositou + ? WHERE email = ?");
$stmtDep->bind_param("ds", $valor_depositado, $email);
$stmtDep->execute();
$stmtDep->close();

// CPA logic for affiliate on first qualified deposit
if ($resultDeposito["total"] >= 1) {
    if (!is_null($resultUser["afiliado"]) && !empty($resultUser["afiliado"])) {
        if (intval($depositRecord["valor"]) >= $resultApp["deposito_min_cpa"]) {
            $randomNumber = rand(0, 100);
            if ($randomNumber <= intval($resultApp["chance_afiliado"])) {
                if ($resultUser["cpa"] > 0) {
                    $conn->query(
                        sprintf(
                            "UPDATE appconfig SET status_primeiro_deposito=1 WHERE email = '%s'",
                            $conn->real_escape_string($resultUser["email"])
                        )
                    );
                    $conn->query(
                        sprintf(
                            "UPDATE appconfig SET saldo_cpa = saldo_cpa + %s WHERE id = '%s'",
                            intval($resultUser["cpa"]),
                            $conn->real_escape_string($resultUser["afiliado"])
                        )
                    );
                } else {
                    $conn->query(
                        sprintf(
                            "UPDATE appconfig SET status_primeiro_deposito=1 WHERE email = '%s'",
                            $conn->real_escape_string($resultUser["email"])
                        )
                    );
                    $conn->query(
                        sprintf(
                            "UPDATE appconfig SET saldo_cpa = saldo_cpa + %s WHERE id = '%s'",
                            intval($resultApp["cpa"]),
                            $conn->real_escape_string($resultUser["afiliado"])
                        )
                    );
                }
            }
        }
    }
}

// Credit user balance
$stmtSaldo = $conn->prepare("UPDATE appconfig SET saldo = saldo + ? WHERE email = ?");
$stmtSaldo->bind_param("ds", $valor_depositado, $email);
$stmtSaldo->execute();
$stmtSaldo->close();

// --- UTMify: send paid status ---
include_once "./../lib/utmify.php";

$brtTimeZone = new DateTimeZone("America/Sao_Paulo");
$dateTimeNow = new DateTime("now", $brtTimeZone);
$approvedDate = $dateTimeNow->format("Y-m-d H:i:s");

// Build UTMify paid data from what we know
$utmData = [
    "orderId" => $paymentCode,
    "amount_cents" => intval(floatval($valor_depositado) * 100),
    "createdAt" => $depositRecord["data"] ? date("Y-m-d H:i:s", strtotime(str_replace("/", "-", $depositRecord["data"]))) : $approvedDate,
    "approvedDate" => $approvedDate,
    "customer" => [
        "name" => $resultUser ? ($resultUser["nome"] ?? "") : "",
        "email" => $email,
        "phone" => $resultUser ? ($resultUser["telefone"] ?? "") : "",
        "document" => $resultUser ? ($resultUser["cpf"] ?? "") : "",
        "ip" => ""
    ]
];

utmify_send_order("paid", $utmData);

// --- Facebook CAPI: Purchase event ---
include_once "./../lib/facebook_capi.php";

$fb_pixel_id = isset($resultApp["facebook_ads_tag"]) ? $resultApp["facebook_ads_tag"] : "";
$fb_capi_token = isset($resultApp["facebook_capi_token"]) ? $resultApp["facebook_capi_token"] : "";

if (!empty($fb_pixel_id) && !empty($fb_capi_token)) {
    $baseUrl = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];

    $fbUserData = [
        "email" => $email,
        "phone" => $resultUser ? ($resultUser["telefone"] ?? "") : "",
        "external_id" => $email
    ];
    $fbCustomData = [
        "value" => floatval($valor_depositado),
        "currency" => "BRL",
        "content_name" => "Deposito PIX Confirmado",
        "content_type" => "product"
    ];
    fb_capi_send_event($fb_pixel_id, $fb_capi_token, "Purchase", $fbUserData, $fbCustomData, $baseUrl . "/deposito/");
}

$conn->close();

http_response_code(200);
echo json_encode(["success" => true, "message" => "Pagamento confirmado."]);
exit();
