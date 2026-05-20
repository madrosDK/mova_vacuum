<?php

declare(strict_types=1);

class MovaVacuum extends IPSModule
{
    private const TIMER_UPDATE = 'UpdateTimer';
    private const PASSWORD_SALT = 'RAylYC%fmSKp7%Tq';
    private const DEFAULT_IOT_PREFIX = '20000';

    private const STATE_NAMES = [
        -1 => 'Unbekannt',
        1 => 'Reinigt',
        2 => 'Bereit',
        3 => 'Pausiert',
        4 => 'Fehler',
        5 => 'Faehrt zur Station',
        6 => 'Laedt',
        7 => 'Wischt',
        8 => 'Trocknet',
        9 => 'Waescht',
        10 => 'Faehrt zum Waschen',
        11 => 'Kartierung',
        12 => 'Saugt und wischt',
        13 => 'Voll geladen',
        14 => 'Update',
    ];

    private const TASK_NAMES = [
        -1 => 'Unbekannt',
        0 => 'Abgeschlossen',
        1 => 'Auto-Reinigung',
        2 => 'Zonenreinigung',
        3 => 'Raumreinigung',
        4 => 'Spot-Reinigung',
        5 => 'Schnellkartierung',
        6 => 'Auto-Reinigung pausiert',
        7 => 'Zonenreinigung pausiert',
        8 => 'Raumreinigung pausiert',
        9 => 'Spot-Reinigung pausiert',
        11 => 'Andocken pausiert',
        12 => 'Wischen pausiert',
        25 => 'Mopp einsetzen',
        26 => 'Mopp entfernen',
    ];

    private const CHARGING_NAMES = [
        -1 => 'Unbekannt',
        1 => 'Laedt',
        2 => 'Laedt nicht',
        3 => 'Voll geladen',
        5 => 'Faehrt zur Station',
    ];

