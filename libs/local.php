<?php

declare(strict_types=1);

trait PanasonicCloudLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NOLOGIN = IS_EBASE + 14;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public static $OPERATION_MODE_AUTO = 0;
    public static $OPERATION_MODE_DRY = 1;
    public static $OPERATION_MODE_COOL = 2;
    public static $OPERATION_MODE_HEAT = 3;
    public static $OPERATION_MODE_FAN = 4;

    public static $ECO_MODE_AUTO = 0;
    public static $ECO_MODE_POWERFUL = 1;
    public static $ECO_MODE_QUIET = 2;

    public static $FAN_SPEED_AUTO = 0;
    public static $FAN_SPEED_LOW = 1;
    public static $FAN_SPEED_LOW_MIN = 2;
    public static $FAN_SPEED_MID = 3;
    public static $FAN_SPEED_HIGH_MID = 4;
    public static $FAN_SPEED_HIGH = 5;

    public static $AIRFLOW_VERTICAL_AUTO = -1;
    public static $AIRFLOW_VERTICAL_UP = 0;
    public static $AIRFLOW_VERTICAL_DOWN = 1;
    public static $AIRFLOW_VERTICAL_MID = 2;
    public static $AIRFLOW_VERTICAL_UP_MID = 3;
    public static $AIRFLOW_VERTICAL_DOWN_MID = 4;
    public static $AIRFLOW_VERTICAL_IN_MOTION = 5;

    public static $AIRFLOW_HORIZONTAL_AUTO = -1;
    public static $AIRFLOW_HORIZONTAL_RIGHT = 0;
    public static $AIRFLOW_HORIZONTAL_LEFT = 1;
    public static $AIRFLOW_HORIZONTAL_MID = 2;
    public static $AIRFLOW_HORIZONTAL_RIGHT_MID = 4;
    public static $AIRFLOW_HORIZONTAL_LEFT_MID = 5;

    public static $AIRFLOW_AUTOMODE_ON = 0;
    public static $AIRFLOW_AUTOMODE_OFF = 1;
    public static $AIRFLOW_AUTOMODE_VERTICAL = 2;
    public static $AIRFLOW_AUTOMODE_HORIZONTAL = 3;

    public static $NANOE_MODE_UNAVAIL = 0;
    public static $NANOE_MODE_OFF = 1;
    public static $NANOE_MODE_ON = 2;
    public static $NANOE_MODE_MODE_G = 3;
    public static $NANOE_MODE_ALL = 4;

    public static $DATA_MODE_DAY = 0;
    public static $DATA_MODE_WEEK = 1;
    public static $DATA_MODE_MONTH = 2;
    public static $DATA_MODE_YEAR = 4;

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.Operate', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$OPERATION_MODE_AUTO, 'Name' => $this->Translate('Automatic'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_DRY, 'Name' => $this->Translate('Dry'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_COOL, 'Name' => $this->Translate('Cool'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_HEAT, 'Name' => $this->Translate('Heat'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_FAN, 'Name' => $this->Translate('Fan'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ECO_MODE_AUTO, 'Name' => $this->Translate('Automatic'), 'Farbe' => -1],
            ['Wert' => self::$ECO_MODE_POWERFUL, 'Name' => $this->Translate('Powerful'), 'Farbe' => -1],
            ['Wert' => self::$ECO_MODE_QUIET, 'Name' => $this->Translate('Quiet'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.EcoMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$FAN_SPEED_AUTO, 'Name' => $this->Translate('Automatic'), 'Farbe' => -1],
            ['Wert' => self::$FAN_SPEED_LOW, 'Name' => $this->Translate('Low'), 'Farbe' => -1],
            ['Wert' => self::$FAN_SPEED_LOW_MIN, 'Name' => $this->Translate('Low middle'), 'Farbe' => -1],
            ['Wert' => self::$FAN_SPEED_MID, 'Name' => $this->Translate('Middle'), 'Farbe' => -1],
            ['Wert' => self::$FAN_SPEED_HIGH_MID, 'Name' => $this->Translate('High middle'), 'Farbe' => -1],
            ['Wert' => self::$FAN_SPEED_HIGH, 'Name' => $this->Translate('High'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.FanSpeed', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$AIRFLOW_VERTICAL_AUTO, 'Name' => $this->Translate('Automatic'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_UP, 'Name' => $this->Translate('Up'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_DOWN, 'Name' => $this->Translate('Down'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_MID, 'Name' => $this->Translate('Middle'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_UP_MID, 'Name' => $this->Translate('Up middle'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_DOWN_MID, 'Name' => $this->Translate('Down middle'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_VERTICAL_IN_MOTION, 'Name' => $this->Translate('In motion'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.AirflowVertical', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$AIRFLOW_HORIZONTAL_AUTO, 'Name' => $this->Translate('Automatic'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_HORIZONTAL_LEFT, 'Name' => $this->Translate('Left'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_HORIZONTAL_RIGHT, 'Name' => $this->Translate('Right'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_HORIZONTAL_MID, 'Name' => $this->Translate('Middle'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_HORIZONTAL_RIGHT_MID, 'Name' => $this->Translate('Right middle'), 'Farbe' => -1],
            ['Wert' => self::$AIRFLOW_HORIZONTAL_LEFT_MID, 'Name' => $this->Translate('Left middle'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.AirflowHorizontal', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self:: $NANOE_MODE_UNAVAIL, 'Name' => $this->Translate('Unavail'), 'Farbe' => -1],
            ['Wert' => self:: $NANOE_MODE_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self:: $NANOE_MODE_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1],
            ['Wert' => self:: $NANOE_MODE_MODE_G, 'Name' => $this->Translate('Mode G'), 'Farbe' => -1],
            ['Wert' => self:: $NANOE_MODE_ALL, 'Name' => $this->Translate('All'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.NanoeMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('PanasonicCloud.Temperature', VARIABLETYPE_FLOAT, '', 16, 30, 0.5, 1, 'Temperature', '', $reInstall);

        $this->CreateVarProfile('PanasonicCloud.Energy', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);
    }

    public static $DEVICE_TYPE_AIR_CONDITIONER = 3;

    private function DeviceTypeMapping()
    {
        return [
            self::$DEVICE_TYPE_AIR_CONDITIONER => [
                'caption' => 'Air conditioner',
            ],
        ];
    }

    private function DeviceTypeAsOptions()
    {
        $maps = $this->DeviceTypeMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $e['caption'],
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function DeviceType2String($deviceType)
    {
        $maps = $this->DeviceTypeMapping();
        if (isset($maps[$deviceType])) {
            $ret = $this->Translate($maps[$deviceType]['caption']);
        } else {
            $ret = $this->Translate('Unknown type') . ' ' . $deviceType;
        }
        return $ret;
    }
}
