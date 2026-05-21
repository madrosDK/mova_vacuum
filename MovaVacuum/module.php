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
        15 => 'Zum Reinigen gerufen',
        16 => 'Selbstreparatur',
        17 => 'Faehrt zum Mopp einsetzen',
        18 => 'Faehrt zum Mopp entfernen',
        19 => 'Wasserstation Selbsttest',
        20 => 'Mopp wird gereinigt und Wasser aufgefuellt',
        21 => 'Reinigung pausiert',
        22 => 'Auto-Entleerung',
        23 => 'Fernsteuerung',
        24 => 'Intelligentes Laden',
        25 => 'Zweite Reinigung',
        26 => 'Folgt',
        27 => 'Spot-Reinigung',
        28 => 'Faehrt zur Staubentleerung',
        29 => 'Wartet auf Aufgaben',
        30 => 'Waschbrett wird gereinigt',
        31 => 'Faehrt zum Entleeren',
        32 => 'Entleert',
        33 => 'Wasserversorgung/Entleerung',
        34 => 'Entleerung',
        35 => 'Staubbeutel trocknet',
        36 => 'Staubbeutel-Trocknung pausiert',
        37 => 'Faehrt zur Zusatzreinigung',
        38 => 'Zusatzreinigung',
        95 => 'Haustiersuche pausiert',
        96 => 'Haustiersuche',
        97 => 'Shortcut laeuft',
        98 => 'Kameraueberwachung',
        99 => 'Kameraueberwachung pausiert',
        101 => 'Initiale Tiefenreinigung',
        102 => 'Initiale Tiefenreinigung pausiert',
        103 => 'Desinfiziert',
        104 => 'Desinfiziert und trocknet',
        105 => 'Moppwechsel',
        106 => 'Moppwechsel pausiert',
        107 => 'Bodenpflege',
        108 => 'Bodenpflege pausiert',
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
        $this->RegisterPropertyString('Model', '');

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);
        $this->RegisterAttributeString('DeviceRaw', '');
        $this->RegisterAttributeString('DiscoveredDevices', '[]');
        $this->RegisterAttributeString('LastDeviceID', '');
        $this->RegisterAttributeString('StatusMap', '{}');
        $this->RegisterAttributeInteger('StatusMapFetched', 0);

        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'MOVA_Refresh($_IPS[\'TARGET\']);');
    }


    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!is_array($form)) {
            return '{}';
        }

        if (isset($form['elements']) && is_array($form['elements'])) {
            $this->InjectDeviceOptions($form['elements']);
        }

        return json_encode($form, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterProfileIntegerText('MOVA.AreaM2', ' m²');
        $this->RegisterProfileIntegerText('MOVA.Minutes', ' min');
        $this->RegisterProfileIntegerText('MOVA.Maintenance', ' %');

        $this->RegisterVariableString('DeviceName', 'Geraet', '', 0);
        $this->RegisterVariableString('DeviceIDText', 'Device-ID', '', 1);
        $this->RegisterVariableString('DeviceModel', 'Modell', '', 2);
        $this->RegisterVariableString('Firmware', 'Firmware', '', 3);
        $this->RegisterVariableString('SerialNumber', 'Seriennummer', '', 4);
        $this->RegisterVariableString('MacAddress', 'MAC-Adresse', '', 5);
        $this->RegisterVariableString('ProductID', 'Produkt-ID', '', 6);
        $this->RegisterVariableString('BindDomain', 'Cloud-Bind-Domain', '', 7);
        $this->RegisterVariableString('FeatureCodes', 'Feature-Codes', '', 8);
        $this->RegisterVariableString('IconUrl', 'Icon-URL', '', 9);
        $this->RegisterVariableString('KeyDefineUrl', 'KeyDefine-URL', '', 10);

        $this->RegisterVariableBoolean('Online', 'Online', '~Switch', 11);
        $this->RegisterVariableInteger('Battery', 'Akku', '~Battery.100', 12);
        $this->RegisterVariableInteger('StateCode', 'Status-Code', '', 13);
        $this->RegisterVariableString('StatusText', 'Status', '', 14);

        $this->RegisterVariableString('VideoStatusText', 'Video/Kamera', '', 15);
        $this->RegisterVariableInteger('VideoStatusCode', 'Video-Status-Code', '', 16);
        $this->RegisterVariableString('VideoOperation', 'Video-Operation', '', 17);

        $this->RegisterVariableString('CloudStatus', 'Cloud-Status', '', 18);
        $this->RegisterVariableString('Vendor', 'Vendor', '', 19);
        $this->RegisterVariableString('SharedStatus', 'Shared-Status', '', 20);
        $this->RegisterVariableString('DeviceTimes', 'Geraetezeiten', '', 21);

        $this->RegisterVariableString('RawConfigData', 'getDeviceData Konfiguration', '', 40);
        $this->RegisterVariableString('LastResponse', 'Letzte Antwort', '', 50);

        $this->SyncSelectedDevice();

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
            case 'Refresh':
            case 'Update':
                $this->Update();
                break;

            default:
                throw new Exception('Invalid ident: ' . $Ident);
        }
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

            $records = $this->FindDevices($devices);
            $this->WriteAttributeString('DiscoveredDevices', $this->Encode($records));

            $found = $this->FindDevice($records);
            if ($found === null) {
                $this->Log('Kein passendes Geraet gefunden. LastResponse pruefen.');
                return false;
            }

            $selected = $this->ReadPropertyString('DeviceID');
            if ($selected !== '' || count($records) === 1) {
                $this->UseDevice($found);
            }

            $this->UpdateFormField('DeviceID', 'options', json_encode($this->BuildDeviceOptions()));
            if (count($records) === 1) {
                $this->UpdateFormField('DeviceID', 'value', (string)($found['did'] ?? $found['deviceId'] ?? ''));
            }

            $this->Log(count($records) . ' Geraet(e) gefunden. Auswahl im Feld "Gefundenes Geraet" pruefen und uebernehmen.');
            return true;
        } catch (Exception $e) {
            $this->HandleException('Login/Geraetesuche', $e);
            return false;
        }
    }
    public function Update()
    {
        try {
            $props = $this->DefaultPropertyRequests();
            $result = $this->GetProperties($props);
            $this->ParseProperties($result);

            $device = $this->GetSelectedDeviceFromCloud();
            if ($device !== null) {
                $this->ParseDeviceListStatus($device);
            }

            $this->SetValueSafe('LastResponse', $this->Encode([
                'properties' => $result,
                'device' => $device,
                'statusMapLoaded' => json_decode($this->ReadAttributeString('StatusMap'), true) !== [],
            ]));

            return true;
        } catch (Exception $e) {
            $this->HandleException('Status aktualisieren', $e);
            return false;
        }
    }




    public function ExplorerApiCall()
    {
        try {
            $this->Login(false);
            $payload = trim($this->ReadPropertyString('ExplorerPayload'));
            $data = $payload === '' ? [] : json_decode($payload, true);
            if ($data === null && $payload !== 'null') {
                throw new Exception('ExplorerPayload ist kein gueltiges JSON.');
            }
            if (is_array($data)) {
                $data = $this->ReplaceDidPlaceholders($data);
            }
            $result = $this->ApiCall($this->ReadPropertyString('ExplorerPath'), $data, true);
            $this->SetValueSafe('LastResponse', $this->Encode($result));
            return $result;
        } catch (Exception $e) {
            $this->HandleException('Explorer API', $e);
            return false;
        }
    }

    public function TestDefaultProperties()
    {
        try {
            $result = $this->GetProperties($this->DefaultPropertyRequests());
            $this->SetValueSafe('LastResponse', $this->Encode($result));
            $this->ParseProperties($result);
            return $result;
        } catch (Exception $e) {
            $this->HandleException('Default Properties testen', $e);
            return false;
        }
    }

    public function Refresh()
    {
        return $this->Update();
    }




    public function GetProperties(array $props)
    {
        $this->Login(false);
        $did = $this->GetDeviceID();
        if ($did === '') {
            throw new Exception('Keine Device-ID vorhanden. Bitte zuerst "Login + Geraete suchen" ausfuehren oder Device-ID eintragen.');
        }

        $model = [];
        foreach ($props as $prop) {
            $model[] = [
                'siid' => (int)($prop['siid'] ?? 0),
                'piid' => (int)($prop['piid'] ?? 0),
            ];
        }

        return $this->ApiCall('/dreame-user-iot/iotuserdata/getDeviceData', [
            'did' => $did,
            'model' => $model,
        ], true);
    }

    public function GetSelectedDeviceFromCloud(): ?array
    {
        $devices = $this->ApiCall('/dreame-user-iot/iotuserbind/device/listV2', [
            'sharedStatus' => 1,
            'current' => 1,
            'size' => 100,
            'lang' => 'de',
            'timestamp' => $this->NowMilliseconds(),
        ], true);

        $records = $this->FindDevices($devices);
        if ($records !== []) {
            $this->WriteAttributeString('DiscoveredDevices', $this->Encode($records));
        }

        return $this->FindDevice($records);
    }
    private function DefaultPropertyRequests(): array
    {
        return [
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
            $this->PropertyRequest('resume_clean', 4, 26),
            $this->PropertyRequest('clean_percent', 4, 27),

            $this->PropertyRequest('map_status', 6, 1),
            $this->PropertyRequest('map_id', 6, 2),

            $this->PropertyRequest('dust_collection', 7, 1),
            $this->PropertyRequest('auto_empty_status', 7, 2),

            $this->PropertyRequest('water_tank', 8, 1),
            $this->PropertyRequest('dirty_water_tank', 8, 2),

            $this->PropertyRequest('main_brush_left', 9, 2),
            $this->PropertyRequest('side_brush_left', 10, 2),
            $this->PropertyRequest('filter_left', 11, 1),

            $this->PropertyRequest('dock_state', 15, 1),
            $this->PropertyRequest('mop_wash_state', 15, 2),
            $this->PropertyRequest('drying_state', 15, 3),

            $this->PropertyRequest('sensor_dirty_left', 16, 1),
            $this->PropertyRequest('mop_pad_left', 18, 1),

            $this->PropertyRequest('camera_state', 20, 1),
            $this->PropertyRequest('pet_mode', 21, 1),
            $this->PropertyRequest('voice_volume', 22, 1),
            $this->PropertyRequest('carpet_boost', 23, 1),
            $this->PropertyRequest('auto_reclean', 24, 1),
            $this->PropertyRequest('smart_drying', 25, 1),
        ];
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

    private function NormalizeRpcParams($params, string $did)
    {
        if (!is_array($params)) {
            return $params;
        }

        if (isset($params['siid']) && (isset($params['piid']) || isset($params['aiid']))) {
            $params['did'] = $did;
        }

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = $this->NormalizeRpcParams($value, $did);
            }
        }

        return $params;
    }

    private function ReplaceDidPlaceholders(array $value): array
    {
        foreach ($value as $key => $item) {
            if ($key === 'did' && $item === '') {
                $value[$key] = $this->GetDeviceID();
            } elseif (is_array($item)) {
                $value[$key] = $this->ReplaceDidPlaceholders($item);
            }
        }
        return $value;
    }

    private function Login(bool $force): void
    {
        $expires = $this->ReadAttributeInteger('TokenExpires');
        if (!$force && $this->ReadAttributeString('AccessToken') !== '' && $expires > time() + 120) {
            return;
        }

        $rawPassword = $this->ReadPropertyString('Password');
        $password = $this->ReadPropertyBoolean('UseMD5Password') ? md5($rawPassword . self::PASSWORD_SALT) : $rawPassword;

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

        $response = $this->HttpRequest($this->BaseUrl() . '/dreame-auth/oauth/token', $data, false, false);
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $data ?? '',
            CURLOPT_ENCODING => '',

            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,

            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FRESH_CONNECT => true,
            CURLOPT_FORBID_REUSE => true,

            CURLOPT_PROXY => '',
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

    private function CommandUrl(): string
    {
        $raw = $this->ReadAttributeString('DeviceRaw');

        if ($raw !== '') {
            $device = json_decode($raw, true);
            $bind = $device['bindDomain'] ?? '';

            if ($bind !== '') {
                return 'https://' . $bind . '/device/sendCommand';
            }
        }

        return $this->BaseUrl() . '/dreame-iot-com-20000/device/sendCommand';
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
        $selected = $this->ReadPropertyString('DeviceID');
        $model = $this->ReadPropertyString('Model');
        $records = $this->FindDevices($response);

        if ($selected !== '') {
            foreach ($records as $item) {
                $did = (string)($item['did'] ?? $item['deviceId'] ?? '');
                if ($did === $selected) {
                    return $item;
                }
            }
        }

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

    private function FindDevices($response): array
    {
        $records = [];
        $seen = [];

        foreach ($this->Flatten($response) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $did = (string)($item['did'] ?? $item['deviceId'] ?? '');
            if ($did === '' || isset($seen[$did])) {
                continue;
            }

            $seen[$did] = true;
            $records[] = $item;
        }

        return $records;
    }

    private function UseDevice(array $device): void
    {
        $did = (string)($device['did'] ?? $device['deviceId'] ?? '');
        if ($did === '') {
            return;
        }

        $this->WriteAttributeString('DeviceRaw', $this->Encode($device));
        $this->WriteAttributeString('LastDeviceID', $did);
        $this->ParseDeviceListStatus($device, false);
        $this->Log('Aktives Geraet: ' . $this->DeviceCaption($device) . ' (' . $did . ')');
    }

    private function SyncSelectedDevice(): void
    {
        $selected = $this->ReadPropertyString('DeviceID');
        if ($selected === '') {
            return;
        }

        foreach ($this->GetDiscoveredDevices() as $device) {
            $did = (string)($device['did'] ?? $device['deviceId'] ?? '');
            if ($did === $selected) {
                $this->UseDevice($device);
                return;
            }
        }

        $this->WriteAttributeString('LastDeviceID', $selected);
        $this->SetValueSafe('DeviceIDText', $selected);
    }

    private function GetDiscoveredDevices(): array
    {
        $devices = json_decode($this->ReadAttributeString('DiscoveredDevices'), true);
        return is_array($devices) ? $devices : [];
    }

    private function InjectDeviceOptions(array &$elements): void
    {
        foreach ($elements as &$element) {
            if (($element['name'] ?? '') === 'DeviceID') {
                $element['type'] = 'Select';
                $element['caption'] = 'Gefundenes Geraet';
                $element['options'] = $this->BuildDeviceOptions();
                continue;
            }

            if (isset($element['items']) && is_array($element['items'])) {
                $this->InjectDeviceOptions($element['items']);
            }
        }
    }

    private function BuildDeviceOptions(): array
    {
        $options = [
            [
                'caption' => 'Bitte Geraet suchen und auswaehlen',
                'value' => '',
            ],
        ];

        $current = $this->ReadPropertyString('DeviceID');
        $hasCurrent = $current === '';
        foreach ($this->GetDiscoveredDevices() as $device) {
            $did = (string)($device['did'] ?? $device['deviceId'] ?? '');
            if ($did === '') {
                continue;
            }

            $hasCurrent = $hasCurrent || $did === $current;
            $options[] = [
                'caption' => $this->DeviceCaption($device) . ' - ' . $did,
                'value' => $did,
            ];
        }

        if (!$hasCurrent) {
            $options[] = [
                'caption' => 'Aktuell eingetragen - ' . $current,
                'value' => $current,
            ];
        }

        return $options;
    }

    private function DeviceCaption(array $device): string
    {
        $did = (string)($device['did'] ?? $device['deviceId'] ?? '');
        $model = (string)($device['model'] ?? ($device['deviceInfo']['model'] ?? ''));
        $name = (string)($device['customName'] ?? $device['name'] ?? ($device['deviceInfo']['displayName'] ?? ''));

        if ($name !== '' && $model !== '') {
            return $name . ' (' . $model . ')';
        }
        if ($name !== '') {
            return $name;
        }
        if ($model !== '') {
            return $model;
        }
        return $did !== '' ? $did : 'Unbekanntes Geraet';
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
        if (!is_array($result)) {
            return;
        }

        $data = $result['data'] ?? null;
        if (is_array($data)) {
            $config = [];
            foreach ($data as $key => $value) {
                if (strpos((string)$key, 'prop.s_') === 0) {
                    $decoded = is_string($value) ? json_decode($value, true) : null;
                    $config[$key] = $decoded !== null ? $decoded : $value;
                }
            }

            if ($config !== []) {
                $this->SetValueSafe('RawConfigData', $this->Encode($config));
            }
        }

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
                $statusParts[] = $this->TranslateStatus((int)$value);
            } elseif ($did === '3.1') {
                $this->SetValueSafe('Battery', (int)$value);
            }
        }

        if ($statusParts !== []) {
            $this->SetValueSafe('StatusText', implode(' / ', array_unique($statusParts)));
        }
    }
    private function ParseDeviceListStatus(array $device, bool $writeRaw = true): void
    {
        if ($writeRaw) {
            $this->WriteAttributeString('DeviceRaw', $this->Encode($device));
        }

        $did = (string)($device['did'] ?? $device['deviceId'] ?? '');
        $deviceInfo = is_array($device['deviceInfo'] ?? null) ? $device['deviceInfo'] : [];

        $this->SetValueSafe('DeviceName', $this->DeviceCaption($device));
        $this->SetValueSafe('DeviceIDText', $did);
        $this->SetValueSafe('DeviceModel', (string)($device['model'] ?? ($deviceInfo['model'] ?? '')));
        $this->SetValueSafe('Firmware', (string)($device['ver'] ?? ($device['firmwareVersion'] ?? '')));
        $this->SetValueSafe('SerialNumber', (string)($device['sn'] ?? ($device['serialNumber'] ?? '')));
        $this->SetValueSafe('MacAddress', (string)($device['mac'] ?? ($device['macAddress'] ?? '')));
        $this->SetValueSafe('ProductID', (string)($deviceInfo['productId'] ?? ($device['productId'] ?? ($device['product_id'] ?? ''))));
        $this->SetValueSafe('BindDomain', (string)($device['bindDomain'] ?? ''));
        $this->SetValueSafe('Vendor', (string)($device['vendor'] ?? ''));
        $this->SetValueSafe('CloudStatus', (string)($deviceInfo['status'] ?? ''));
        $this->SetValueSafe('SharedStatus', (string)($device['sharedStatus'] ?? ''));

        $imageUrl = (string)($deviceInfo['mainImage']['imageUrl'] ?? $deviceInfo['icon']['imageUrl'] ?? $device['icon'] ?? $device['image'] ?? '');
        $this->SetValueSafe('IconUrl', $imageUrl);

        $keyDefineUrl = (string)($device['keyDefine']['url'] ?? $device['keyDefineUrl'] ?? '');
        $this->SetValueSafe('KeyDefineUrl', $keyDefineUrl);
        if ($keyDefineUrl !== '') {
            $this->EnsureStatusMap($keyDefineUrl);
        }

        $features = $device['featureCodes'] ?? $device['featureCode'] ?? $deviceInfo['feature'] ?? $device['feature'] ?? null;
        if ($features !== null) {
            $this->SetValueSafe('FeatureCodes', is_array($features) ? implode(', ', array_map('strval', $features)) : (string)$features);
        }

        $this->SetValueSafe('Online', (bool)($device['online'] ?? false));

        if (array_key_exists('battery', $device)) {
            $this->SetValueSafe('Battery', (int)$device['battery']);
        }

        $status = (int)($device['latestStatus'] ?? -1);
        if ($status >= 0) {
            $this->SetValueSafe('StateCode', $status);
            $this->SetValueSafe('StatusText', $this->TranslateStatus($status));
        } elseif (array_key_exists('online', $device)) {
            $this->SetValueSafe('StatusText', $device['online'] ? 'Online' : 'Offline');
        }

        $this->ParseVideoStatus($device['videoStatus'] ?? '');

        $times = [
            'updateTime' => $device['updateTime'] ?? '',
            'devBindTime' => $this->FormatTimestampMs($device['devBindTime'] ?? null),
            'createTime' => $this->FormatTimestampMs($device['createTime'] ?? null),
            'releaseAt' => $this->FormatTimestampMs($deviceInfo['releaseAt'] ?? null),
        ];
        $this->SetValueSafe('DeviceTimes', $this->Encode($times));

        if (array_key_exists('online', $device) && !$device['online']) {
            $this->SetValueSafe('ErrorText', 'Offline');
        } else {
            $this->SetValueSafe('ErrorText', 'Kein Fehler');
        }
    }


    

    private function TranslateStatus(int $status): string
    {
        $map = json_decode($this->ReadAttributeString('StatusMap'), true);
        if (is_array($map) && isset($map[(string)$status]) && $map[(string)$status] !== '') {
            return (string)$map[(string)$status];
        }

        return self::STATE_NAMES[$status] ?? ('Unbekannt (' . $status . ')');
    }

    private function EnsureStatusMap(string $url): void
    {
        $cached = json_decode($this->ReadAttributeString('StatusMap'), true);
        $lastFetch = $this->ReadAttributeInteger('StatusMapFetched');

        if (is_array($cached) && $cached !== [] && $lastFetch > time() - 86400) {
            return;
        }

        try {
            $json = $this->HttpGet($url);
            $decoded = json_decode($json, true);

            $map = [];
            $this->CollectStatusMap($decoded, $map);

            if ($map !== []) {
                $this->WriteAttributeString('StatusMap', $this->Encode($map));
                $this->WriteAttributeInteger('StatusMapFetched', time());
                $this->Log('Statusmap geladen: ' . count($map) . ' Eintraege.');
            }
        } catch (Exception $e) {
            $this->Log('Statusmap konnte nicht geladen werden: ' . $e->getMessage());
        }
    }

    private function CollectStatusMap($value, array &$map): void
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $code = null;
                $text = null;

                foreach (['key', 'value', 'code', 'status', 'id'] as $codeKey) {
                    if (isset($item[$codeKey]) && is_numeric($item[$codeKey])) {
                        $code = (string)((int)$item[$codeKey]);
                        break;
                    }
                }

                foreach (['de', 'de_DE', 'en', 'en_US', 'value', 'text', 'name', 'desc', 'displayName'] as $textKey) {
                    if (isset($item[$textKey]) && is_string($item[$textKey]) && $item[$textKey] !== '') {
                        $text = $item[$textKey];
                        break;
                    }
                }

                if ($code !== null && $text !== null) {
                    $map[$code] = $text;
                }

                $this->CollectStatusMap($item, $map);
            } elseif (is_numeric($key) && is_string($item) && $item !== '') {
                $map[(string)((int)$key)] = $item;
            }
        }
    }

    private function ParseVideoStatus($videoStatus): void
    {
        $this->SetValueSafe('VideoStatusText', 'Unbekannt');

        if (!is_string($videoStatus) || $videoStatus === '') {
            return;
        }

        $decoded = json_decode($videoStatus, true);
        if (!is_array($decoded)) {
            $this->SetValueSafe('VideoStatusText', $videoStatus);
            return;
        }

        $status = (int)($decoded['status'] ?? -1);
        $result = (int)($decoded['result'] ?? -1);
        $operation = (string)($decoded['operation'] ?? '');
        $operType = (string)($decoded['operType'] ?? '');

        $this->SetValueSafe('VideoStatusCode', $status);
        $this->SetValueSafe('VideoOperation', trim($operation . ' / ' . $operType, ' /'));

        if ($status === 0 && $operType === 'end') {
            $text = 'Beendet';
        } elseif ($status === 1) {
            $text = 'Aktiv';
        } elseif ($result === 0) {
            $text = 'OK';
        } else {
            $text = 'Status ' . $status . ', Result ' . $result;
        }

        if ($operation !== '') {
            $text .= ' (' . $operation . ')';
        }

        $this->SetValueSafe('VideoStatusText', $text);
    }

    private function HttpGet(string $url): string
    {
        $this->Log('HTTP GET ' . $url);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/plain,*/*',
                'User-Agent: Mova_Smarthome/1.5.59 (iPhone; iOS 16.0; Scale/3.00)',
            ],
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('HTTP GET Fehler: ' . $err);
        }

        if ((int)$code >= 400) {
            throw new Exception('HTTP GET Status ' . $code);
        }

        return (string)$body;
    }

    private function FormatTimestampMs($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $ts = (int)$value;
        if ($ts > 100000000000) {
            $ts = (int)floor($ts / 1000);
        }

        if ($ts <= 0) {
            return '';
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function GetIDForIdentSafe(string $ident)
    {
        return @$this->GetIDForIdent($ident);
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

    private function RegisterProfileIntegerText(string $name, string $suffix): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, VARIABLETYPE_INTEGER);
        }
        IPS_SetVariableProfileText($name, '', $suffix);
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
