<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudConfig extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('ImportCategoryID', 0);

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->ConnectParent('{FA9B3ACC-2056-06B5-4DA6-0C7D375A89FB}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = ['ImportCategoryID'];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function getConfiguratorValues()
    {
        $entries = [];

        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return $entries;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            return $entries;
        }

        $catID = $this->ReadPropertyInteger('ImportCategoryID');

        // an PanasonicCloudIO
        $sdata = [
            'DataID'   => '{34871A78-6B14-6BD4-3BE2-192BCB0B150D}',
            'Function' => 'GetGroups'
        ];
        $this->SendDebug(__FUNCTION__, 'SendDataToParent(' . print_r($sdata, true) . ')', 0);
        $data = $this->SendDataToParent(json_encode($sdata));
        $groups = $data != '' ? json_decode($data, true) : '';
        $this->SendDebug(__FUNCTION__, 'groups=' . print_r($groups, true), 0);

        $guid = '{A972DA17-4989-9CAD-2680-0CB492645050}';
        $instIDs = IPS_GetInstanceListByModuleID($guid);

        if (is_array($groups)) {
            foreach ($groups as $group) {
                $this->SendDebug(__FUNCTION__, 'group=' . print_r($group, true), 0);
                $groupName = $this->GetArrayElem($group, 'groupName', '');
                $devices = $this->GetArrayElem($group, 'deviceList', '');
                if ($devices != '') {
                    foreach ($devices as $device) {
                        $deviceGuid = $this->GetArrayElem($device, 'deviceGuid', '');
                        $deviceName = $this->GetArrayElem($device, 'deviceName', '');
                        $deviceType = $this->GetArrayElem($device, 'deviceType', 0);
                        $deviceModule = $this->GetArrayElem($device, 'deviceModuleNumber', '');
                        $type = $this->DeviceType2String($deviceType);

                        $instanceID = 0;
                        foreach ($instIDs as $instID) {
                            if (IPS_GetProperty($instID, 'guid') == $deviceGuid) {
                                $this->SendDebug(__FUNCTION__, 'device found: ' . IPS_GetName($instID) . ' (' . $instID . ')', 0);
                                $instanceID = $instID;
                                break;
                            }
                        }
                        $entry = [
                            'instanceID'      => $instanceID,
                            'name'            => $groupName . ' - ' . $deviceName,
                            'type'            => $type,
                            'model'           => $deviceModule,
                            'guid'            => $deviceGuid,
                            'create'          => [
                                'moduleID'      => $guid,
                                'location'      => $this->GetConfiguratorLocation($catID),
                                'info'          => $type . ' ' . $deviceModule,
                                'configuration' => [
                                    'guid'          => (string) $deviceGuid,
                                    'type'          => (int) $deviceType,
                                    'model'         => (string) $deviceModule,
                                ],
                            ],
                        ];

                        $entries[] = $entry;
                        $this->SendDebug(__FUNCTION__, 'entry=' . print_r($entry, true), 0);
                    }
                }
            }
        }
        foreach ($instIDs as $instID) {
            $fnd = false;
            foreach ($entries as $entry) {
                if ($entry['instanceID'] == $instID) {
                    $fnd = true;
                    break;
                }
            }
            if ($fnd) {
                continue;
            }

            $name = IPS_GetName($instID);
            $deviceType = IPS_GetProperty($instID, 'type');
            $deviceModule = IPS_GetProperty($instID, 'model');
            $deviceGuid = IPS_GetProperty($instID, 'guid');
            $type = $this->DeviceType2String($deviceType);

            $entry = [
                'instanceID'      => $instID,
                'name'            => $name,
                'type'            => $type,
                'model'           => $deviceModule,
                'guid'            => $deviceGuid,
            ];

            $entries[] = $entry;
            $this->SendDebug(__FUNCTION__, 'missing entry=' . print_r($entry, true), 0);
        }

        return $entries;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Panasonic ComfortCloud Configurator');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'SelectCategory',
            'name'    => 'ImportCategoryID',
            'caption' => 'category for devices to be created'
        ];

        $entries = $this->getConfiguratorValues();
        $formElements[] = [
            'type'     => 'Configurator',
            'name'     => 'devices',
            'caption'  => 'Devices',
            'rowCount' => count($entries),

            'add'     => false,
            'delete'  => false,
            'columns' => [
                [
                    'caption' => 'Name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Type',
                    'name'    => 'type',
                    'width'   => '200px'
                ],
                [
                    'caption' => 'Model',
                    'name'    => 'model',
                    'width'   => '300px'
                ],
                [
                    'caption' => 'Device-ID',
                    'name'    => 'guid',
                    'width'   => '350px'
                ],
            ],
            'values' => $entries,
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

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }
}