    private const ERROR_NAMES = [
        0 => 'Kein Fehler',
        1 => 'Angehoben',
        2 => 'Absturzsensor',
        3 => 'Stossfaenger',
        11 => 'Staubbehaelter voll',
        12 => 'Hauptbuerste',
        13 => 'Seitenbuerste',
        14 => 'Luefter',
        20 => 'Akku niedrig',
        21 => 'Ladefehler',
        47 => 'Blockiert',
        48 => 'LDS Fehler',
        51 => 'Filter blockiert',
        56 => 'Laser Fehler',
        68 => 'Mopp entfernen',
        101 => 'Staubbehaelter voll',
        105 => 'Wassertank',
        106 => 'Schmutzwassertank',
        111 => 'Mop-Pad',
        116 => 'Frischwassertank',
        118 => 'Schmutzwassertank-Fuellstand',
    ];

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Region', 'eu');
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('UseMD5Password', true);
        $this->RegisterPropertyBoolean('Debug', true);
        $this->RegisterPropertyString('BaseUrl', '');
        $this->RegisterPropertyString('AuthBasic', '');
        $this->RegisterPropertyString('TenantId', '000002');
        $this->RegisterPropertyString('Model', 'mova.vacuum.r2587a');

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);
        $this->RegisterAttributeString('DeviceRaw', '');
        $this->RegisterAttributeString('LastDeviceID', '');

        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'MOVA_Update($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterProfileIntegerEx('MOVA.SuctionLevel', '', '', '', [
            [0, 'Leise', '', -1],
            [1, 'Standard', '', -1],
            [2, 'Stark', '', -1],
            [3, 'Turbo', '', -1],
        ]);
        $this->RegisterProfileIntegerEx('MOVA.WaterVolume', '', '', '', [
            [1, 'Niedrig', '', -1],
            [2, 'Mittel', '', -1],
            [3, 'Hoch', '', -1],
        ]);
        $this->RegisterProfileIntegerEx('MOVA.CleaningMode', '', '', '', [
            [0, 'Saugen', '', -1],
            [1, 'Wischen', '', -1],
            [2, 'Saugen und Wischen', '', -1],
        ]);

        $this->RegisterVariableString('DeviceName', 'Geraet', '', 0);
        $this->RegisterVariableString('StatusText', 'Status', '', 1);
        $this->RegisterVariableInteger('Battery', 'Akku', '~Battery.100', 2);
        $this->RegisterVariableInteger('StateCode', 'State-Code', '', 3);
        $this->RegisterVariableInteger('ChargingStatus', 'Ladestatus-Code', '', 4);
        $this->RegisterVariableInteger('ErrorCode', 'Fehler-Code', '', 5);
        $this->RegisterVariableString('ErrorText', 'Fehler', '', 6);
        $this->RegisterVariableInteger('TaskStatus', 'Task-Status-Code', '', 7);
        $this->RegisterVariableInteger('CleanedArea', 'Gereinigte Flaeche', '', 8);
        $this->RegisterVariableInteger('CleaningTime', 'Reinigungszeit', '', 9);
        $this->RegisterVariableInteger('SuctionLevel', 'Saugleistung', 'MOVA.SuctionLevel', 10);
        $this->RegisterVariableInteger('WaterVolume', 'Wasserstufe', 'MOVA.WaterVolume', 11);
        $this->RegisterVariableInteger('CleaningMode', 'Reinigungsmodus', 'MOVA.CleaningMode', 12);
        $this->RegisterVariableString('LastResponse', 'Letzte Antwort', '', 50);

        $this->EnableAction('SuctionLevel');
        $this->EnableAction('WaterVolume');
        $this->EnableAction('CleaningMode');

        if ($this->ReadPropertyString('Username') === '' || $this->ReadPropertyString('Password') === '') {
            $this->SetStatus(104);
        } else {
            $this->SetStatus(IS_ACTIVE);
        }

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval > 0 ? $interval * 1000 : 0);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'SuctionLevel':
                $this->SetProperty(4, 4, (int)$Value);
                break;
            case 'WaterVolume':
                $this->SetProperty(4, 5, (int)$Value);
                break;
            case 'CleaningMode':
                $this->SetProperty(4, 23, (int)$Value);
                break;
            default:
                throw new Exception('Invalid ident: ' . $Ident);
        }

        $this->Update();
    }

    public function LoginAndDiscover()
    {
        try {
            $this->Login(true);

            $devices = $this->ApiCall('/dreame-user-iot/iotuserbind/device/listV2', [
                'sharedStatus' => 1,
                'current' => 1,
                'size' => 100,
                'lang' => 'de',
                'timestamp' => $this->NowMilliseconds(),
            ], true);
            $this->SetValueSafe('LastResponse', $this->Encode($devices));

            $found = $this->FindDevice($devices);
            if ($found === null) {
                $this->Log('Kein passendes Geraet gefunden. LastResponse pruefen.');
                return false;
            }

            $did = (string)($found['did'] ?? $found['deviceId'] ?? '');
            $this->WriteAttributeString('DeviceRaw', $this->Encode($found));
            $this->WriteAttributeString('LastDeviceID', $did);
            $this->SetValueSafe('DeviceName', (string)($found['customName'] ?? $found['name'] ?? $found['deviceInfo']['displayName'] ?? $found['model'] ?? $did));
            $this->Log('Geraet gefunden: did=' . $did . ', model=' . ($found['model'] ?? 'unbekannt'));
            return true;
        } catch (Exception $e) {
            $this->HandleException('Login/Geraetesuche', $e);
            return false;
        }
    }

    public function Update()
    {
        try {
            $props = [
                $this->PropertyRequest('state', 2, 1),
                $this->PropertyRequest('error', 2, 2),
                $this->PropertyRequest('battery', 3, 1),
                $this->PropertyRequest('charging_status', 3, 2),
                $this->PropertyRequest('status', 4, 1),
                $this->PropertyRequest('cleaning_time', 4, 2),
                $this->PropertyRequest('cleaned_area', 4, 3),
                $this->PropertyRequest('suction_level', 4, 4),
                $this->PropertyRequest('water_volume', 4, 5),
                $this->PropertyRequest('task_status', 4, 7),
                $this->PropertyRequest('cleaning_mode', 4, 23),
                $this->PropertyRequest('self_wash_base_status', 4, 25),
                $this->PropertyRequest('main_brush_left', 9, 2),
                $this->PropertyRequest('side_brush_left', 10, 2),
                $this->PropertyRequest('filter_left', 11, 1),
                $this->PropertyRequest('sensor_dirty_left', 16, 1),
                $this->PropertyRequest('mop_pad_left', 18, 1),
            ];

            $result = $this->SendRpc('get_properties', $props);
            $this->SetValueSafe('LastResponse', $this->Encode($result));
            $this->ParseProperties($result);
            return true;
        } catch (Exception $e) {
            $this->HandleException('Status aktualisieren', $e);
            return false;
        }
    }

    public function Start()
    {
        return $this->SendAction(2, 1, []);
    }

    public function Pause()
    {
        return $this->SendAction(2, 2, []);
    }

    public function Dock()
    {
        return $this->SendAction(3, 1, []);
    }

    public function Stop()
    {
        return $this->SendAction(4, 2, []);
    }

    public function Locate()
    {
        return $this->SendAction(7, 1, []);
    }

    public function ClearWarning()
    {
        return $this->SendAction(4, 3, []);
    }

    public function StartAutoEmpty()
    {
        return $this->SendAction(15, 1, []);
    }

    public function RawRpc(string $method, string $jsonParams)
    {
        $params = json_decode($jsonParams, true);
        if ($params === null && trim($jsonParams) !== 'null') {
            throw new Exception('RawRpc: jsonParams ist kein gueltiges JSON.');
        }
        $result = $this->SendRpc($method, $params);
        $this->SetValueSafe('LastResponse', $this->Encode($result));
        return $result;
    }

    public function SendAction(int $siid, int $aiid, array $in = [])
    {
        $result = $this->SendRpc('action', [
            'did' => $siid . '.' . $aiid,
            'siid' => $siid,
            'aiid' => $aiid,
            'in' => $in,
        ]);
        $this->SetValueSafe('LastResponse', $this->Encode($result));
        return $result;
    }

    public function SetProperty(int $siid, int $piid, $value)
    {
        $result = $this->SendRpc('set_properties', [[
            'did' => $siid . '.' . $piid,
            'siid' => $siid,
            'piid' => $piid,
            'value' => $value,
        ]]);
        $this->SetValueSafe('LastResponse', $this->Encode($result));
        return $result;
    }

    public function SendRpc(string $method, $params)
    {
        $this->Login(false);
        $did = $this->GetDeviceID();
        if ($did === '') {
            throw new Exception('Keine Device-ID vorhanden. Bitte zuerst "Login + Geraete suchen" ausfuehren oder Device-ID eintragen.');
        }

        $id = time();
        $payload = [
            'did' => $did,
            'id' => $id,
            'data' => [
                'did' => $did,
                'id' => $id,
                'method' => $method,
                'params' => $params,
            ],
        ];

        return $this->ApiCall($this->CommandPath(), $payload, true);
    }

    private function PropertyRequest(string $name, int $siid, int $piid): array
    {
        return [
            'did' => $siid . '.' . $piid,
            'siid' => $siid,
            'piid' => $piid,
            'name' => $name,
        ];
    }

    private function Login(bool $force): void
    {
        $expires = $this->ReadAttributeInteger('TokenExpires');
        if (!$force && $this->ReadAttributeString('AccessToken') !== '' && $expires > time() + 120) {
            return;
        }

        $password = md5($this->ReadPropertyString('Password') . self::PASSWORD_SALT);

        $data = http_build_query([
            'grant_type' => 'password',
            'scope' => 'all',
            'platform' => 'IOS',
            'type' => 'account',
            'username' => $this->ReadPropertyString('Username'),
            'password' => $password,
            'country' => 'DE',
            'lang' => 'de',
        ]);

        $response = $this->HttpRequest($this->BaseUrl() . '/dreame-auth/oauth/token', $data, true, false);
        if (!is_array($response) || (!isset($response['access_token']) && !isset($response['data']['access_token']))) {
            $this->SetStatus(201);
            throw new Exception('MOVAhome Login fehlgeschlagen: ' . $this->Encode($response));
        }

        $token = $response['access_token'] ?? $response['data']['access_token'];
        $refresh = $response['refresh_token'] ?? ($response['data']['refresh_token'] ?? '');
        $expiresIn = (int)($response['expires_in'] ?? ($response['data']['expires_in'] ?? 3600));

        $this->WriteAttributeString('AccessToken', (string)$token);
        $this->WriteAttributeString('RefreshToken', (string)$refresh);
        $this->WriteAttributeInteger('TokenExpires', time() + $expiresIn);
        $this->SetStatus(IS_ACTIVE);
        $this->Log('Login OK, Token gueltig bis ' . date('Y-m-d H:i:s', time() + $expiresIn));
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
            'Tenant-Id: ' . $this->ReadPropertyString('TenantId'),
            'Authorization: ' . $this->AuthBasicHeader(),
        ];

        if ($json) {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if ($auth) {
            $token = $this->ReadAttributeString('AccessToken');
            if ($token !== '') {
                $headers[] = 'Dreame-Auth: ' . $token;
            }
        }

        $this->Log('HTTP POST ' . $url . ' DATA=' . $this->MaskPayloadForLog($data));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $data ?? '',
            CURLOPT_ENCODING => '',
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
        if (is_array($decoded)) {
            $decoded['_http_code'] = $code;
            return $decoded;
        }
        return ['http_code' => $code, 'raw' => $body];
    }

    private function AuthBasicHeader(): string
    {
        $configured = trim($this->ReadPropertyString('AuthBasic'));
        if ($configured !== '') {
            return stripos($configured, 'Basic ') === 0 ? $configured : 'Basic ' . $configured;
        }

        $clientId = 'mova_' . 'app';
        $clientSecret = 'V7Ko' . 'ChLW' . '8vHA' . 'CqGb';
        return 'Basic ' . base64_encode($clientId . ':' . $clientSecret);
    }

    private function CommandPath(): string
    {
        $prefix = self::DEFAULT_IOT_PREFIX;
        $raw = $this->ReadAttributeString('DeviceRaw');
        if ($raw !== '') {
            $device = json_decode($raw, true);
            $bindDomain = (string)($device['bindDomain'] ?? '');
            if (preg_match('/^([0-9]+)/', $bindDomain, $matches)) {
                $prefix = $matches[1];
            }
        }

        return '/dreame-iot-com-' . $prefix . '/device/sendCommand';
    }

    private function MaskPayloadForLog(?string $data): string
    {
        if ($data === null || $data === '') {
            return '';
        }

        return preg_replace('/(password=)[^&]*/', '$1***', $data) ?? $data;
    }

    private function NowMilliseconds(): int
    {
        return (int)floor(microtime(true) * 1000);
    }

    private function HandleException(string $context, Exception $e): void
    {
        $this->SetStatus(201);
        $message = $context . ' fehlgeschlagen: ' . $e->getMessage();
        $this->SetValueSafe('LastResponse', $message);
        $this->Log($message);
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

        $last = $this->ReadAttributeString('LastDeviceID');
        if ($last !== '') {
            return $last;
        }

        $raw = $this->ReadAttributeString('DeviceRaw');
        if ($raw !== '') {
            $data = json_decode($raw, true);
            return (string)($data['did'] ?? $data['deviceId'] ?? '');
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
            if (isset($value['did']) || isset($value['deviceId'])) {
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
        $items = $this->ExtractResultItems($result);
        $statusParts = [];

        foreach ($items as $item) {
            if (!is_array($item) || !array_key_exists('value', $item)) {
                continue;
            }

            $siid = (int)($item['siid'] ?? 0);
            $piid = (int)($item['piid'] ?? 0);
            $did = (string)($item['did'] ?? ($siid . '.' . $piid));
            $value = $item['value'];

            if ($did === '2.1') {
                $this->SetValueSafe('StateCode', (int)$value);
                $statusParts[] = self::STATE_NAMES[(int)$value] ?? ('State ' . $value);
            } elseif ($did === '2.2') {
                $this->SetValueSafe('ErrorCode', (int)$value);
                $this->SetValueSafe('ErrorText', self::ERROR_NAMES[(int)$value] ?? ('Fehler ' . $value));
            } elseif ($did === '3.1') {
                $this->SetValueSafe('Battery', (int)$value);
            } elseif ($did === '3.2') {
                $this->SetValueSafe('ChargingStatus', (int)$value);
                $statusParts[] = self::CHARGING_NAMES[(int)$value] ?? ('Ladestatus ' . $value);
            } elseif ($did === '4.2') {
                $this->SetValueSafe('CleaningTime', (int)$value);
            } elseif ($did === '4.3') {
                $this->SetValueSafe('CleanedArea', (int)$value);
            } elseif ($did === '4.4') {
                $this->SetValueSafe('SuctionLevel', (int)$value);
            } elseif ($did === '4.5') {
                $this->SetValueSafe('WaterVolume', (int)$value);
            } elseif ($did === '4.7') {
                $this->SetValueSafe('TaskStatus', (int)$value);
                $statusParts[] = self::TASK_NAMES[(int)$value] ?? ('Task ' . $value);
            } elseif ($did === '4.23') {
                $this->SetValueSafe('CleaningMode', (int)$value);
            }
        }

        if ($statusParts !== []) {
            $this->SetValueSafe('StatusText', implode(' / ', array_unique($statusParts)));
        }
    }

    private function ExtractResultItems($result): array
    {
        if (!is_array($result)) {
            return [];
        }

        $candidates = [
            $result['data']['result'] ?? null,
            $result['data']['data']['result'] ?? null,
            $result['result'] ?? null,
            $result['data'] ?? null,
            $result,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate) && $this->IsList($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function IsList(array $value): bool
    {
        $expected = 0;
        foreach (array_keys($value) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }
        return true;
    }

    private function RegisterProfileIntegerEx(string $name, string $icon, string $prefix, string $suffix, array $associations): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }

        IPS_SetVariableProfileIcon($name, $icon);
        IPS_SetVariableProfileText($name, $prefix, $suffix);
        foreach ($associations as $association) {
            IPS_SetVariableProfileAssociation($name, $association[0], $association[1], $association[2], $association[3]);
        }
    }

    private function SetValueSafe(string $ident, $value): void
    {
        $id = @$this->GetIDForIdent($ident);
        if ($id !== false) {
            SetValue($id, $value);
        }
    }

    private function Encode($value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function Log(string $message): void
    {
        if ($this->ReadPropertyBoolean('Debug')) {
            IPS_LogMessage('MOVA Vacuum', '[' . $this->InstanceID . '] ' . $message);
        }
        $this->SendDebug('MOVA', $message, 0);
    }
}
