<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudIO extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    // Panasonic Comfort-Cloud
    private static $API_CC = 1;

    private static $base_url_cc = 'https://accsmart.panasonic.com';

    private static $base_auth_url_cc = 'https://authglb.digital.panasonic.com';
    private static $auth_endpoint_cc = '/authorize';

    private static $login_callback_cc = '/login/callback';

    private static $token_endpoint_cc = '/oauth/token';

    private static $auth_v2_login_endpoint_cc = '/auth/v2/login';

    private static $group_endpoint_cc = '/device/group';

    // +guid
    private static $device_status_endpoint_cc = '/deviceStatus/';
    private static $device_status_now_endpoint_cc = '/deviceStatus/now/';

    private static $device_control_endpoint_cc = '/deviceStatus/control/';

    private static $device_history_endpoint_cc = '/deviceHistoryData';

    private static $app_client_id_cc = 'Xmy6xIYIitMxngjB2rHvlm6HSDNnaMJx';
    private static $auth0_client_cc = 'eyJuYW1lIjoiQXV0aDAuQW5kcm9pZCIsImVudiI6eyJhbmRyb2lkIjoiMzAifSwidmVyc2lvbiI6IjIuOS4zIn0=';

    private static $x_app_type_cc = '1';
    private static $x_app_version_cc = '1.21.0';
    private static $x_app_name_cc = 'Comfort Cloud';
    private static $user_agent_cc = 'G-RAC';

    private static $user_agent_auth_cc = 'okhttp/4.10.0';
    private static $user_agent_form_cc = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/113.0.0.0 Mobile Safari/537.36';

    private static $login_interval = 125 * 24 * 60 * 60; // 125 Tage

    // Panasonic Aquarea-Smart-Cloud
    private static $API_ASC = 2;

    private static $auth0_client_asc = 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMjMuMiJ9';

    private static $base_url_asc = 'https://aquarea-smart.panasonic.com';

    private static $auth_endpoint_asc = '/remote/v1/api/auth/login';

    private static $devices_endpoint_asc = '/remote/v1/api/devices';
    private static $device_endpoint_asc = '/remote/v1/api/devices/'; // + device_id

    // device_id ermitteln
    private static $contract_endpoint_asc = '/remote/contract';

    private static $device_consumption_endpoint_asc = '/remote/v1/api/consumption/'; // + device_id
    private static $status_referer_asc = '/remote/a2wStatusDisplay';

    private static $user_agent_asc = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:74.0) Gecko/20100101 Firefox/74.0';

    private static $token_expiration_interval = 23 * 60 * 60; // 23 Stunden

    // Modul
    private static $semaphoreTM = 5 * 1000;

    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('username', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('inactivity_logout_ASC', 30);

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->RegisterAttributeString('AccessToken_CC', '');
        $this->RegisterAttributeString('AccessToken_ASC', '');

        $this->SetBuffer('LastApiCall_ASC', 0);

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

        $vops = 1;

        $vpos = 1000;
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        $this->MaintainMedia('ApiCallStats', $this->Translate('API call statistics'), MEDIATYPE_DOCUMENT, '.txt', false, $vpos++, $collectApiCallStats);

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

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'inactivity_logout_ASC',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Inactivity logout from Aquera Smart Cloud after',
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
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

        $items = [
            $this->GetInstallVarProfilesFormItem(),
            [
                'type'    => 'Button',
                'caption' => 'Clear token',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
            ],
        ];
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
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
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $jtoken = json_decode($this->ReadAttributeString('AccessToken_CC'), true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->WriteAttributeString('AccessToken_CC', '');

        IPS_SemaphoreLeave($this->SemaphoreID);
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

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

        $ret = '';
        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetGroups':
                    $ret = $this->GetGroups();
                    break;
                case 'GetDevices':
                    if ($jdata['Type'] == self::$DEVICE_TYPE_HEAT_PUMP) {
                        $ret = $this->GetDevices_ASC();
                    }
                    break;
                case 'GetDeviceStatus':
                    if ($jdata['Type'] == self::$DEVICE_TYPE_HEAT_PUMP) {
                        $ret = $this->GetDeviceStatus_ASC($jdata['DeviceID']);
                    } else {
                        $now = isset($jdata['Now']) ? (bool) $jdata['Now'] : false;
                        $ret = $this->GetDeviceStatus($jdata['Guid'], $now);
                    }
                    break;
                case 'MapGuidToId':
                    if ($jdata['Type'] == self::$DEVICE_TYPE_HEAT_PUMP) {
                        $ret = $this->MapGuidToId_ASC($jdata['Guid']);
                    }
                    break;
                case 'ControlDevice':
                    if ($jdata['Type'] == self::$DEVICE_TYPE_HEAT_PUMP) {
                        $ret = $this->ControlDevice_ASC($jdata['DeviceID'], $jdata['Parameters']);
                    } else {
                        $ret = $this->ControlDevice($jdata['Guid'], $jdata['Parameters']);
                    }
                    break;
                case 'GetDeviceHistory':
                    if ($jdata['Type'] == self::$DEVICE_TYPE_HEAT_PUMP) {
                        $ret = $this->GetDeviceHistory_ASC($jdata['DeviceID'], (int) $jdata['DataMode'], (int) $jdata['Timestamp']);
                    } else {
                        $ret = $this->GetDeviceHistory($jdata['Guid'], (int) $jdata['DataMode'], (int) $jdata['Timestamp']);
                    }
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

    private function do_HttpRequest($endpoint, $postfields, $params, $header_add, $api)
    {
        if ($api == self::$API_CC) {
            $url = self::$base_url_cc . $endpoint;
        } else {
            $url = self::$base_url_asc . $endpoint;
        }
        if ($params != '') {
            $url .= '?' . http_build_query($params);
        }

        if ($api == self::$API_CC) {
            $header_base = [
                'Accept'          => 'application/json; charset=utf-8',
                'Content-Type'    => 'application/json; charset=utf-8',
                'User-Agent'      => self::$user_agent_cc,
                'X-APP-TYPE'      => self::$x_app_type_cc,
                'X-APP-VERSION'   => self::$x_app_version_cc,
                'X-APP-NAME'      => self::$x_app_name_cc,
                'X-APP-TIMESTAMP' => date('Y-m-d H:i:s', time()),
                'X-CFC-API-KEY'   => $this->random_string_hex(128),
            ];
        } else {
            $header_base = [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent'   => self::$user_agent_asc,
            ];
        }
        if (is_array($header_add)) {
            $header_base = array_merge($header_base, $header_add);
        }
        $header = $this->build_header($header_base);

        $mode = $postfields != '' ? 'POST' : 'GET';
        $this->SendDebug(__FUNCTION__, 'url=' . $url . ', mode=' . $mode, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);
        if ($postfields != '') {
            if (isset($header_base['Content-Type']) && preg_match('#application/json#', $header_base['Content-Type'])) {
                $postdata = json_encode($postfields);
            } else {
                $postdata = http_build_query($postfields);
            }
            $this->SendDebug(__FUNCTION__, '... postdata=' . $postdata, 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
                if ($api == self::$API_CC) {
                    $this->WriteAttributeString('AccessToken_CC', '');
                } else {
                    $this->WriteAttributeString('AccessToken_ASC', '');
                }
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
                if ($api == self::$API_CC) {
                    $this->WriteAttributeString('AccessToken_CC', '');
                } else {
                    $this->WriteAttributeString('AccessToken_ASC', '');
                }
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($api == self::$API_CC) {
            if ($statuscode == 0) {
                if ($body == 'Token expires') {
                    $this->WriteAttributeString('AccessToken_CC', '');
                    $statuscode = self::$IS_UNAUTHORIZED;
                }
            }
            if ($statuscode == 0) {
                $jbody = json_decode($body, true);
                if ($jbody == '') {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                }
            }
        } else {
            $jbody = [];
            if ($endpoint == self::$contract_endpoint_asc) {
                if (preg_match_all('|Set-Cookie: selectedDeviceId=(.*);|Ui', $head, $matches) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "selectedDeviceId" in header';
                } else {
                    $jbody = [
                        'DeviceId' => $matches[1][0],
                    ];
                }
            } else {
                if ($statuscode == 0) {
                    $jbody = @json_decode($body, true);
                    if ($jbody == false) {
                        $statuscode = self::$IS_INVALIDDATA;
                        $err = 'malformed response';
                    }
                }
                $this->SendDebug(__FUNCTION__, 'jbody=' . print_r($jbody, true), 0);
                if ($statuscode == 0) {
                    if (isset($jbody['errorCode']) && $jbody['errorCode'] > 0) {
                        $statuscode = self::$IS_INVALIDDATA;
                        $err = 'errorCode=' . $jbody['errorCode'];
                        $this->WriteAttributeString('AccessToken_ASC', '');
                    }
                }
                if ($statuscode == 0) {
                    if (isset($jbody['message'][0]['errorCode'])) {
                        $errorCode = $jbody['message'][0]['errorCode'];
                        if (isset($jbody['message'][0]['errorMessage'])) {
                            $err = 'errorMessage=' . $jbody['message'][0]['errorMessage'];
                        } else {
                            $err = 'errorCode=' . $errorCode;
                        }
                        $this->WriteAttributeString('AccessToken_ASC', '');
                        if (in_array($errorCode, ['1001-0001'])) {
                            $this->SendDebug(__FUNCTION__, $err, 0);
                            return $jbody;
                        }
                        $statuscode = self::$IS_INVALIDDATA;
                    }
                }
            }
            if ($statuscode == 0) {
                if (isset($jbody['accessToken'])) {
                    $old_jtoken = json_decode($this->ReadAttributeString('AccessToken_ASC'), true);
                    $old_access_token = isset($old_jtoken['access_token']) ? $old_jtoken['access_token'] : '';
                    $old_expires = isset($old_jtoken['expires']) ? $old_jtoken['expires'] : 0;

                    $new_access_token = '';
                    $new_expires = 0;
                    if (isset($jbody['accessToken']['token'])) {
                        $new_access_token = $jbody['accessToken']['token'];
                        if (isset($jbody['accessToken']['expires'])) {
                            $new_expires = @strtotime($jbody['accessToken']['expires']);
                        }
                    }

                    if ($new_access_token != $old_access_token || $new_expires != $old_expires) {
                        $new_jtoken = [
                            'access_token' => $new_access_token,
                            'expires'      => $new_expires,
                        ];
                        $this->SendDebug(__FUNCTION__, 'renew access_token=' . $new_access_token . ', valid until ' . date('d.m.y H:i:s', $new_expires), 0);
                        $this->WriteAttributeString('AccessToken_ASC', json_encode($new_jtoken));
                    }
                }
            }
            $this->SetBuffer('LastApiCall_ASC', time());
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        return $jbody;
    }

    private function RefreshAccessToken_CC()
    {
        $jtoken = @json_decode($this->ReadAttributeString('AccessToken_CC'), true);
        if (isset($jtoken['refresh_token']) == false) {
            return false;
        }

        $refresh_token = $jtoken['refresh_token'];

        $url = self::$base_auth_url_cc . self::$token_endpoint_cc;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);

        $header_base = [
            'Auth0-Client'    => self::$auth0_client_cc,
            'User-Agent'      => self::$user_agent_auth_cc,
            'Content-Type'    => 'application/json; charset=utf-8',
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, 'header=' . print_r($header, true), 0);

        $postfields = [
            'scope'         => 'openid offline_access comfortcloud.control a2w.control',
            'client_id'     => self::$app_client_id_cc,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
        ];
        $this->SendDebug(__FUNCTION__, 'postfields=' . print_r($postfields, true), 0);
        $postdata = json_encode($postfields);
        $this->SendDebug(__FUNCTION__, 'postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, 'jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode == 0) {
            $access_token = $jbody['access_token'];
            $refresh_token = $jbody['refresh_token'];
            $expires_in = $jbody['expires_in'];
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        $jtoken['access_token'] = $access_token;
        $jtoken['refresh_token'] = $refresh_token;
        $jtoken['expires'] = time() + $expires_in - 60;
        return $jtoken;
    }

    private function FetchAccessToken_CC()
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $ret = $this->RefreshAccessToken_CC();
        if ($ret != false) {
            return $ret;
        }

        # Setting up PKCS data
        $code_verifier = $this->random_string(43);
        $code_challenge = $this->urlsafe_b64encode(hash('sha256', $code_verifier, true));
        $state = $this->random_string(20);
        $this->SendDebug(__FUNCTION__, 'code_verifier=' . $code_verifier . ', code_challenge=' . $code_challenge . ', state=' . $state, 0);

        $pre = 'step 1';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $params = [
            'scope'                => 'openid offline_access comfortcloud.control a2w.control',
            'audience'             => 'https://digital.panasonic.com/' . self::$app_client_id_cc . '/api/v1/',
            'protocol'             => 'oauth2',
            'response_type'        => 'code',
            'code_challenge'       => $code_challenge,
            'code_challenge_method'=> 'S256',
            'auth0Client'          => self::$auth0_client_cc,
            'client_id'            => self::$app_client_id_cc,
            'redirect_uri'         => 'panasonic-iot-cfc://authglb.digital.panasonic.com/android/com.panasonic.ACCsmart/callback',
            'state'                => $state,
        ];

        $url = self::$base_auth_url_cc . self::$auth_endpoint_cc . '?' . http_build_query($params);
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $header_base = [
            'User-Agent' => self::$user_agent_auth_cc,
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 302) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match('|Location: (.*)|i', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "Location" in header';
            } else {
                $location = str_replace(PHP_EOL, '', $matches[1]);
                $this->SendDebug(__FUNCTION__, $pre . ' location="' . $location . '"', 0);
                $location_query = parse_url($location, PHP_URL_QUERY);
                parse_str($location_query, $query);
                $state = $query['state'];
                $this->SendDebug(__FUNCTION__, $pre . ' state=' . $state, 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: auth0=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "auth0" in header';
            } else {
                $auth0 = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' auth0=' . $auth0, 0);
            }
            if (preg_match_all('|Set-Cookie: auth0_compat=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "auth0_compat" in header';
            } else {
                $auth0_compat = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' auth0_compat=' . $auth0_compat, 0);
            }
            if (preg_match_all('|Set-Cookie: did=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "did" in header';
            } else {
                $did = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' did=' . $did, 0);
            }
            if (preg_match_all('|Set-Cookie: did_compat=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "did_compat" in header';
            } else {
                $did_compat = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' did_compat=' . $did_compat, 0);
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 2';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = $curl_info['redirect_url'];
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $header_base = [
            'User-Agent' => self::$user_agent_auth_cc,
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: _csrf=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "_csrf" in header';
            } else {
                $csrf = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' csrf=' . $csrf, 0);
            }
        }
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 3';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = self::$base_auth_url_cc . '/usernamepassword/login';
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $cookies = [
            '_csrf=' . $csrf,
            'auth0=' . $auth0,
            'auth0_compat=' . $auth0_compat,
            'did=' . $did,
            'did_compat=' . $did_compat,
        ];
        $header_base = [
            'Auth0-Client' => self::$auth0_client_cc,
            'User-Agent'   => self::$user_agent_auth_cc,
            'Cookie'       => implode('; ', $cookies),
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postfields = [
            'client_id'    => self::$app_client_id_cc,
            'redirect_uri' => 'panasonic-iot-cfc://authglb.digital.panasonic.com/android/com.panasonic.ACCsmart/callback',
            'tenant'       => 'pdpauthglb-a1',
            'response_type'=> 'code',
            'scope'        => 'openid offline_access comfortcloud.control a2w.control',
            'audience'     => 'https://digital.panasonic.com/' . self::$app_client_id_cc . '/api/v1/',
            '_csrf'        => $csrf,
            'state'        => $state,
            '_intstate'    => 'deprecated',
            'username'     => $username,
            'password'     => $password,
            'lang'         => 'en',
            'connection'   => 'PanasonicID-Authentication',
        ];
        $this->SendDebug(__FUNCTION__, $pre . ' postfields=' . print_r($postfields, true), 0);

        //$postdata = json_encode($postfields);
        $postdata = http_build_query($postfields);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            }
        }
        if ($statuscode == 0) {
            $form_data = [];
            if (preg_match_all('|<input([^>]*)>|Ui', $body, $matches)) {
                // $this->SendDebug(__FUNCTION__, $pre . ' matches='.print_r($matches,true).PHP_EOL;
                foreach ($matches[1] as $match) {
                    // $this->SendDebug(__FUNCTION__, $pre . ' match='.print_r($match,true).PHP_EOL;
                    $name = false;
                    $value = false;
                    if (preg_match('| name="([^"]*)"|Ui', $match, $r)) {
                        $name = $r[1];
                    }
                    if (preg_match('| value="([^"]*)"|Ui', $match, $r)) {
                        $value = $r[1];
                    }
                    if ($name !== false && $value !== false) {
                        $form_data[$name] = htmlspecialchars_decode($value);
                    }
                }
            }
            if ($form_data == []) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "input" in body';
            } else {
                $this->SendDebug(__FUNCTION__, $pre . ' form_data=' . print_r($form_data, true), 0);
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 4';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = self::$base_auth_url_cc . self::$login_callback_cc;
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $cookies = [
            '_csrf=' . $csrf,
            'auth0=' . $auth0,
            'auth0_compat=' . $auth0_compat,
            'did=' . $did,
            'did_compat=' . $did_compat,
        ];
        $header_base = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent'   => self::$user_agent_form_cc,
            'Cookie'       => implode('; ', $cookies),
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postdata = http_build_query($form_data);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 302) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 5';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = $curl_info['redirect_url'];
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $cookies = [
            '_csrf=' . $csrf,
            'auth0=' . $auth0,
            'auth0_compat=' . $auth0_compat,
            'did=' . $did,
            'did_compat=' . $did_compat,
        ];
        $header_base = [
            'User-Agent'   => self::$user_agent_form_cc,
            'Cookie'       => implode('; ', $cookies),
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match('|Location: (.*)|i', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "Location" in header';
            } else {
                $location = str_replace(PHP_EOL, '', $matches[1]);
                $this->SendDebug(__FUNCTION__, $pre . ' location="' . $location . '"', 0);
                $location_query = parse_url($location, PHP_URL_QUERY);
                parse_str($location_query, $query);
                $code = $query['code'];
                $this->SendDebug(__FUNCTION__, $pre . ' code=' . $code, 0);
            }
        }
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');

        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 6';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = self::$base_auth_url_cc . self::$token_endpoint_cc;
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $header_base = [
            'Auth0-Client'    => self::$auth0_client_cc,
            'User-Agent'      => self::$user_agent_auth_cc,
            'Content-Type'    => 'application/json; charset=utf-8',
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postfields = [
            'scope'        => 'openid',
            'client_id'    => self::$app_client_id_cc,
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => 'panasonic-iot-cfc://authglb.digital.panasonic.com/android/com.panasonic.ACCsmart/callback',
            'code_verifier'=> $code_verifier,
        ];
        $this->SendDebug(__FUNCTION__, $pre . ' postfields=' . print_r($postfields, true), 0);
        $postdata = json_encode($postfields);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, $pre . ' jbody=' . print_r($jbody, true), 0);
            foreach (['access_token', 'refresh_token', 'expires_in'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode == 0) {
            $access_token = $jbody['access_token'];
            $refresh_token = $jbody['refresh_token'];
            $expires_in = $jbody['expires_in'];
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 7';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = self::$base_url_cc . self::$auth_v2_login_endpoint_cc;
        $this->SendDebug(__FUNCTION__, $pre . ' url=' . $url, 0);

        $header_base = [
            'Accept'                  => 'application/json; charset=utf-8',
            'Content-Type'            => 'application/json; charset=utf-8',
            'User-Agent'              => self::$user_agent_cc,
            'X-APP-TYPE'              => self::$x_app_type_cc,
            'X-APP-VERSION'           => self::$x_app_version_cc,
            'X-APP-NAME'              => self::$x_app_name_cc,
            'X-APP-TIMESTAMP'         => date('Y-m-d H:i:s', time()),
            'X-CFC-API-KEY'           => $this->random_string_hex(128),
            'X-User-Authorization-V2' => 'Bearer ' . $access_token,
        ];
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postfields = [
            'language'=> 0,
        ];
        $this->SendDebug(__FUNCTION__, $pre . ' postfields=' . print_r($postfields, true), 0);
        $postdata = json_encode($postfields);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = -1;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }
        if ($statuscode == 0) {
            $this->SendDebug(__FUNCTION__, $pre . ' jbody=' . print_r($jbody, true), 0);
            foreach (['clientId'] as $key) {
                if (isset($jbody[$key]) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'missing element "' . $key . '" in "' . $body . '"' . PHP_EOL;
                    break;
                }
            }
        }
        if ($statuscode == 0) {
            $client_id = $jbody['clientId'];
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        $ret = [
            'access_token'  => $access_token,
            'refresh_token' => $refresh_token,
            'client_id'     => $client_id,
            'expires'       => time() + $expires_in - 60,
        ];
        return $ret;
    }

    private function GetAccessToken_CC()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $data = $this->ReadAttributeString('AccessToken_CC');
        if ($data != '') {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expires = isset($jtoken['expires']) ? $jtoken['expires'] : 0;
            if ($expires < time()) {
                $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                $access_token = '';
            }
            if ($access_token != '') {
                $this->SendDebug(__FUNCTION__, 'access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expires), 0);
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $access_token;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
        }

        $jtoken = $this->FetchAccessToken_CC();
        if ($jtoken != false) {
            $access_token = $jtoken['access_token'];
            $expires = $jtoken['expires'];
            $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expires), 0);
            $this->WriteAttributeString('AccessToken_CC', json_encode($jtoken));
        } else {
            $access_token = '';
        }
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $access_token;
    }

    private function GetClientID_CC()
    {
        $jtoken = @json_decode($this->ReadAttributeString('AccessToken_CC'), true);
        return isset($jtoken['client_id']) ? $jtoken['client_id'] : false;
    }

    private function FetchAccessToken_ASC()
    {
        $username = $this->ReadPropertyString('username');
        $password = $this->ReadPropertyString('password');

        $header_dflt = [
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Cache-Control'             => 'max-age=0',
            'Accept-Encoding'           => 'deflate, br',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent'                => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:74.0) Gecko/20100101 Firefox/74.0',
        ];

        $pre = 'step 1';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = self::$base_url_asc . self::$auth_endpoint_asc;
        $this->SendDebug(__FUNCTION__, $pre . ' mode=POST, url=' . $url, 0);

        $header_base = $header_dflt;
        $header_add = [
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'popup-screen-id' => '1001',
            'Registration-Id' => '',
            'Referer'         => self::$base_url_asc,
        ];
        $header_base = array_merge($header_dflt, $header_add);
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: com.auth0.state=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "com.auth0.state" in header';
            } else {
                $auth0_state = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' auth0_state=' . $auth0_state, 0);
            }
        }
        if ($statuscode == 0) {
            $jbody = @json_decode($body, true);
            if ($jbody == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } elseif (isset($jbody['authorizeUrl']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "authorizeUrl" in body';
            } else {
                $authorize_url = $jbody['authorizeUrl'];
                $this->SendDebug(__FUNCTION__, $pre . ' authorize_url=' . $authorize_url, 0);
                $auth_query = parse_url($authorize_url, PHP_URL_QUERY);
                parse_str($auth_query, $query);
                if (isset($query['client_id']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "client_id" in "authorizeUrl"';
                } else {
                    $client_id = $query['client_id'];
                    $this->SendDebug(__FUNCTION__, $pre . ' client_id=' . $client_id, 0);
                }
                if (isset($query['audience']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "audience" in "authorizeUrl"';
                } else {
                    $audience = $query['audience'];
                    $this->SendDebug(__FUNCTION__, $pre . ' audience=' . $audience, 0);
                }
                if (isset($query['redirect_uri']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "redirect_uri" in "authorizeUrl"';
                } else {
                    $redirect_uri = $query['redirect_uri'];
                    $this->SendDebug(__FUNCTION__, $pre . ' redirect_uri=' . $redirect_uri, 0);
                }
                if (isset($query['response_type']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "response_type" in "authorizeUrl"';
                } else {
                    $response_type = $query['response_type'];
                    $this->SendDebug(__FUNCTION__, $pre . ' response_type=' . $response_type, 0);
                }
                if (isset($query['scope']) == false) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'no "scope" in "authorizeUrl"';
                } else {
                    $scope = $query['scope'];
                    $this->SendDebug(__FUNCTION__, $pre . ' scope=' . $scope, 0);
                }
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 2';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $params = [
            'client_id'     => $client_id,
            'audience'      => $audience,
            'redirect_uri'  => $redirect_uri,
            'response_type' => $response_type,
            'state'         => $auth0_state,
            'scope'         => $scope,
        ];
        $url = 'https://authglb.digital.panasonic.com/authorize' . '?' . http_build_query($params);
        $this->SendDebug(__FUNCTION__, $pre . ' mode=GET, url=' . $url, 0);

        $header_base = $header_dflt;
        $header_add = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Referer'      => self::$base_url_asc,
        ];
        $header_base = array_merge($header_dflt, $header_add);
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 302) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match('|Location: (.*)|i', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "Location" in header';
            } else {
                $location = str_replace(PHP_EOL, '', $matches[1]);
                $location_query = parse_url($location, PHP_URL_QUERY);
                parse_str($location_query, $query);
                $state = $query['state'];
                $this->SendDebug(__FUNCTION__, $pre . ' state=' . $state, 0);
            }
        }
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: auth0=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "auth0" in header';
            } else {
                $auth0 = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' auth0=' . $auth0, 0);
            }
            if (preg_match_all('|Set-Cookie: auth0_compat=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "auth0_compat" in header';
            } else {
                $auth0_compat = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' auth0_compat=' . $auth0_compat, 0);
            }
            if (preg_match_all('|Set-Cookie: did=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "did" in header';
            } else {
                $did = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' did=' . $did, 0);
            }
            if (preg_match_all('|Set-Cookie: did_compat=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "did_compat" in header';
            } else {
                $did_compat = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' did_compat=' . $did_compat, 0);
            }
        }
        if ($statuscode == 0) {
            if (isset($curl_info['redirect_url']) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "redirect_url" in curl_info';
            } else {
                $redirect_url = $curl_info['redirect_url'];
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 3';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = $redirect_url;
        $this->SendDebug(__FUNCTION__, $pre . ' mode=GET, url=' . $url, 0);

        $header_base = $header_dflt;
        $header_add = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Referer'      => self::$base_url_asc,
        ];
        $header_base = array_merge($header_dflt, $header_add);
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 302) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        $csrf = '';
        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: _csrf=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "_csrf" in header';
            } else {
                $csrf = $matches[1][0];
                $this->SendDebug(__FUNCTION__, $pre . ' csrf=' . $csrf, 0);
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 4';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = 'https://authglb.digital.panasonic.com/usernamepassword/login';
        $this->SendDebug(__FUNCTION__, $pre . ' mode=POST, url=' . $url, 0);

        $header_base = $header_dflt;
        $params = [
            'state'         => $state,
            'client_id'     => $client_id,
            'protocol'      => 'oauth2',
            'audience'      => $audience,
            'redirect_uri'  => $redirect_uri,
            'response_type' => $response_type,
            'scope'         => $scope,
        ];
        $referer = 'https://authglb.digital.panasonic.com/login' . '?' . http_build_query($params);
        $cookies = [
            '_csrf=' . $csrf,
            'auth0=' . $auth0,
            'auth0_compat=' . $auth0_compat,
            'did=' . $did,
            'did_compat=' . $did_compat,
        ];
        $header_add = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Auth0-Client' => self::$auth0_client_asc,
            'Referer'      => $referer,
            'Cookie'       => implode('; ', $cookies),
        ];
        $header_base = array_merge($header_dflt, $header_add);
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postfields = [
            'client_id'     => $client_id,
            'audience'      => $audience,
            'redirect_uri'  => $redirect_uri,
            'tenant'        => 'pdpauthglb-a1',
            'response_type' => $response_type,
            'scope'         => $scope,
            '_csrf'         => $csrf,
            'state'         => $state,
            '_intstate'     => 'deprecated',
            'username'      => $username,
            'password'      => $password,
            'lang'          => 'de',
            'connection'    => 'PanasonicID-Authentication',
        ];
        $postdata = json_encode($postfields);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        // $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                // $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            }
        }
        $action_url = false;
        if ($statuscode == 0) {
            if (preg_match('|action="([^"]*)"|Ui', $body, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "action" in body';
            } else {
                $action_url = $matches[1];
                $this->SendDebug(__FUNCTION__, $pre . ' action_url=' . $action_url, 0);
            }
        }
        $form_data = [];
        if ($statuscode == 0) {
            if (preg_match_all('|<input([^>]*)>|Ui', $body, $matches)) {
                // $this->SendDebug(__FUNCTION__, $pre . ' matches='.print_r($matches,true).PHP_EOL;
                foreach ($matches[1] as $match) {
                    // $this->SendDebug(__FUNCTION__, $pre . ' match='.print_r($match,true).PHP_EOL;
                    $name = false;
                    $value = false;
                    if (preg_match('| name="([^"]*)"|Ui', $match, $r)) {
                        $name = $r[1];
                    }
                    if (preg_match('| value="([^"]*)"|Ui', $match, $r)) {
                        $value = $r[1];
                    }
                    if ($name !== false && $value !== false) {
                        $form_data[$name] = htmlspecialchars_decode($value);
                    }
                }
            }
            if ($form_data == []) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "input" in body';
            } else {
                $this->SendDebug(__FUNCTION__, $pre . ' form_data=' . print_r($form_data, true), 0);
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $pre = 'step 5';
        $this->SendDebug(__FUNCTION__, '*** ' . $pre, 0);

        $url = $action_url;
        $this->SendDebug(__FUNCTION__, $pre . ' mode=POST, url=' . $url, 0);

        $header_base = $header_dflt;
        $params = [
            'state'         => $state,
            'client_id'     => $client_id,
            'protocol'      => 'oauth2',
            'audience'      => $audience,
            'redirect_uri'  => $redirect_uri,
            'response_type' => $response_type,
            'scope'         => $scope,
        ];
        $referer = 'https://authglb.digital.panasonic.com/login' . '?' . http_build_query($params);
        $cookies = [
            '_csrf=' . $csrf,
            'auth0=' . $auth0,
            'auth0_compat=' . $auth0_compat,
            'did=' . $did,
            'did_compat=' . $did_compat,
        ];
        $header_add = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Referer'      => $referer,
            'Cookie'       => implode('; ', $cookies),
        ];
        $header_base = array_merge($header_dflt, $header_add);
        $header = $this->build_header($header_base);
        $this->SendDebug(__FUNCTION__, $pre . ' header=' . print_r($header, true), 0);

        $postdata = http_build_query($form_data);
        $this->SendDebug(__FUNCTION__, $pre . ' postdata=' . $postdata, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $curl_info = curl_getinfo($ch);
        curl_close($ch);
        $httpcode = $curl_info['http_code'];
        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, $pre . '  => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        // $this->SendDebug(__FUNCTION__, $pre . '  => curl_info=' . print_r($curl_info, true), 0);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        }
        if ($statuscode == 0) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . '(' . $this->HttpCode2Text($httpcode) . ')';
            }
        }
        if ($statuscode == 0) {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, $pre . '  => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, $pre . '  => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, $pre . '  => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }

        if ($statuscode == 0) {
            if (preg_match_all('|Set-Cookie: accessToken=(.*);|Ui', $head, $matches) == false) {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'no "accessToken" in header';
            } else {
                $access_token = $matches[1][0];
            }
        }

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);

        $ret = [
            'access_token' => $access_token,
            'expires'      => time() + self::$token_expiration_interval,
        ];
        return $ret;
    }

    private function GetAccessToken_ASC()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $relogin = false;
        $inactivity_logout = $this->ReadPropertyInteger('inactivity_logout_ASC');
        if ($inactivity_logout > 0) {
            $ts = intval($this->GetBuffer('LastApiCall_ASC'));
            $this->SendDebug(__FUNCTION__, 'last api call=' . date('d.m.Y H:i:s', $ts), 0);
            $ts += $inactivity_logout;
            if ($ts < time()) {
                $relogin = true;
            }
        }

        $data = $this->ReadAttributeString('AccessToken_ASC');
        if ($data != '') {
            $jtoken = json_decode($data, true);
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expires = isset($jtoken['expires']) ? $jtoken['expires'] : 0;
            if ($expires < time()) {
                $this->SendDebug(__FUNCTION__, 'access_token expires', 0);
                $access_token = '';
            }
            if ($access_token != '' && $relogin == true) {
                $this->SendDebug(__FUNCTION__, 'ignore access_token, re-login cause inactivity-logout', 0);
                $access_token = '';
            }
            if ($access_token != '') {
                $this->SendDebug(__FUNCTION__, 'access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expires), 0);
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $access_token;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
        }

        $jtoken = $this->FetchAccessToken_ASC();
        if ($jtoken != false) {
            $access_token = $jtoken['access_token'];
            $expires = $jtoken['expires'];
            $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expires), 0);
            $this->WriteAttributeString('AccessToken_ASC', json_encode($jtoken));
        } else {
            $access_token = '';
        }
        IPS_SemaphoreLeave($this->SemaphoreID);
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

        $access_token = $this->GetAccessToken_CC();
        if ($access_token == false) {
            $this->MaintainStatus(self::$IS_UNAUTHORIZED);
            $msg = $this->Translate('Invalid login-data at Panasonic Comfort Cloud') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $r = $this->GetGroups();
        if ($r == false) {
            $msg = $this->Translate('Unable to get group list') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $msg = $this->Translate('valid account-data') . PHP_EOL;
        $msg .= PHP_EOL;

        $groups = json_decode($r, true);
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
        $access_token = $this->GetAccessToken_CC();
        if ($access_token == false) {
            return false;
        }

        $client_id = $this->GetClientID_CC();

        $url = self::$group_endpoint_cc;

        $header_add = [
            'X-Client-Id'             => $client_id,
            'X-User-Authorization-V2' => 'Bearer ' . $access_token,
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, '', '', $header_add, self::$API_CC);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $groups = $this->GetArrayElem($jdata, 'groupList', '');
        $this->SendDebug(__FUNCTION__, 'groups=' . print_r($groups, true), 0);
        return json_encode($groups);
    }

    private function MapGuidToId_ASC(string $guid)
    {
        for ($retry = 0; $retry <= 1; $retry++) {
            $access_token = $this->GetAccessToken_ASC();
            if ($access_token == false) {
                return false;
            }

            $url = self::$contract_endpoint_asc;

            $cookies = [
                'accessToken=' . $access_token,
                'selectedGwid=' . $guid,
            ];

            $header_add = [
                'Accept'        => 'application/json; charset=UTF-8',
                'Referer'       => self::$base_url_asc,
                'Cache-Control' => 'max-age=0',
                'Cookie'        => implode('; ', $cookies),
            ];

            $postfields = [
            ];

            if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
                $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
                return;
            }
            $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add, self::$API_ASC);
            IPS_SemaphoreLeave($this->SemaphoreID);
            if ($jdata == false) {
                return false;
            }
            if (isset($jdata['message'][0]['errorCode'])) {
                if ($retry) {
                    return false;
                }
                $this->SendDebug(__FUNCTION__, 'retry request', 0);
            } else {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function GetDeviceStatus(string $guid, bool $now)
    {
        $access_token = $this->GetAccessToken_CC();
        if ($access_token == false) {
            return false;
        }

        $client_id = $this->GetClientID_CC();

        $url = ($now ? self::$device_status_now_endpoint_cc : self::$device_status_endpoint_cc) . $guid;

        $header_add = [
            'X-Client-Id'             => $client_id,
            'X-User-Authorization-V2' => 'Bearer ' . $access_token,
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, '', '', $header_add, self::$API_CC);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function GetDeviceStatus_ASC(string $device_id)
    {
        for ($retry = 0; $retry <= 1; $retry++) {
            $access_token = $this->GetAccessToken_ASC();
            if ($access_token == false) {
                return false;
            }

            $url = self::$device_endpoint_asc . $device_id;

            $params = [
                'var.deviceDirect' => true,
            ];

            $cookies = [
                'accessToken=' . $access_token,
            ];

            $header_add = [
                'Accept'       => 'application/json; charset=UTF-8',
                'Referer'      => self::$base_url_asc,
                'Cookie'       => implode('; ', $cookies),
            ];

            $postfields = '';

            if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
                $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
                return;
            }
            $jdata = $this->do_HttpRequest($url, $postfields, $params, $header_add, self::$API_ASC);
            IPS_SemaphoreLeave($this->SemaphoreID);
            if ($jdata == false) {
                return false;
            }
            if (isset($jdata['message'][0]['errorCode'])) {
                if ($retry) {
                    return false;
                }
                $this->SendDebug(__FUNCTION__, 'retry request', 0);
            } else {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $ret = isset($jdata['status']) ? json_encode($jdata['status']) : '';
        return $ret;
    }

    private function GetDevices_ASC()
    {
        for ($retry = 0; $retry <= 1; $retry++) {
            $access_token = $this->GetAccessToken_ASC();
            if ($access_token == false) {
                return false;
            }

            $url = self::$devices_endpoint_asc;

            $cookies = [
                'accessToken=' . $access_token,
            ];

            $header_add = [
                'Accept'       => 'application/json; charset=UTF-8',
                'Referer'      => self::$base_url_asc,
                'Cookie'       => implode('; ', $cookies),
            ];

            $postfields = '';

            if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
                $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
                return;
            }
            $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add, self::$API_ASC);
            IPS_SemaphoreLeave($this->SemaphoreID);
            if ($jdata == false) {
                return false;
            }
            if (isset($jdata['message'][0]['errorCode'])) {
                if ($retry) {
                    return false;
                }
                $this->SendDebug(__FUNCTION__, 'retry request', 0);
            } else {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $ret = isset($jdata['device']) ? json_encode($jdata['device']) : '';
        return $ret;
    }

    private function ControlDevice(string $guid, string $parameters)
    {
        $access_token = $this->GetAccessToken_CC();
        if ($access_token == false) {
            return false;
        }

        $client_id = $this->GetClientID_CC();

        $url = self::$device_control_endpoint_cc;

        $postfields = [
            'deviceGuid' => $guid,
            'parameters' => json_decode($parameters, true),
        ];

        $header_add = [
            'X-Client-Id'             => $client_id,
            'X-User-Authorization-V2' => 'Bearer ' . $access_token,
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add, self::$API_CC);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function ControlDevice_ASC(string $device_id, string $parameters)
    {
        for ($retry = 0; $retry <= 1; $retry++) {
            $access_token = $this->GetAccessToken_ASC();
            if ($access_token == false) {
                return false;
            }

            $url = self::$device_endpoint_asc . $device_id;
            $params = '';

            $status = [
                'deviceGuid'=> $device_id,
            ];
            $postfields = [
                'status' => [
                    array_merge($status, json_decode($parameters, true)),
                ],
            ];

            $cookies = [
                'accessToken=' . $access_token,
                'selectedDeviceId=' . $device_id,
            ];

            $header_add = [
                'Accept'          => 'application/json; charset=UTF-8',
                'Content-Type'    => 'application/json; charset=utf-8',
                'Cache-Control'   => 'max-age=0',
                'Referer'         => self::$base_url_asc . self::$status_referer_asc,
                'Cookie'          => implode('; ', $cookies),
            ];

            if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
                $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
                return;
            }
            $jdata = $this->do_HttpRequest($url, $postfields, $params, $header_add, self::$API_ASC);
            IPS_SemaphoreLeave($this->SemaphoreID);
            if ($jdata == false) {
                return false;
            }
            if (isset($jdata['message'][0]['errorCode'])) {
                if ($retry) {
                    return false;
                }
                $this->SendDebug(__FUNCTION__, 'retry request', 0);
            } else {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function GetDeviceHistory(string $guid, int $dataMode, int $tstamp)
    {
        $access_token = $this->GetAccessToken_CC();
        if ($access_token == false) {
            return false;
        }

        $client_id = $this->GetClientID_CC();

        $url = self::$device_history_endpoint_cc;

        $header_add = [
            'X-Client-Id'             => $client_id,
            'X-User-Authorization-V2' => 'Bearer ' . $access_token,
        ];

        $postfields = [
            'deviceGuid' => $guid,
            'dataMode'   => $dataMode,
            'date'       => date('Ymd', $tstamp),
            'osTimezone' => date('P', $tstamp),
        ];

        if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }
        $jdata = $this->do_HttpRequest($url, $postfields, '', $header_add, self::$API_CC);
        IPS_SemaphoreLeave($this->SemaphoreID);
        if ($jdata == false) {
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        return json_encode($jdata);
    }

    private function GetDeviceHistory_ASC(string $device_id, int $dataMode, int $tstamp)
    {
        for ($retry = 0; $retry <= 1; $retry++) {
            $access_token = $this->GetAccessToken_ASC();
            if ($access_token == false) {
                return false;
            }

            $url = self::$device_consumption_endpoint_asc . $device_id;

            /*
                HOURLY = "hourly"
                DAILY = "daily"
                MONTHLY = "monthly"
             */

            $params = [
                'date' => date('Y-m-d', $tstamp),
            ];

            $cookies = [
                'accessToken=' . $access_token,
                'selectedGwid=' . substr($device_id, 6, 10),
            ];

            $header_add = [
                'Accept'        => 'application/json; charset=UTF-8',
                'Cache-Control' => 'max-age=0',
                'Referer'       => self::$base_url_asc . self::$status_referer_asc,
                'Cookie'        => implode('; ', $cookies),
            ];

            $postfields = '';

            if (IPS_SemaphoreEnter($this->SemaphoreID, self::$semaphoreTM) == false) {
                $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
                return;
            }

            $jdata = $this->do_HttpRequest($url, $postfields, $params, $header_add, self::$API_ASC);
            IPS_SemaphoreLeave($this->SemaphoreID);
            if ($jdata == false) {
                return false;
            }
            if (isset($jdata['message'][0]['errorCode'])) {
                if ($retry) {
                    return false;
                }
                $this->SendDebug(__FUNCTION__, 'retry request', 0);
            } else {
                break;
            }
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $ret = isset($jdata['dateData']) ? json_encode($jdata['dateData']) : '';
        return $ret;
    }

    private function random_string(int $length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randstring = '';
        for ($i = 0; $i < $length; $i++) {
            $randstring .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randstring;
    }

    private function random_string_hex(int $length)
    {
        $characters = '0123456789ABCDEF';
        $randstring = '';
        for ($i = 0; $i < $length; $i++) {
            $randstring .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randstring;
    }

    private function urlsafe_b64encode($string)
    {
        $data = base64_encode($string);
        $data = str_replace(['+', '/'], ['-', '_'], $data);
        $data = rtrim($data, '=');
        return $data;
    }

    private function build_header($headerfields)
    {
        $header = [];
        foreach ($headerfields as $key => $value) {
            $header[] = $key . ': ' . $value;
        }
        return $header;
    }
}
