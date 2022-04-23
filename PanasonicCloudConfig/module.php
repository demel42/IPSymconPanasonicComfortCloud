<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class PanasonicCloudConfig extends IPSModule
{
    use PanasonicCloud\StubsCommonLib;
    use PanasonicCloudLocalLib;

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

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->SetStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = ['ImportCategoryID'];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid >= 10000) {
                $this->RegisterReference($oid);
            }
        }

        if ($this->CheckConfiguration() != false) {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }

    private function SetLocation()
    {
        $catID = $this->ReadPropertyInteger('ImportCategoryID');
        $tree_position = [];
        if ($catID >= 10000 && IPS_ObjectExists($catID)) {
            $tree_position[] = IPS_GetName($catID);
            $parID = IPS_GetObject($catID)['ParentID'];
            while ($parID > 0) {
                if ($parID > 0) {
                    $tree_position[] = IPS_GetName($parID);
                }
                $parID = IPS_GetObject($parID)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        $this->SendDebug(__FUNCTION__, 'tree_position=' . print_r($tree_position, true), 0);
        return $tree_position;
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
                        $type = $this->deviceType2str($deviceType);

                        $instanceID = 0;
                        foreach ($instIDs as $instID) {
                            if (IPS_GetProperty($instID, 'guid') == $deviceGuid) {
                                $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode(IPS_GetName($instID)) . ' (' . $instID . ')', 0);
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
                                'location'      => $this->SetLocation(),
                                'info'          => $type . ' ' . $deviceModule,
                                'configuration' => [
                                    'guid'          => (string) $deviceGuid,
                                    'type'          => $deviceType,
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
            $type = $this->deviceType2str($deviceType);

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

    protected function GetFormElements()
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

    protected function GetFormActions()
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
