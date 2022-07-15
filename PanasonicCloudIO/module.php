<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudIO extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    private static $base_url = 'https://accsmart.panasonic.com';

    private static $auth_endpoint = '/auth/login/';
    private static $group_endpoint = '/device/group';

    // +guid
    private static $device_status_endpoint = '/deviceStatus/';
    private static $device_status_now_endpoint = '/deviceStatus/now/';

    private static $device_control_endpoint = '/deviceStatus/control//';

    private static $x_app_type = 1;
    private static $x_app_version = '1.15.1';
    private static $user_agent = 'G-RAC';

    private static $login_interval = 10800000;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->RegisterAttributeString('AccessToken', '');

        $this->InstallVarProfiles(false);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $username = $this->ReadPropertyString('username');
        if ($username == '') {
            $this->SendDebug(__FUNCTION__, '"username" is needed', 0);
            $r[] = $this->Translate('Username must be specified');
        }
        $password = $this->ReadPropertyString('password');
        if ($password == '') {
            $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
            $r[] = $this->Translate('Password must be specified');
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vops = 0;

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Panasonic ComfortCloud I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Access data',
            'items'   => [
                [
                    'name'    => 'username',
                    'type'    => 'ValidationTextBox',
                    'caption' => 'User-ID (email)'
                ],
                [
                    'name'    => 'password',
                    'type'    => 'PasswordTextBox',
                    'caption' => 'Password'
                ],
            ],
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccount", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded ' => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                [
                    'type'    => 'Button',
                    'caption' => 'Clear Token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
            ],
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'ClearToken':
                $this->ClearToken();
                break;
            case 'TestAccount':
                $this->TestAccount();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function ClearToken()
    {
        $jtoken = json_decode($this->GetBuffer('AccessToken'), true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->WriteAttributeString('AccessToken', '');
    }

    protected function SendData($data)
    {
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode(['DataID' => '{FE8D32D1-6A63-D55B-FC77-8C34A637A5E0}', 'Buffer' => $data]));
    }

    public function ForwardData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetGroups':
                    $ret = $this->GetGroups();
                    break;
                case 'GetDeviceStatus':
                    $ret = $this->GetDeviceStatus($jdata['Guid'], false);
                    break;
                case 'GetDeviceStatusNow':
                    $ret = $this->GetDeviceStatus($jdata['Guid'], true);
                    break;
                case 'ControlDevice':
                    $ret = $this->ControlDevice($jdata['Guid'], $jdata['Parameters']);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
                }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function do_HttpRequest($endpoint, $postfields, $params, $header_add)
    {
        $url = self::$base_url . $endpoint;

        if ($params != '') {
            $this->SendDebug(__FUNCTION__, 'params=' . print_r($params, true), 0);
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }

        $header_base = [
            'Accept'            => 'application/json; charset=utf-8',
            'Content-Type'      => 'application/json; charset=utf-8',
            'User-Agent'        => self::$user_agent,
            'X-APP-TYPE'        => self::$x_app_type,
            'X-APP-VERSION'     => self::$x_app_version,
        ];
        if ($header_add != '') {
            foreach ($header_add as $key => $val) {
                $header_base[$key] = $val;
            }
        }
        $header = [];
        foreach ($header_base as $key => $val) {
            $header[] = $key . ': ' . $val;
        }

        $mode = $postfields != '' ? 'post' : 'get';
        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ', url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        if ($postfields != '') {
            $this->SendDebug(__FUNCTION__, '... postfields=' . json_encode($postfields), 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if ($postfields != '') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            if ($cdata == '' || ctype_print($cdata)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $cdata, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($cdata), 0);
            }
        }
        if ($statuscode == 0) {
            if ($cdata == 'Token expires') {
                $this->WriteAttributeString('AccessToken', '');
                $statuscode = self::$IS_UNAUTHORIZED;
            }
        }
        if ($statuscode == 0) {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }
        return $jdata;
    }

    private function GetAccessToken()
    {
        $data = $this->ReadAttributeString('AccessToken');
        if ($data != '') {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expireѕ = isset($jtoken['expireѕ']) ? $jtoken['expireѕ'] : 0;
            if ($access_token != '') {
                $this->SendDebug(__FUNCTION__, 'access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expireѕ), 0);
                return $access_token;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
        }

        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');
        $postfields = [
            'language' => 0,
            'loginId'  => $username,
            'password' => $password,
        ];
        $jdata = $this->do_HttpRequest(self::$auth_endpoint, $postfields, '', '');
        if ($jdata == false) {
            $this->WriteAttributeString('AccessToken', '');
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $access_token = $this->GetArrayElem($jdata, 'uToken', '');
        $client_id = $this->GetArrayElem($jdata, 'clientId', '');
        $expireѕ = time() + self::$login_interval;
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expireѕ), 0);
        $jtoken = [
            'access_token' => $access_token,
            'client_id'    => $client_id,
            'expireѕ'      => $expireѕ,
        ];
        $this->WriteAttributeString('AccessToken', json_encode($jtoken));
        return $access_token;
    }

    private function TestAccount()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText() . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            $msg = $this->Translate('Invalid login-data at Panasonic Comfort Cloud') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $msg = $this->Translate('valid account-data') . PHP_EOL;
        $msg .= PHP_EOL;

        $groups = json_decode($this->GetGroups(), true);
        if (is_array($groups)) {
            foreach ($groups as $group) {
                $this->SendDebug(__FUNCTION__, 'group=' . print_r($group, true), 0);
                $groupName = $this->GetArrayElem($group, 'groupName', '');
                $msg .= $groupName . PHP_EOL;
                $devices = $this->GetArrayElem($group, 'deviceList', '');
                if (is_array($devices)) {
                    foreach ($devices as $device) {
                        $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                        $deviceName = $this->GetArrayElem($device, 'deviceName', '');
                        $deviceType = $this->GetArrayElem($device, 'deviceType', 0);
                        $deviceModule = $this->GetArrayElem($device, 'deviceModuleNumber', '');
                        $deviceGuid = $this->GetArrayElem($device, 'deviceGuid', '');
                        $msg .= ' - ' . $deviceName . ' (' . $deviceModule . ')' . PHP_EOL;
                    }
                }
            }
        }
        $this->PopupMessage($msg);
    }

    private function GetGroups()
    {
        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            return false;
        }

        $url = self::$group_endpoint;

        $header_add = [
            'X-User-Authorization' => $access_token,
        ];

        $jdata = $this->do_HttpRequest($url, '', '', $header_add);
        if ($jdata == false) {
            return false;
        }
        $groups = $this->GetArrayElem($jdata, 'groupList', '');
        $this->SendDebug(__FUNCTION__, 'groups=' . print_r($groups, true), 0);
        return json_encode($groups);
    }

    private function GetDeviceStatus(string $guid, bool $now)
    {
        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            return false;
        }

        $url = $now ? self::$device_status_now_endpoint : self::$device_status_endpoint;
        $url .= $guid;

        $header_add = [
            'X-User-Authorization' => $access_token,
        ];

        $jdata = $this->do_HttpRequest($url, '', '', $header_add);
        if ($jdata == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function ControlDevice(string $guid, string $parameters)
    {
        $access_token = $this->GetAccessToken();
        if ($access_token == false) {
            return false;
        }

        $url = self::$device_control_endpoint;

        $postfields = [
            'deviceGuid' => $guid,
            'parameters' => json_decode($parameters, true),
        ];

        $header_add = [
            'X-User-Authorization' => $access_token,
        ];

        $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add);
        if ($jdata == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }
}
