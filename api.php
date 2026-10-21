<?php

/**
 * One-off MTN MoMo SANDBOX setup. Run from the project folder:
 *
 *     php api.php
 *
 * It (1) creates a sandbox API user, (2) creates that user's API key,
 * (3) fetches an access token, (4) sends a test Request-to-Pay to MTN's
 * a sandbox number that succeeds and checks the result. At the end it prints
 * the values to put into .env / the Docker Manager environment.
 *
 * Do NOT commit or upload this file - it holds your subscription key.
 */

// ---- FILL IN (only this section) -----------------------------------------
$subscriptionKey = 'cb0a8576b65f4de5ad81fa45bc473b84'; // Collection product "Primary key" from momodeveloper.mtn.com > Profile > Subscriptions
$callbackHost = 'cy-market.com';                    // your domain, no https:// and no path
// ---------------------------------------------------------------------------

$base = 'https://sandbox.momodeveloper.mtn.com';

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0F) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3F) | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function call(string $method, string $url, array $headers, ?string $body = null, ?string $basicAuth = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        // XAMPP's PHP ships without a CA bundle; use a local one if present.
        CURLOPT_CAINFO => (function () {
            foreach (['C:/xampp/apache/bin/curl-ca-bundle.crt', 'C:/xampp/php/extras/ssl/cacert.pem'] as $p) {
                if (is_file($p)) {
                    return $p;
                }
            }

            return ini_get('curl.cainfo') ?: null;
        })(),
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if ($basicAuth !== null) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth);
    }
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return ['code' => $code, 'body' => (string) $response, 'json' => json_decode((string) $response, true), 'error' => $error];
}

function step(string $title): void
{
    echo "\n=== $title ===\n";
}

function fail(string $message, array $r): never
{
    echo "FAILED: $message\nHTTP {$r['code']} {$r['error']}\n{$r['body']}\n";
    exit(1);
}

$sub = "Ocp-Apim-Subscription-Key: $subscriptionKey";

// 1. Create the API user (the UUID you generate here IS the API user) -------
step('1/4 Create API user');
$apiUser = uuid4();
$r = call('POST', "$base/v1_0/apiuser", ["X-Reference-Id: $apiUser", $sub, 'Content-Type: application/json'], json_encode(['providerCallbackHost' => $callbackHost]));
echo "HTTP {$r['code']} (expect 201)\n";
$r['code'] === 201 || fail('could not create API user (check the subscription key is for the Collection product)', $r);
echo "API user: $apiUser\n";

// 2. Create the API key for that user --------------------------------------
step('2/4 Create API key');
$r = call('POST', "$base/v1_0/apiuser/$apiUser/apikey", [$sub, 'Content-Length: 0']);
echo "HTTP {$r['code']} (expect 201)\n";
$apiKey = $r['json']['apiKey'] ?? null;
$apiKey || fail('no apiKey returned', $r);
echo "API key: $apiKey\n";

// 3. Access token (proves user + key + subscription key work together) ------
step('3/4 Get access token');
$r = call('POST', "$base/collection/token/", [$sub, 'Content-Length: 0'], null, "$apiUser:$apiKey");
echo "HTTP {$r['code']} (expect 200)\n";
$token = $r['json']['access_token'] ?? null;
$token || fail('no access token - subscription key must be subscribed to the "Collection" product', $r);
echo "Token OK (expires in {$r['json']['expires_in']}s)\n";

// 4. Test payment: any number except 46733123450-54 succeeds in the sandbox (those five simulate failures) -----
step('4/4 Test Request to Pay (EUR 1.00 from 46733100001)');
$payId = uuid4();
$r = call('POST', "$base/collection/v1_0/requesttopay", [
    "Authorization: Bearer $token",
    "X-Reference-Id: $payId",
    'X-Target-Environment: sandbox',
    $sub,
    'Content-Type: application/json',
], json_encode([
    'amount' => '1.00',
    'currency' => 'EUR',
    'externalId' => 'TEST-'.time(),
    'payer' => ['partyIdType' => 'MSISDN', 'partyId' => '46733100001'],
    'payerMessage' => 'CY-Market test',
    'payeeNote' => 'CY-Market test',
]));
echo "HTTP {$r['code']} (expect 202)\n";
$r['code'] === 202 || fail('request-to-pay rejected', $r);

sleep(3);
$r = call('GET', "$base/collection/v1_0/requesttopay/$payId", [
    "Authorization: Bearer $token",
    'X-Target-Environment: sandbox',
    $sub,
]);
echo "Status check: HTTP {$r['code']} -> {$r['body']}\n";
$ok = ($r['json']['status'] ?? '') === 'SUCCESSFUL';
echo $ok ? "Sandbox payment SUCCESSFUL.\n" : "Payment did not report SUCCESSFUL (see body above).\n";

// Result --------------------------------------------------------------------
step('PUT THESE INTO .env (and Docker Manager > environment variables)');
echo <<<TXT
MOMO_MODE=live
MOMO_ENVIRONMENT=sandbox
MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com
MOMO_CURRENCY=EUR
MOMO_SUBSCRIPTION_KEY=$subscriptionKey
MOMO_API_USER=$apiUser
MOMO_API_KEY=$apiKey
MOMO_CALLBACK_URL=https://$callbackHost/webhooks/mtn-momo

TXT;
