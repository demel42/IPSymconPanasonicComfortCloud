<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudAquarea extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    public static $ZONE_MAX = 2;
    public static $TANK_MAX = 2;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyBoolean('log_no_parent', true);

        $this->RegisterPropertyString('guid', '');
        $this->RegisterPropertyInteger('type', 0);
        $this->RegisterPropertyString('model', '');

        $this->RegisterPropertyInteger('zone_count', 1);
        $this->RegisterPropertyInteger('tank_count', 1);

        $this->RegisterPropertyBoolean('with_energy', false);
        $this->RegisterPropertyInteger('update_interval', 60);
        $this->RegisterPropertyInteger('short_action_refresh_delay', 3);
        $this->RegisterPropertyInteger('long_action_refresh_delay', 4);

        $this->RegisterAttributeString('device_id', '');
        $this->RegisterAttributeString('device_config', '');
        $this->RegisterAttributeString('external_update_interval', '');

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{FA9B3ACC-2056-06B5-4DA6-0C7D375A89FB}');

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timeStamp, $senderID, $message, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;

        $this->MaintainVariable('Operate', $this->Translate('Operate'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        // $this->MaintainAction('Operate', true);

        $this->MaintainVariable('OperationMode', $this->Translate('Operation mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.OperationMode_ASC', $vpos++, true);
        $this->MaintainVariable('WorkingMode', $this->Translate('Working mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.WorkingMode_ASC', $vpos++, true);

        $this->MaintainVariable('QuietMode', $this->Translate('Whisper mode'), VARIABLETYPE_INTEGER, 'PanasonicCloud.QuietMode_ASC', $vpos++, true);
        $this->MaintainVariable('PowerMode', $this->Translate('Power operation'), VARIABLETYPE_INTEGER, 'PanasonicCloud.PowerMode_ASC', $vpos++, true);
        $this->MaintainVariable('ForceHeater', $this->Translate('Electric immersion heater heating'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        $this->MaintainVariable('ForceDHW', $this->Translate('Electric immersion heater hot water'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        $this->MaintainVariable('DefrostMode', $this->Translate('Manual defrosting'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);
        $this->MaintainVariable('HolidayTimer', $this->Translate('Holiday timer'), VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, true);

        $this->MaintainVariable('DeviceActivity', $this->Translate('Activity'), VARIABLETYPE_INTEGER, 'PanasonicCloud.DeviceActivity_ASC', $vpos++, true);

        $this->MaintainVariable('OutsideTemperature', $this->Translate('Outside temperature'), VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, true);

        $vpos = 100;
        $zone_count = $this->ReadPropertyInteger('zone_count');
        for ($i = 0; $i < self::$ZONE_MAX; $i++) {
            $use = $i < $zone_count;
            $vpos = 100 + ($i * 20);

            $ident = 'Zone' . $i . '_Operate';
            $name = $this->TranslateFormat('Zone {$zone}: operate', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, $use);

            $ident = 'Zone' . $i . '_TemperatureNow';
            $name = $this->TranslateFormat('Zone {$zone}: current temperature', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_TargetHeatTemperature';
            $name = $this->TranslateFormat('Zone {$zone}: target heating temperature', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_EcoHeatAdjust';
            $name = $this->TranslateFormat('Zone {$zone}: eco heating temperature adjustment', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_ComfortHeatAdjust';
            $name = $this->TranslateFormat('Zone {$zone}: comfort heating temperature adjustment', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_TargetCoolTemperature';
            $name = $this->TranslateFormat('Zone {$zone}: target cooling temperature', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_EcoCoolAdjust';
            $name = $this->TranslateFormat('Zone {$zone}: eco cooling temperature adjustment', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Zone' . $i . '_ComfortCoolAdjust';
            $name = $this->TranslateFormat('Zone {$zone}: comfort cooling temperature adjustment', ['{$zone}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);
        }

        $vpos = 200;
        $tank_count = $this->ReadPropertyInteger('tank_count');
        for ($i = 0; $i < self::$TANK_MAX; $i++) {
            $use = $i < $tank_count;
            $vpos = 200 + ($i * 20);

            $ident = 'Tank' . $i . '_Operate';
            $name = $this->TranslateFormat('Tank {$tank}: operate', ['{$tank}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_BOOLEAN, 'PanasonicCloud.Operate', $vpos++, $use);

            $ident = 'Tank' . $i . '_TemperatureNow';
            $name = $this->TranslateFormat('Tank {$tank}: current temperature', ['{$tank}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);

            $ident = 'Tank' . $i . '_TargetTemperature';
            $name = $this->TranslateFormat('Tank {$tank}: target temperature', ['{$tank}' => ($i + 1)]);
            $this->MaintainVariable($ident, $name, VARIABLETYPE_FLOAT, 'PanasonicCloud.Temperature', $vpos++, $use);
        }

        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $s = '';
        $guid = $this->ReadPropertyString('guid');
        if ($guid != '') {
            $r = explode('+', $guid);
            if (is_array($r) && count($r) == 2) {
                $s = $r[0] . '(#' . $r[1] . ')';
            }
        }
        $this->SetSummary($s);

        $this->MaintainStatus(IS_ACTIVE);

        $this->AdjustActions();

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Panasonic ComfortCloud Aquarea Device');

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
            'caption' => 'Basic configuration',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'guid',
                    'caption' => 'Device-ID',
                    'enabled' => false
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'model',
                    'caption' => 'Model',
                    'enabled' => false
                ],
                [
                    'type'    => 'Select',
                    'options' => $this->DeviceTypeAsOptions(),
                    'name'    => 'type',
                    'caption' => 'Type',
                    'enabled' => false
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'zone_count',
            'minimum' => 0,
            'maximum' => self::$ZONE_MAX,
            'caption' => 'Zone count',
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'tank_count',
            'minimum' => 0,
            'maximum' => self::$TANK_MAX,
            'caption' => 'Tank count',
        ];

        /*
        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'with_energy',
                    'caption' => 'save daily energy consumption'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... by activating this switch, additional variables are created and logged as counters',
                    'italic'  => true,
                ],
            ],
        ];
         */

        $formElements[] = [
            'type'    => 'RowLayout',
            'items'   => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'update_interval',
                    'suffix'  => 'Seconds',
                    'minimum' => 0,
                    'caption' => 'Update interval',
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'short_action_refresh_delay',
                    'suffix'  => 'Seconds',
                    'minimum' => 0,
                    'caption' => 'Update with short action after',
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'long_action_refresh_delay',
                    'suffix'  => 'Seconds',
                    'minimum' => 0,
                    'caption' => 'Update with long action after',
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'log_no_parent',
            'caption' => 'Generate message when the gateway is inactive',
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
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'     => 'ExpansionPanel',
            'caption'  => 'Expert area',
            'expanded' => false,
            'items'    => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'     => 'ExpansionPanel',
            'caption'  => 'Test area',
            'expanded' => false,
            'items'    => [
                [
                    'type' => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $sec = $this->ReadAttributeString('external_update_interval');
            if ($sec == '') {
                $sec = $this->ReadPropertyInteger('update_interval');
            }
        }
        $this->MaintainTimer('UpdateStatus', $sec * 1000);
    }

    public function OverwriteUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $this->WriteAttributeString('external_update_interval', '');
        } else {
            $this->WriteAttributeString('external_update_interval', $sec);
        }
        $this->SetUpdateInterval($sec);
    }

    private function GetDeviceId()
    {
        $guid = $this->ReadPropertyString('guid');
        $type = $this->ReadPropertyInteger('type');

        $device_id = $this->ReadAttributeString('device_id');
        if ($device_id == '') {
            $sdata = [
                'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
                'CallerID' => $this->InstanceID,
                'Function' => 'MapGuidToId',
                'Type'     => $type,
                'Guid'     => $guid,
            ];
            $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
            $data = $this->SendDataToParent(json_encode($sdata));
            $jdata = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            if (isset($jdata['DeviceId'])) {
                $device_id = $jdata['DeviceId'];
                $this->WriteAttributeString('device_id', $device_id);
            } else {
                $this->SendDebug(__FUNCTION__, 'unable to get  device_id', 0);
            }
        }
        return $device_id;
    }

    private function GetArrayElemList($array, $parentKey = '')
    {
        $keys = [];
        foreach ($array as $parentKey => $i) {
            if (is_array($i)) {
                $nestedKeys = $this->GetArrayElemList($i, $parentKey);
                foreach ($nestedKeys as $idx => $key) {
                    $nestedKeys[$idx] = $parentKey . '.' . $key;
                }
                $keys = array_merge($keys, $nestedKeys);
            } else {
                $keys[] = $parentKey;
            }
        }
        return $keys;
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return;
        }

        $now = time();

        $guid = $this->ReadPropertyString('guid');
        $type = $this->ReadPropertyInteger('type');
        $device_id = $this->GetDeviceId();

        $configuration = [];

        $sdata = [
            'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'CallerID' => $this->InstanceID,
            'Function' => 'GetDevices',
            'Type'     => $type,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $devices = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);
        foreach ($devices as $device) {
            $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
            if ($device['deviceGuid'] == $guid) {
                $configuration = (array) $this->GetArrayElem($device, 'configration.0', '');
            }
        }
        $this->SendDebug(__FUNCTION__, 'configuration=' . print_r($configuration, true), 0);

        $sdata = [
            'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'CallerID' => $this->InstanceID,
            'Function' => 'GetDeviceStatus',
            'Type'     => $type,
            'DeviceID' => $device_id,
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $this->SendDebug(__FUNCTION__, ' => ' . $data, 0);
        $jdata = json_decode($data, true);
        $status = isset($jdata[0]) ? $jdata[0] : [];
        $this->SendDebug(__FUNCTION__, 'status=' . print_r($status, true), 0);

        $jdata = [
            'configuration' => $configuration,
            'status'        => $status,
        ];

        $device_config = @json_decode($this->ReadAttributeString('device_config'), true);

        $is_changed = false;
        $fnd = false;

        $used_fields = [];
        $missing_fields = [];
        $ignored_fields = [
            'configuration.deviceGuid',
            'status.deviceGuid',
            'status.bivalent',
        ];

        $zone_count = $this->ReadPropertyInteger('zone_count');
        for ($i = $zone_count; $i < self::$ZONE_MAX; $i++) {
            $ignored_fields[] = 'zoneStatus.' . $i . '.*';
        }
        $tank_count = $this->ReadPropertyInteger('tank_count');
        for ($i = $tank_count; $i < self::$TANK_MAX; $i++) {
            $ignored_fields[] = 'tankStatus.' . $i . '.*';
        }

        $operate = $this->GetArrayElem($jdata, 'status.operationStatus', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.operationStatus';
            $this->SendDebug(__FUNCTION__, '... Operate (operationStatus)=' . $operate, 0);
            $this->SaveValue('Operate', (bool) $operate, $is_changed);
        } else {
            $missing_fields[] = 'status.operationStatus';
        }

        $operationMode = $this->GetArrayElem($jdata, 'status.operationMode', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.operationMode';
            $this->SendDebug(__FUNCTION__, '... OperationMode (operationMode)=' . $operationMode, 0);
            $this->SaveValue('OperationMode', (int) $operationMode, $is_changed);
        } else {
            $missing_fields[] = 'status.operationMode';
        }

        $powerMode = $this->GetArrayElem($jdata, 'status.powerful', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.powerful';
            $this->SendDebug(__FUNCTION__, '... PowerMode (powerful)=' . $powerMode, 0);
            $this->SaveValue('PowerMode', (int) $powerMode, $is_changed);
        } else {
            $missing_fields[] = 'status.powerful';
        }

        $quietMode = $this->GetArrayElem($jdata, 'status.quietMode', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.quietMode';
            $this->SendDebug(__FUNCTION__, '... QuietMode (quietMode)=' . $quietMode, 0);
            $this->SaveValue('QuietMode', (int) $quietMode, $is_changed);
        } else {
            $missing_fields[] = 'status.quietMode';
        }

        $deiceStatus = $this->GetArrayElem($jdata, 'status.deiceStatus', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.deiceStatus';
            $this->SendDebug(__FUNCTION__, '... DefrostMode (deiceStatus)=' . $deiceStatus, 0);
            $this->SaveValue('DefrostMode', (bool) $deiceStatus, $is_changed);
        } else {
            $missing_fields[] = 'status.deiceStatus';
        }

        $holidayTimer = $this->GetArrayElem($jdata, 'status.holidayTimer', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.holidayTimer';
            $this->SendDebug(__FUNCTION__, '... HolidayTimer (holidayTimer)=' . $holidayTimer, 0);
            $this->SaveValue('HolidayTimer', (bool) $holidayTimer, $is_changed);
        } else {
            $missing_fields[] = 'status.holidayTimer';
        }

        $forceHeater = $this->GetArrayElem($jdata, 'status.forceHeater', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.forceHeater';
            $this->SendDebug(__FUNCTION__, '... ForceHeater (forceHeater)=' . $forceHeater, 0);
            $this->SaveValue('ForceHeater', (bool) $forceHeater, $is_changed);
        } else {
            $missing_fields[] = 'status.forceHeater';
        }

        $forceDHW = $this->GetArrayElem($jdata, 'status.forceDHW', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.forceDHW';
            $this->SendDebug(__FUNCTION__, '... ForceDHW (forceDHW)=' . $forceDHW, 0);
            $this->SaveValue('ForceDHW', (bool) $forceDHW, $is_changed);
        } else {
            $missing_fields[] = 'status.forceDHW';
        }

        $outdoorNow = $this->GetArrayElem($jdata, 'status.outdoorNow', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.outdoorNow';
            $this->SendDebug(__FUNCTION__, '... OutsideTemperature (outdoorNow)=' . $outdoorNow, 0);
            $this->SaveValue('OutsideTemperature', (float) $outdoorNow, $is_changed);
        } else {
            $missing_fields[] = 'status.outdoorNow';
        }

        if ($missing_fields != []) {
            $this->SendDebug(__FUNCTION__, 'missing fields', 0);
            foreach ($missing_fields as $var) {
                $this->SendDebug(__FUNCTION__, '... ' . $var, 0);
            }
        }

        $operationStatus = $this->GetArrayElem($jdata, 'status.operationStatus', '', $fnd);
        if ($fnd) {
            $used_fields[] = 'status.operationStatus';
            $operationMode = $this->GetArrayElem($jdata, 'status.operationMode', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.operationMode';
                $direction = $this->GetArrayElem($jdata, 'status.direction', '', $fnd);
                if ($fnd) {
                    $used_fields[] = 'status.direction';
                    $tank = $this->GetArrayElem($jdata, 'status.tank', '', $fnd);
                    if ($fnd) {
                        $used_fields[] = 'status.tank';
                        $tank_operationStatus = $this->GetArrayElem($jdata, 'status.tankStatus.0.operationStatus', '', $fnd);
                        if ($fnd) {
                            $used_fields[] = 'status.tankStatus.0.operationStatus';
                            if ($operationStatus === 0 /* OFF */) {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_OFF;
                            } elseif ($direction == 0 /* IDLE */) {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_IDLE;
                            } elseif ($direction == 1 /* PUMP */ && in_array($operationMode, [1 /* HEAT */, 3 /* AUTO_HEAT */])) {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_HEATING;
                            } elseif ($direction == 1 /* PUMP */ && in_array($operationMode, [2 /* COOL */, 4 /* AUTO_COOL */])) {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_COOLING;
                            } elseif ($direction == 2 /* WATER */ && $tank == 1 && $tank_operationStatus == 1 /* ON */) {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_HEATING_WATER;
                            } else {
                                $deviceActivity = self::$DEVICE_ACTIVITY_ASC_IDLE;
                            }
                            $this->SendDebug(__FUNCTION__, '... DeviceActivity=' . $deviceActivity, 0);
                            $this->SaveValue('DeviceActivity', $deviceActivity, $is_changed);
                        } else {
                            $missing_fields[] = 'status.tankStatus.0.operationStatus';
                        }
                    } else {
                        $missing_fields[] = 'status.tank';
                    }
                } else {
                    $missing_fields[] = 'status.direction';
                }
            } else {
                $missing_fields[] = 'status.operationMode';
            }
        } else {
            $missing_fields[] = 'status.operationStatus';
        }

        $workingMode = 0 /* NORMAL */;
        for ($i = 0; $i < 2; $i++) {
            $operationStatus = $this->GetArrayElem($jdata, 'status.specialStatus.' . $i . '.operationStatus', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.specialStatus.' . $i . '.operationStatus';
                $specialMode = $this->GetArrayElem($jdata, 'status.specialStatus.' . $i . '.specialMode', '', $fnd);
                if ($fnd) {
                    $used_fields[] = 'status.specialStatus.' . $i . '.specialMode';
                    if ($operationStatus == 1 /* ON */) {
                        $workingMode = $specialMode;
                    }
                } else {
                    $missing_fields[] = 'status.specialStatus.' . $i . '.specialMode';
                }
            } else {
                $missing_fields[] = 'status.specialStatus.' . $i . '.operationStatus';
            }
        }
        $this->SendDebug(__FUNCTION__, '... WorkingMode=' . $workingMode, 0);
        $this->SaveValue('WorkingMode', $workingMode, $is_changed);

        $zonestatus = [];
        for ($i = 0; $i < self::$ZONE_MAX; $i++) {
            if ($i >= $zone_count) {
                continue;
            }

            $ignored_fields[] = 'status.zoneStatus.' . $i . '.zoneId';

            $ident = 'Zone' . $i . '_Operate';
            $operate = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.operationStatus', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.operationStatus';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(zoneStatus.' . $i . '.operationStatus)=' . $operate, 0);
                $this->SaveValue($ident, (bool) $operate, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.operationStatus';
            }

            $ident = 'Zone' . $i . '_TemperatureNow';
            $temparatureNow = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.temparatureNow', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.temparatureNow';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.temparatureNow)=' . $temparatureNow, 0);
                $this->SaveValue($ident, (float) $temparatureNow, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.temparatureNow';
            }

            $ident = 'Zone' . $i . '_TargetHeatTemperature';
            $heatSet = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.heatSet', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.heatSet';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.heatSet)=' . $heatSet, 0);
                $this->SaveValue($ident, (float) $heatSet, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.heatSet';
            }

            $ident = 'Zone' . $i . '_EcoHeatAdjust';
            $ecoHeat = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.ecoHeat', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.ecoHeat';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.ecoHeat)=' . $ecoHeat, 0);
                $this->SaveValue($ident, (float) $ecoHeat, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.ecoHeat';
            }

            $ident = 'Zone' . $i . '_ComfortHeatAdjust';
            $comfortHeat = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.comfortHeat', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.comfortHeat';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.comfortHeat)=' . $comfortHeat, 0);
                $this->SaveValue($ident, (float) $comfortHeat, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.comfortHeat';
            }

            $ident = 'Zone' . $i . '_TargetCoolTemperature';
            $coolSet = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.coolSet', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.coolSet';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.coolSet)=' . $coolSet, 0);
                $this->SaveValue($ident, (float) $coolSet, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.coolSet';
            }

            $ident = 'Zone' . $i . '_EcoCoolAdjust';
            $ecoCool = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.ecoCool', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.ecoCool';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.ecoCool)=' . $ecoCool, 0);
                $this->SaveValue($ident, (float) $ecoCool, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.ecoCool';
            }

            $ident = 'Zone' . $i . '_ComfortCoolAdjust';
            $comfortCool = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.comfortCool', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.zoneStatus.' . $i . '.comfortCool';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.zoneStatus.' . $i . '.comfortCool)=' . $comfortCool, 0);
                $this->SaveValue($ident, (float) $comfortCool, $is_changed);
            } else {
                $missing_fields[] = 'status.zoneStatus.' . $i . '.comfortCool';
            }

            $elem = [];
            foreach (['heatMin', 'heatMax', 'coolMin', 'coolMax'] as $f) {
                $v = $this->GetArrayElem($jdata, 'status.zoneStatus.' . $i . '.' . $f, '', $fnd);
                if ($fnd) {
                    $used_fields[] = 'status.zoneStatus.' . $i . '.' . $f;
                    $elem[$f] = $v;
                } else {
                    $missing_fields[] = 'status.zoneStatus.' . $i . '.' . $f;
                }
            }
            $zonestatus[] = $elem;
        }
        $device_config['zonestatus'] = $zonestatus;

        $tankstatus = [];
        for ($i = 0; $i < self::$TANK_MAX; $i++) {
            if ($i >= $tank_count) {
                continue;
            }

            $ident = 'Tank' . $i . '_Operate';
            $operate = $this->GetArrayElem($jdata, 'status.tankStatus.' . $i . '.operationStatus', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.tankStatus.' . $i . '.operationStatus';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(tankStatus.' . $i . '.operationStatus)=' . $operate, 0);
                $this->SaveValue($ident, (bool) $operate, $is_changed);
            } else {
                $missing_fields[] = 'status.tankStatus.' . $i . '.operationStatus';
            }

            $ident = 'Tank' . $i . '_TemperatureNow';
            $temparatureNow = $this->GetArrayElem($jdata, 'status.tankStatus.' . $i . '.temparatureNow', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.tankStatus.' . $i . '.temparatureNow';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.tankStatus.' . $i . '.temparatureNow)=' . $temparatureNow, 0);
                $this->SaveValue($ident, (float) $temparatureNow, $is_changed);
            } else {
                $missing_fields[] = 'status.tankStatus.' . $i . '.temparatureNow';
            }

            $ident = 'Tank' . $i . '_TargetTemperature';
            $heatSet = $this->GetArrayElem($jdata, 'status.tankStatus.' . $i . '.heatSet', '', $fnd);
            if ($fnd) {
                $used_fields[] = 'status.tankStatus.' . $i . '.heatSet';
                $this->SendDebug(__FUNCTION__, '... ' . $ident . '(status.tankStatus.' . $i . '.heatSet)=' . $heatSet, 0);
                $this->SaveValue($ident, (float) $heatSet, $is_changed);
            } else {
                $missing_fields[] = 'status.tankStatus.' . $i . '.heatSet';
            }

            $elem = [];
            foreach (['heatMin', 'heatMax'] as $f) {
                $v = $this->GetArrayElem($jdata, 'status.tankStatus.' . $i . '.' . $f, '', $fnd);
                if ($fnd) {
                    $used_fields[] = 'status.tankStatus.' . $i . '.' . $f;
                    $elem[$f] = $v;
                } else {
                    $missing_fields[] = 'status.tankStatus.' . $i . '.' . $f;
                }
            }
            $tankstatus[] = $elem;
        }
        $device_config['tankstatus'] = $tankstatus;

        $this->SendDebug(__FUNCTION__, 'device_config=' . print_r($device_config, true), 0);
        $this->WriteAttributeString('device_config', json_encode($device_config));

        for ($i = 0; $i < 2; $i++) {
            $b = false;
            $s = ($i == 0 ? 'ignored' : 'unused') . ' variables';
            $vars = $this->GetArrayElemList($jdata);
            foreach ($vars as $var) {
                $ign = false;
                foreach ($ignored_fields as $ignored_field) {
                    if (preg_match('/' . $ignored_field . '/', $var)) {
                        $ign = true;
                        break;
                    }
                }
                if ($i == 0) {
                    $skip = $ign == false;
                } else {
                    $skip = in_array($var, $used_fields) || $ign;
                }
                if ($skip) {
                    continue;
                }
                if ($b == false) {
                    $b = true;
                    $this->SendDebug(__FUNCTION__, $s, 0);
                }
                $val = $this->GetArrayElem($jdata, $var, '', $fnd);
                if ($fnd) {
                    $this->SendDebug(__FUNCTION__, '... ' . $var . '="' . $val . '"', 0);
                }
            }
        }

        $this->SetValue('LastUpdate', $now);

        $this->AdjustActions();

        $this->SetUpdateInterval();
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
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

        $short_delay = $this->ReadPropertyInteger('short_action_refresh_delay');
        $long_delay = $this->ReadPropertyInteger('long_action_refresh_delay');
        $delay = $short_delay;

        $r = false;
        switch ($ident) {
            case 'Operate':
                $r = $this->SetOperate((bool) $value);
                $delay = $long_delay;
                break;
            case 'OperationMode':
                $r = $this->SetOperateMode((int) $value);
                break;
            case 'EcoMode':
                $r = $this->SetEcoMode((int) $value);
                break;
            case 'TargetTemperature':
                $r = $this->SetTargetTemperature((float) $value);
                break;
            case 'FanSpeed':
                $r = $this->SetFanSpeed((int) $value);
                break;
            case 'AirflowVertical':
                $r = $this->SetAirflowVertical((int) $value);
                break;
            case 'AirflowHorizontal':
                $r = $this->SetAirflowHorizontal((int) $value);
                break;
            case 'NanoeMode':
                $r = $this->SetNanoeMode((int) $value);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
            $this->MaintainTimer('UpdateStatus', $delay * 1000);
        }
    }

    private function CheckAction($func, $verbose)
    {
        return true;
    }

    private function AdjustActions()
    {
        $chg = false;

        /*
            $operate = $this->GetValue('Operate');
            $ecoMode = $this->GetValue('EcoMode');

            $chg |= $this->AdjustAction('OperationMode', $operate);

            $chg |= $this->AdjustAction('EcoMode', $operate);

            $b = $operate && $ecoMode == self::$ECO_MODE_AUTO;
            $chg |= $this->AdjustAction('FanSpeed', $b);

            $chg |= $this->AdjustAction('TargetTemperature', $operate);
         */

        if ($chg) {
            $this->ReloadForm();
        }
    }

    private function ControlDevice($func, array $parameters)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent/gateway', 0);
            $log_no_parent = $this->ReadPropertyBoolean('log_no_parent');
            if ($log_no_parent) {
                $this->LogMessage($this->Translate('Instance has no active gateway'), KL_WARNING);
            }
            return false;
        }

        $type = $this->ReadPropertyInteger('type');
        $device_id = $this->GetDeviceId();

        $sdata = [
            'DataID'     => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'CallerID'   => $this->InstanceID,
            'Function'   => 'ControlDevice',
            'Type'       => $type,
            'DeviceID'   => $device_id,
            'Parameters' => json_encode($parameters),
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        return isset($jdata['result']) && $jdata['result'] == 0;
    }
}
