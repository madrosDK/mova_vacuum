<?php

class MovaVacuum extends IPSModule
{
    private const TIMER_UPDATE = 'UpdateTimer';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Region', 'eu');
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('UseMD5Password', false);
        $this->RegisterPropertyBoolean('Debug', true);
        $this->RegisterPropertyString('BaseUrl', '');
        $this->RegisterPropertyString('AuthBasic', 'ZHJlYW1lX2FwcHYxOkFQXmR2QHpAU1FZVnhOODg=');
        $this->RegisterPropertyString('TenantId', '000002');
        $this->RegisterPropertyString('Model', 'mova.vacuum.r2587a');

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);
        $this->RegisterAttributeString('DeviceRaw', '');

        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'MOVA_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterVariableString('StatusText', 'Status', '', 1);
        $this->RegisterVariableInteger('Battery', 'Akku', '~Battery.100', 2);
        $this->RegisterVariableInteger('StateCode', 'Status-Code', '', 3);
        $this->RegisterVariableInteger('TaskStatus', 'Task-Status', '', 4);
        $this->RegisterVariableInteger('ErrorCode', 'Fehler-Code', '', 5);
        $this->RegisterVariableInteger('CleanedArea', 'Gereinigte Fläche', '', 6);
        $this->RegisterVariableInteger('CleaningTime', 'Reinigungszeit', '', 7);
        $this->RegisterVariableString('LastResponse', 'Letzte Antwort', '', 20);

        $this->EnableAction('StatusText');

        if ($this->ReadPropertyString('Username') === '' || $this->ReadPropertyString('Password') === '') {
            $this->SetStatus(101);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval > 0 ? $interval * 1000 : 0);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'StatusText':
                $this->Update();
                break;
        }
    }

    public function LoginAndDiscover()
    {
        $this->Login(true);
        $devices = $this->ApiCall('/dreame-user-iot/iotuserbind/device/listV2', null, true);
        $this->SetValueSafe('LastResponse', json_encode($devices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $found = $this->FindDevice($devices);
        if ($found !== null) {
            $did = (string)($found['did'] ?? '');
            $this->WriteAttributeString('DeviceRaw', json_encode($found, JSON_UNESCAPED_UNICODE));
            if ($did !== '' && $this->ReadPropertyString('DeviceID') === '') {
                $this->Log('Gerät gefunden: did=' . $did . ', model=' . ($found['model'] ?? 'unbekannt'));
            }
        } else {
            $this->Log('Kein passendes Gerät in der Antwort gefunden. LastResponse prüfen.');
        }
    }

    public function Update()
    {
        $props = [
            ['did' => '2.1', 'siid' => 2, 'piid' => 1],   // state/status, modellabhängig
            ['did' => '2.2', 'siid' => 2, 'piid' => 2],   // battery, modellabhängig
            ['did' => '2.3', 'siid' => 2, 'piid' => 3],
            ['did' => '4.1', 'siid' => 4, 'piid' => 1],   // task status, modellabhängig
            ['did' => '4.2', 'siid' => 4, 'piid' => 2],
            ['did' => '4.3', 'siid' => 4, 'piid' => 3]
        ];

        $result = $this->SendRpc('get_properties', $props);
        $this->SetValueSafe('LastResponse', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->ParseProperties($result);
    }

    public function Start()
    {
        // Standard-Dreame/MOVA MIOT action. Falls dein P50 abweicht, sieht man es direkt im Debug.
        return $this->SendAction(4, 1, []);
    }

    public function Pause()
    {
        return $this->SendAction(4, 2, []);
    }

    public function Dock()
    {
        return $this->SendAction(4, 3, []);
    }

    public function SendAction(int $siid, int $aiid, array $in = [])
    {
        return $this->SendRpc('action', [
            'did'  => $siid . '.' . $aiid,
            'siid' => $siid,
            'aiid' => $aiid,
            'in'   => $in
        ]);
    }

    public function SendRpc(string $method, $params)
    {
        $this->Login(false);
        $did = $this->GetDeviceID();
        if ($did === '') {
            throw new Exception('Keine Device-ID vorhanden. Bitte zuerst "Login + Geräte suchen" ausführen oder Device-ID eintragen.');
        }

        $payload = [
            'did' => $did,
            'id' => time(),
            'data' => [
                'did' => $did,
                'id' => time(),
                'method' => $method,
                'params' => $params
            ]
        ];

        return $this->ApiCall('/dreame-iot-com-10000/device/sendCommand', $payload, true);
    }

    private function Login(bool $force)
    {
        $expires = $this->ReadAttributeInteger('TokenExpires');
        if (!$force && $this->ReadAttributeString('AccessToken') !== '' && $expires > time() + 120) {
            return;
        }

        $password = $this->ReadPropertyString('Password');
        if ($this->ReadPropertyBoolean('UseMD5Password')) {
            $password = md5($password);
        }

        $data = http_build_query([
            'grant_type' => 'password',
            'scope' => 'all',
            'username' => $this->ReadPropertyString('Username'),
            'password' => $password
        ]);

        $response = $this->HttpRequest($this->BaseUrl() . '/dreame-auth/oauth/token', $data, true, false);
        if (!is_array($response) || (!isset($response['access_token']) && !isset($response['data']['access_token']))) {
            $this->SetStatus(102);
            throw new Exception('MOVAhome Login fehlgeschlagen: ' . json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        $token = $response['access_token'] ?? $response['data']['access_token'];
        $refresh = $response['refresh_token'] ?? ($response['data']['refresh_token'] ?? '');
        $expiresIn = (int)($response['expires_in'] ?? ($response['data']['expires_in'] ?? 3600));

        $this->WriteAttributeString('AccessToken', $token);
        $this->WriteAttributeString('RefreshToken', $refresh);
        $this->WriteAttributeInteger('TokenExpires', time() + $expiresIn);
        $this->SetStatus(IS_ACTIVE);
        $this->Log('Login OK, Token gültig bis ' . date('Y-m-d H:i:s', time() + $expiresIn));
    }

    private function ApiCall(string $path, $payload = null, bool $auth = true)
    {
        $data = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);
        return $this->HttpRequest($this->BaseUrl() . $path, $data, $auth, true);
    }

    private function HttpRequest(string $url, ?string $data, bool $auth, bool $json)
    {
        $headers = [
            'Accept: */*',
            'Accept-Language: en-US;q=0.8',
            'Accept-Encoding: gzip, deflate',
            'User-Agent: Mova_Smarthome/1.5.59 (iPhone; iOS 16.0; Scale/3.00)',
            'Tenant-Id: ' . $this->ReadPropertyString('TenantId')
        ];

        if ($json) {
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $headers[] = 'Authorization: Basic ' . $this->ReadPropertyString('AuthBasic');
        }

        if ($auth && $json) {
            $token = $this->ReadAttributeString('AccessToken');
            if ($token !== '') {
                $headers[] = 'Authorization: Bearer ' . $token;
            }
        }

        $this->Log('HTTP POST ' . $url . ' DATA=' . (is_string($data) ? $data : ''));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $data ?? ''
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('HTTP Fehler: ' . $err);
        }

        $this->Log('HTTP ' . $code . ' RESPONSE=' . $body);
        $decoded = json_decode($body, true);
        return $decoded ?? ['http_code' => $code, 'raw' => $body];
    }

    private function BaseUrl(): string
    {
        $custom = trim($this->ReadPropertyString('BaseUrl'));
        if ($custom !== '') {
            return rtrim($custom, '/');
        }
        return 'https://' . $this->ReadPropertyString('Region') . '.iot.mova-tech.com:13267';
    }

    private function GetDeviceID(): string
    {
        $did = trim($this->ReadPropertyString('DeviceID'));
        if ($did !== '') {
            return $did;
        }
        $raw = $this->ReadAttributeString('DeviceRaw');
        if ($raw !== '') {
            $data = json_decode($raw, true);
            return (string)($data['did'] ?? '');
        }
        return '';
    }

    private function FindDevice($response): ?array
    {
        $model = $this->ReadPropertyString('Model');
        $records = $this->Flatten($response);
        foreach ($records as $item) {
            if (!is_array($item)) {
                continue;
            }
            $itemModel = (string)($item['model'] ?? '');
            if (($model !== '' && $itemModel === $model) || strpos($itemModel, '.vacuum.') !== false) {
                return $item;
            }
        }
        return null;
    }

    private function Flatten($value): array
    {
        $out = [];
        if (is_array($value)) {
            if (isset($value['did'])) {
                $out[] = $value;
            }
            foreach ($value as $v) {
                if (is_array($v)) {
                    $out = array_merge($out, $this->Flatten($v));
                }
            }
        }
        return $out;
    }

    private function ParseProperties($result): void
    {
        $items = [];
        if (is_array($result)) {
            if (isset($result['data']['result']) && is_array($result['data']['result'])) {
                $items = $result['data']['result'];
            } elseif (isset($result['result']) && is_array($result['result'])) {
                $items = $result['result'];
            } elseif (array_is_list($result)) {
                $items = $result;
            }
        }

        foreach ($items as $item) {
            if (!is_array($item) || !array_key_exists('value', $item)) {
                continue;
            }
            $did = (string)($item['did'] ?? '');
            $value = $item['value'];
            if ($did === '2.2') {
                $this->SetValueSafe('Battery', (int)$value);
            } elseif ($did === '2.1') {
                $this->SetValueSafe('StateCode', (int)$value);
                $this->SetValueSafe('StatusText', 'Status-Code: ' . $value);
            } elseif ($did === '4.1') {
                $this->SetValueSafe('TaskStatus', (int)$value);
            } elseif ($did === '4.2') {
                $this->SetValueSafe('CleanedArea', (int)$value);
            } elseif ($did === '4.3') {
                $this->SetValueSafe('CleaningTime', (int)$value);
            }
        }
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id !== false) {
            SetValue($id, $value);
        }
    }

    private function Log(string $message): void
    {
        if ($this->ReadPropertyBoolean('Debug')) {
            IPS_LogMessage('MOVA Vacuum', '[' . $this->InstanceID . '] ' . $message);
        }
        $this->SendDebug('MOVA', $message, 0);
    }
}
