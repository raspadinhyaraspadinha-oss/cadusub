<?php
/**
 * Facebook Conversions API (CAPI) Helper
 * Sends server-side events to Facebook for better attribution.
 */

function fb_capi_get_config($conn) {
    $sql = "SELECT facebook_ads_tag FROM app LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return $row["facebook_ads_tag"];
    }
    return null;
}

/**
 * Send event to Facebook Conversions API
 * @param string $pixelId Facebook Pixel ID
 * @param string $accessToken Facebook CAPI access token
 * @param string $eventName Event name (PageView, AddToCart, Purchase, etc.)
 * @param array $userData User data (email, phone, ip, ua, fbp, fbc, etc.)
 * @param array $customData Custom event data (value, currency, content_name, etc.)
 * @param string|null $eventSourceUrl The URL where the event happened
 */
function fb_capi_send_event($pixelId, $accessToken, $eventName, $userData = [], $customData = [], $eventSourceUrl = null) {
    if (empty($pixelId) || empty($accessToken)) {
        return null;
    }

    $url = "https://graph.facebook.com/v18.0/{$pixelId}/events";

    $userDataPayload = [];
    if (!empty($userData["email"])) {
        $userDataPayload["em"] = [hash("sha256", strtolower(trim($userData["email"])))];
    }
    if (!empty($userData["phone"])) {
        $phone = preg_replace("/[^0-9]/", "", $userData["phone"]);
        if (substr($phone, 0, 2) !== "55") {
            $phone = "55" . $phone;
        }
        $userDataPayload["ph"] = [hash("sha256", $phone)];
    }
    if (!empty($userData["ip"])) {
        $userDataPayload["client_ip_address"] = $userData["ip"];
    }
    if (!empty($userData["ua"])) {
        $userDataPayload["client_user_agent"] = $userData["ua"];
    }
    if (!empty($userData["fbp"])) {
        $userDataPayload["fbp"] = $userData["fbp"];
    }
    if (!empty($userData["fbc"])) {
        $userDataPayload["fbc"] = $userData["fbc"];
    }
    if (!empty($userData["external_id"])) {
        $userDataPayload["external_id"] = [hash("sha256", $userData["external_id"])];
    }

    $eventData = [
        "event_name" => $eventName,
        "event_time" => time(),
        "action_source" => "website",
        "user_data" => $userDataPayload
    ];

    if (!empty($eventSourceUrl)) {
        $eventData["event_source_url"] = $eventSourceUrl;
    }

    if (!empty($customData)) {
        $eventData["custom_data"] = $customData;
    }

    $payload = [
        "data" => [$eventData],
        "access_token" => $accessToken
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ["httpCode" => $httpCode, "response" => $response];
}
