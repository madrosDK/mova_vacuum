<?php

declare(strict_types=1);

class MovaVacuum extends IPSModule
{
    private const TIMER_UPDATE = 'UpdateTimer';
    private const PASSWORD_SALT = 'RAylYC%fmSKp7%Tq';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Region', 'eu');
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('UpdateInterval', 60);
        $this->RegisterPropertyBoolean('Debug', true);

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);

        $this->RegisterTimer(self::TIMER_UPDATE, 0, 'MOVA_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->RegisterVariableInteger('Battery', 'Akku', '~Battery.100', 1);
        $this->RegisterVariableInteger('StateCode', 'Status', '', 2);
        $this->RegisterVariableInteger('CleanedArea', 'Fläche', '~Intensity.100', 3);
        $this->RegisterVariableInteger('CleaningTime', 'Zeit', '', 4);
        $this->RegisterVariableInteger('SuctionLevel', 'Saugleistung', '', 5);
        $this->RegisterVariableInteger('WaterVolume', 'Wasser', '', 6);

        $this->RegisterVariableString('LastResponse', 'Letzte Antwort', '', 100);

        $interval = $this->ReadPropertyInteger('UpdateInterval');
        $this->SetTimerInterval(self::TIMER_UPDATE, $interval * 1000);
    }

    // 🔥 LOGIN
    private function Login(bool $force = false)
    {
        if (!$force && $this->ReadAttributeString('AccessToken') !== '') {
            return;
        }

        $data = http_build_query([
            'grant_type' => 'password',
            'username' => $this->ReadPropertyString('Username'),
            'password' => md5($this->ReadPropertyString('Password') . self::PASSWORD_SALT),
            'scope' => 'all'
        ]);

        $result = $this->HttpRequest(
            $this->BaseUrl() . '/dreame-auth/oauth/token',
            $data,
            false,
            false
        );

        if (!isset($result['access_token'])) {
            throw new Exception('Login fehlgeschlagen');
        }

        $this->WriteAttributeString('AccessToken', $result['access_token']);
    }

    // 🔥 UPDATE
    public function Update()
    {
        try {
            $this->Login();

            $result = $this->GetDeviceData();

            $this->Parse($result);
            $this->SetValue('LastResponse', json_encode($result));

        } catch (Exception $e) {
            IPS_LogMessage("MOVA", $e->getMessage());
        }
    }

    // 🔥 CLOUD TEST
    public function TestCloudStatus()
    {
        $result = $this->GetDeviceData();
        $this->SetValue('LastResponse', json_encode($result));
        return $result;
    }

    // 🔥 DEVICE DATA
    private function GetDeviceData()
    {
        return $this->ApiCall('/dreame-user-iot/iotuserdata/getDeviceData', [
            'did' => $this->ReadPropertyString('DeviceID'),
            'model' => [
                ['siid'=>2,'piid'=>1],
                ['siid'=>2,'piid'=>2],
                ['siid'=>3,'piid'=>1],
                ['siid'=>3,'piid'=>2],
                ['siid'=>4,'piid'=>2],
                ['siid'=>4,'piid'=>3],
                ['siid'=>4,'piid'=>4],
                ['siid'=>4,'piid'=>5],
            ]
        ], true);
    }

    // 🔥 PARSE
    private function Parse($data)
    {
        if (!isset($data['data']['result'])) return;

        foreach ($data['data']['result'] as $item) {

            $did = $item['siid'] . '.' . $item['piid'];
            $val = $item['value'];

            switch ($did) {
                case '3.1':
                    $this->SetValue('Battery', $val);
                    break;

                case '2.1':
                    $this->SetValue('StateCode', $val);
                    break;

                case '4.2':
                    $this->SetValue('CleaningTime', $val);
                    break;

                case '4.3':
                    $this->SetValue('CleanedArea', $val);
                    break;

                case '4.4':
                    $this->SetValue('SuctionLevel', $val);
                    break;

                case '4.5':
                    $this->SetValue('WaterVolume', $val);
                    break;
            }
        }
    }

    // 🔥 API CALL
    private function ApiCall(string $path, $payload, bool $auth)
    {
        return $this->HttpRequest(
            $this->BaseUrl() . $path,
            json_encode($payload),
            $auth,
            true
        );
    }

    // 🔥 HTTP
    private function HttpRequest(string $url, $data, bool $auth, bool $json)
    {
        $headers = [
            'User-Agent: Mova',
            'Content-Type: ' . ($json ? 'application/json' : 'application/x-www-form-urlencoded')
        ];

        if ($auth) {
            $headers[] = 'Dreame-Auth: ' . $this->ReadAttributeString('AccessToken');
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => $data,
        ]);

        $result = curl_exec($ch);

        if ($result === false) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($result, true);
    }

    private function BaseUrl()
    {
        return 'https://' . $this->ReadPropertyString('Region') . '.iot.mova-tech.com:13267';
    }
}
