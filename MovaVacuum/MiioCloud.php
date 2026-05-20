<?php

declare(strict_types=1);

/**
 * Xiaomi/MiIO Cloud helper based on the communication model used by Home Assistant dreame-vacuum.
 *
 * This is intentionally separated from module.php so the existing MOVAhome status path remains untouched.
 * Required PHP extension: curl
 */
class MovaMiioCloud
{
    private string $username;
    private string $password;
    private string $country;
    private string $clientId;
    private string $cookieFile;

    private string $serviceToken = '';
    private string $ssecurity = '';
    private string $userId = '';
    private string $location = '';

    public function __construct(string $username, string $password, string $country = 'de', string $clientId = '')
    {
        $this->username = $username;
        $this->password = $password;
        $this->country = $country !== '' ? strtolower($country) : 'de';
        $this->clientId = $clientId !== '' ? $clientId : self::generateClientId();
        $this->cookieFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mova_miio_' . md5($username . $this->clientId) . '.cookie';
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getAuthKey(): string
    {
        if ($this->serviceToken === '' || $this->ssecurity === '' || $this->userId === '') {
            return '';
        }
        return $this->serviceToken . ' ' . $this->ssecurity . ' ' . $this->userId . ' ' . $this->clientId;
    }

    public function setAuthKey(string $authKey): void
    {
        $parts = preg_split('/\s+/', trim($authKey));
        if (is_array($parts) && count($parts) === 4) {
            $this->serviceToken = $parts[0];
            $this->ssecurity = $parts[1];
            $this->userId = $parts[2];
            $this->clientId = $parts[3];
        }
    }

    public function login(): array
    {
        $step1 = $this->loginStep1();
        if (($step1['_sign'] ?? '') === '') {
            throw new Exception('Xiaomi Login Step 1 fehlgeschlagen: _sign fehlt. Antwort=' . json_encode($step1, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $step2 = $this->loginStep2((string)$step1['_sign']);
        if (($step2['location'] ?? '') === '' || ($step2['ssecurity'] ?? '') === '') {
            throw new Exception('Xiaomi Login Step 2 fehlgeschlagen. Antwort=' . json_encode($step2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->userId = (string)($step2['userId'] ?? $this->userId);
        $this->ssecurity = (string)$step2['ssecurity'];
        $this->location = (string)$step2['location'];

        $this->loginStep3();
        if ($this->serviceToken === '') {
            throw new Exception('Xiaomi Login Step 3 fehlgeschlagen: serviceToken fehlt.');
        }

        return [
            'ok' => true,
            'userId' => $this->userId,
            'clientId' => $this->clientId,
            'authKey' => $this->getAuthKey(),
        ];
    }

    public function rpc(string $did, string $method, $params)
    {
        if ($this->serviceToken === '' || $this->ssecurity === '' || $this->userId === '') {
            $this->login();
        }

        $url = $this->apiUrl() . '/v2/home/rpc/' . rawurlencode($did);
        return $this->request($url, [
            'data' => json_encode([
                'method' => $method,
                'params' => $params,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function action(string $did, int $siid, int $aiid, array $in = [])
    {
        return $this->rpc($did, 'action', [
            'did' => $siid . '.' . $aiid,
            'siid' => $siid,
            'aiid' => $aiid,
            'in' => $in,
        ]);
    }

    public function getProperties(string $did, array $props)
    {
        return $this->rpc($did, 'get_properties', $props);
    }

    private function loginStep1(): array
    {
        $url = 'https://account.xiaomi.com/pass/serviceLogin?sid=xiaomiio&_json=true';
        $response = $this->curl($url, null, [
            'User-Agent: ' . $this->userAgent(),
            'Content-Type: application/x-www-form-urlencoded',
        ], false);
        return self::decodeXiaomiJson($response['body']);
    }

    private function loginStep2(string $sign): array
    {
        $url = 'https://account.xiaomi.com/pass/serviceLoginAuth2?_json=true';
        $data = http_build_query([
            'user' => $this->username,
            'hash' => strtoupper(md5($this->password)),
            'callback' => 'https://sts.api.io.mi.com/sts',
            'sid' => 'xiaomiio',
            'qs' => '%3Fsid%3Dxiaomiio%26_json%3Dtrue',
            '_sign' => $sign,
        ]);

        $response = $this->curl($url, $data, [
            'User-Agent: ' . $this->userAgent(),
            'Content-Type: application/x-www-form-urlencoded',
        ], false);
        return self::decodeXiaomiJson($response['body']);
    }

    private function loginStep3(): void
    {
        $response = $this->curl($this->location, null, [
            'User-Agent: ' . $this->userAgent(),
            'Content-Type: application/x-www-form-urlencoded',
        ], false);

        foreach (($response['headers'] ?? []) as $header) {
            if (stripos($header, 'Set-Cookie:') === 0 && preg_match('/serviceToken=([^;]+)/', $header, $m)) {
                $this->serviceToken = $m[1];
                return;
            }
        }
    }

    private function request(string $url, array $params)
    {
        $nonce = self::generateNonce();
        $signedNonce = self::signedNonce($this->ssecurity, $nonce);
        $fields = self::generateEncryptedParams($url, 'POST', $signedNonce, $nonce, $params, $this->ssecurity);

        $headers = [
            'User-Agent: ' . $this->userAgent(),
            'Accept-Encoding: identity',
            'x-xiaomi-protocal-flag-cli: PROTOCAL-HTTP2',
            'Content-Type: application/x-www-form-urlencoded',
            'MIOT-ENCRYPT-ALGORITHM: ENCRYPT-RC4',
        ];

        $cookie = 'userId=' . rawurlencode($this->userId)
            . '; yetAnotherServiceToken=' . rawurlencode($this->serviceToken)
            . '; serviceToken=' . rawurlencode($this->serviceToken)
            . '; locale=de_DE; timezone=GMT%2B01%3A00; is_daylight=0; dst_offset=0; channel=MI_APP_STORE';

        $response = $this->curl($url, http_build_query($fields), $headers, true, $cookie);
        $body = trim((string)$response['body']);
        if ($body === '') {
            return null;
        }

        $decrypted = self::decryptRc4($signedNonce, $body);
        $decoded = json_decode($decrypted, true);
        return $decoded !== null ? $decoded : [
            'raw' => $body,
            'decrypted' => $decrypted,
            'http_code' => $response['code'],
        ];
    }

    private function curl(string $url, ?string $data, array $headers, bool $post, string $cookie = ''): array
    {
        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_ENCODING => '',
            CURLOPT_HEADERFUNCTION => function ($curl, $header) use (&$responseHeaders) {
                $responseHeaders[] = trim($header);
                return strlen($header);
            },
        ]);

        if ($cookie !== '') {
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }
        if ($post || $data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data ?? '');
        }

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Xiaomi HTTP Fehler: ' . $err);
        }

        return [
            'code' => $code,
            'body' => (string)$body,
            'headers' => $responseHeaders,
        ];
    }

    private function apiUrl(): string
    {
        $prefix = $this->country === 'cn' ? '' : ($this->country . '.');
        return 'https://' . $prefix . 'api.io.mi.com/app';
    }

    private function userAgent(): string
    {
        return 'Android-7.1.1-1.0.0-ONEPLUS A3010-136-' . $this->clientId . ' APP/xiaomi.smarthome APPV/62830';
    }

    private static function decodeXiaomiJson(string $body): array
    {
        $body = str_replace('&&&START&&&', '', $body);
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : ['raw' => $body];
    }

    private static function generateClientId(): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $out = '';
        for ($i = 0; $i < 16; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    private static function generateNonce(): string
    {
        $bytes = random_bytes(8);
        $minutes = (int)floor((int)round(microtime(true) * 1000) / 60000);

        $part = '';
        while ($minutes > 0) {
            $part = chr($minutes & 0xff) . $part;
            $minutes >>= 8;
        }
        if ($part === '') {
            $part = "\0";
        }

        return base64_encode($bytes . $part);
    }

    private static function signedNonce(string $ssecurity, string $nonce): string
    {
        return base64_encode(hash('sha256', base64_decode($ssecurity) . base64_decode($nonce), true));
    }

    private static function generateEncryptedParams(string $url, string $method, string $signedNonce, string $nonce, array $params, string $ssecurity): array
    {
        $params['rc4_hash__'] = self::generateEncryptedSignature($url, $method, $signedNonce, $params);

        foreach ($params as $key => $value) {
            $params[$key] = self::encryptRc4($signedNonce, (string)$value);
        }

        $params['signature'] = self::generateEncryptedSignature($url, $method, $signedNonce, $params);
        $params['ssecurity'] = $ssecurity;
        $params['_nonce'] = $nonce;

        return $params;
    }

    private static function generateEncryptedSignature(string $url, string $method, string $signedNonce, array $params): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = str_replace('/app/', '/', $path);
        $parts = [strtoupper($method), $path];

        foreach ($params as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        $parts[] = $signedNonce;

        return base64_encode(sha1(implode('&', $parts), true));
    }

    private static function encryptRc4(string $password, string $payload): string
    {
        return base64_encode(self::rc4(base64_decode($password), $payload));
    }

    private static function decryptRc4(string $password, string $payload): string
    {
        return self::rc4(base64_decode($password), base64_decode($payload));
    }

    private static function rc4(string $key, string $data): string
    {
        $s = range(0, 255);
        $j = 0;
        $keyLength = strlen($key);

        for ($i = 0; $i < 256; $i++) {
            $j = ($j + $s[$i] + ord($key[$i % $keyLength])) & 255;
            $tmp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $tmp;
        }

        $i = 0;
        $j = 0;

        for ($drop = 0; $drop < 1024; $drop++) {
            $i = ($i + 1) & 255;
            $j = ($j + $s[$i]) & 255;
            $tmp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $tmp;
            $discard = $s[($s[$i] + $s[$j]) & 255];
        }

        $out = '';
        $len = strlen($data);

        for ($n = 0; $n < $len; $n++) {
            $i = ($i + 1) & 255;
            $j = ($j + $s[$i]) & 255;
            $tmp = $s[$i];
            $s[$i] = $s[$j];
            $s[$j] = $tmp;
            $k = $s[($s[$i] + $s[$j]) & 255];
            $out .= chr(ord($data[$n]) ^ $k);
        }

        return $out;
    }
}
