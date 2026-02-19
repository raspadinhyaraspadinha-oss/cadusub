<?php
/**
 * Check payment status by token (payment_code from Mangofy, stored as externalreference)
 * Used by frontend polling to detect when payment is confirmed.
 */

if (!isset($_GET["token"])) {
    http_response_code(400);
    echo json_encode(["message" => "Token obrigatório"]);
    exit();
}

$externalReference = $_GET["token"];

include "./../conectarbanco.php";

$conn = new mysqli(
    "localhost",
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["message" => "Erro de conexão"]);
    exit();
}

$stmt = $conn->prepare("SELECT status FROM confirmar_deposito WHERE externalreference = ?");
$stmt->bind_param("s", $externalReference);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$row) {
    echo json_encode(["message" => "Token inválido"]);
    http_response_code(400);
    exit();
}

echo json_encode($row);
http_response_code(200);
?>
