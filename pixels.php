<?php

include __DIR__ . '/conectarbanco.php';

$conn = new mysqli('localhost', $config['db_user'], $config['db_pass'], $config['db_name']);
$sql = "SELECT * FROM app";
$result2 = $conn->query($sql);
$result = $result2->fetch_assoc();
$google_ads_tag = $result['google_ads_tag'];
$facebook_ads_tag = $result['facebook_ads_tag'];
$facebook_capi_token = isset($result['facebook_capi_token']) ? $result['facebook_capi_token'] : '';
$conn->close();

?>

<!-- Facebook Pixel Code -->
<script>
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '<?php echo $facebook_ads_tag; ?>');
    fbq('track', 'PageView');
</script>

<noscript><img height="1" width="1" style="display:none"
    src="https://www.facebook.com/tr?id=<?php echo $facebook_ads_tag; ?>&ev=PageView&noscript=1"
/></noscript>
<!-- End Facebook Pixel Code -->

<!-- Google Ads Tag -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $google_ads_tag; ?>"> </script>

<script>
    window.dataLayer = window.dataLayer || [] ;
    function gtag ( ) {dataLayer.push (arguments) ; }
    gtag ('js' , new Date () ) ;
    gtag ('config', '<?php echo $google_ads_tag; ?>') ;
</script>

<?php
// --- Facebook CAPI: PageView server-side event ---
if (!empty($facebook_ads_tag) && !empty($facebook_capi_token)) {
    include_once __DIR__ . '/lib/facebook_capi.php';

    $baseUrl_px = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on" ? "https" : "http") . "://" . $_SERVER["HTTP_HOST"];
    $currentUrl_px = $baseUrl_px . $_SERVER["REQUEST_URI"];

    $fbUserData_px = [
        "ip" => isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "",
        "ua" => isset($_SERVER["HTTP_USER_AGENT"]) ? $_SERVER["HTTP_USER_AGENT"] : ""
    ];

    if (isset($_COOKIE["_fbp"])) {
        $fbUserData_px["fbp"] = $_COOKIE["_fbp"];
    }

    fb_capi_send_event($facebook_ads_tag, $facebook_capi_token, "PageView", $fbUserData_px, [], $currentUrl_px);
}
?>
