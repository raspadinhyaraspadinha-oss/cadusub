<?php
/**
 * UTMify API Helper
 * Sends order data to UTMify for UTM attribution tracking.
 */

function utmify_get_config($conn) {
    $sql = "SELECT client_id, client_secret FROM gateway LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row;
    }
    return null;
}

/**
 * Send order to UTMify API
 * @param string $status "waiting_payment" or "paid"
 * @param array $data Order data with keys: orderId, amount_cents, createdAt, approvedDate, customer, trackingParameters
 */
function utmify_send_order($status, $data) {
    $apiUrl = "https://api.utmify.com.br/api-credentials/orders";
    $apiToken = defined('UTMIFY_API_TOKEN') ? UTMIFY_API_TOKEN : "698535fcd111bd400675768d";

    $body = [
        "orderId" => $data["orderId"],
        "platform" => "SubwayPay",
        "paymentMethod" => "pix",
        "status" => $status,
        "createdAt" => $data["createdAt"],
        "approvedDate" => isset($data["approvedDate"]) ? $data["approvedDate"] : null,
        "refundedAt" => null,
        "customer" => [
            "name" => isset($data["customer"]["name"]) ? $data["customer"]["name"] : "",
            "email" => isset($data["customer"]["email"]) ? $data["customer"]["email"] : "",
            "phone" => isset($data["customer"]["phone"]) ? $data["customer"]["phone"] : "",
            "document" => isset($data["customer"]["document"]) ? $data["customer"]["document"] : "",
            "country" => "BR",
            "ip" => isset($data["customer"]["ip"]) ? $data["customer"]["ip"] : ""
        ],
        "products" => [
            [
                "id" => $data["orderId"],
                "name" => "Deposito SubwayPay",
                "planId" => "deposito",
                "planName" => "Deposito",
                "quantity" => 1,
                "priceInCents" => intval($data["amount_cents"])
            ]
        ],
        "trackingParameters" => isset($data["trackingParameters"]) ? $data["trackingParameters"] : [
            "src" => null,
            "sck" => null,
            "utm_source" => null,
            "utm_campaign" => null,
            "utm_medium" => null,
            "utm_content" => null,
            "utm_term" => null,
            "fbclid" => null,
            "fbp" => null
        ],
        "commission" => [
            "totalPriceInCents" => intval($data["amount_cents"]),
            "gatewayFeeInCents" => 0,
            "userCommissionInCents" => intval($data["amount_cents"])
        ],
        "isTest" => false
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-api-token: " . $apiToken
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ["httpCode" => $httpCode, "response" => $response];
}
