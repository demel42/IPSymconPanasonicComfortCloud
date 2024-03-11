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

    public static $INSIDE_CLEANING_UNAVAIL = 0;
    public static $INSIDE_CLEANING_OFF = 1;
    public static $INSIDE_CLEANING_ON = 2;

    // Aquarea smart cloud

    public static $OPERATION_MODE_ASC_OFF = 0;
    public static $OPERATION_MODE_ASC_HEAT = 1;
    public static $OPERATION_MODE_ASC_COOL = 2;
    public static $OPERATION_MODE_ASC_AUTO_HEAT = 3;
    public static $OPERATION_MODE_ASC_AUTO_COOL = 4;

    public static $WORKING_MODE_ASC_NORMAL = 0;
    public static $WORKING_MODE_ASC_ECO = 1;
    public static $WORKING_MODE_ASC_COMFORT = 2;

    public static $DEVICE_ACTIVITY_ASC_OFF = 0;
    public static $DEVICE_ACTIVITY_ASC_IDLE = 1;
    public static $DEVICE_ACTIVITY_ASC_HEATING = 2;
    public static $DEVICE_ACTIVITY_ASC_COOLING = 3;
    public static $DEVICE_ACTIVITY_ASC_HEATING_WATER = 4;

    public static $QUIET_MODE_ASC_OFF = 0;
    public static $QUIET_MODE_ASC_LEVEL1 = 1;
    public static $QUIET_MODE_ASC_LEVEL2 = 2;
    public static $QUIET_MODE_ASC_LEVEL3 = 3;

    public static $POWER_MODE_ASC_OFF = 0;
    public static $POWER_MODE_ASC_30M = 1;
    public static $POWER_MODE_ASC_60M = 2;
    public static $POWER_MODE_ASC_90M = 3;

    public static $DEFROST_MODE_ASC_OFF = 0;
    public static $DEFROST_MODE_ASC_ON = 1;

    public static $HOLIDAY_TIMER_ASC_OFF = 0;
    public static $HOLIDAY_TIMER_ASC_ON = 1;

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

        $associations = [
            ['Wert' => self:: $INSIDE_CLEANING_UNAVAIL, 'Name' => $this->Translate('Unavail'), 'Farbe' => -1],
            ['Wert' => self:: $INSIDE_CLEANING_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self:: $INSIDE_CLEANING_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.InsideCleaning', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('PanasonicCloud.Temperature', VARIABLETYPE_FLOAT, ' °C', 16, 30, 0.5, 1, 'Temperature', '', $reInstall);

        $this->CreateVarProfile('PanasonicCloud.Energy', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', '', $reInstall);

        $associations = [
            ['Wert' => self::$OPERATION_MODE_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_ASC_HEAT, 'Name' => $this->Translate('Heat'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_ASC_COOL, 'Name' => $this->Translate('Cool'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_ASC_AUTO_HEAT, 'Name' => $this->Translate('Automatic heating'), 'Farbe' => -1],
            ['Wert' => self::$OPERATION_MODE_ASC_AUTO_COOL, 'Name' => $this->Translate('Automatic cooling'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.OperationMode_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$WORKING_MODE_ASC_NORMAL, 'Name' => $this->Translate('Normal'), 'Farbe' => -1],
            ['Wert' => self::$WORKING_MODE_ASC_ECO, 'Name' => $this->Translate('Eco'), 'Farbe' => -1],
            ['Wert' => self::$WORKING_MODE_ASC_COMFORT, 'Name' => $this->Translate('Comfort'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.WorkingMode_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$DEVICE_ACTIVITY_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$DEVICE_ACTIVITY_ASC_IDLE, 'Name' => $this->Translate('Idle'), 'Farbe' => -1],
            ['Wert' => self::$DEVICE_ACTIVITY_ASC_HEATING, 'Name' => $this->Translate('Heating'), 'Farbe' => -1],
            ['Wert' => self::$DEVICE_ACTIVITY_ASC_COOLING, 'Name' => $this->Translate('Cooling'), 'Farbe' => -1],
            ['Wert' => self::$DEVICE_ACTIVITY_ASC_HEATING_WATER, 'Name' => $this->Translate('Heating water'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.DeviceActivity_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$QUIET_MODE_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$QUIET_MODE_ASC_LEVEL1, 'Name' => $this->Translate('Level 1'), 'Farbe' => -1],
            ['Wert' => self::$QUIET_MODE_ASC_LEVEL2, 'Name' => $this->Translate('Level 2'), 'Farbe' => -1],
            ['Wert' => self::$QUIET_MODE_ASC_LEVEL3, 'Name' => $this->Translate('Level 3'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.QuietMode_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$POWER_MODE_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$POWER_MODE_ASC_30M, 'Name' => $this->Translate('30m on'), 'Farbe' => -1],
            ['Wert' => self::$POWER_MODE_ASC_60M, 'Name' => $this->Translate('60m on'), 'Farbe' => -1],
            ['Wert' => self::$POWER_MODE_ASC_90M, 'Name' => $this->Translate('90m on'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.PowerMode_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$DEFROST_MODE_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$DEFROST_MODE_ASC_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.DefrostMode_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$HOLIDAY_TIMER_ASC_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$HOLIDAY_TIMER_ASC_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('PanasonicCloud.HolidayTimer_ASC', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);
    }

    public static $DEVICE_TYPE_HEAT_PUMP = 2;
    public static $DEVICE_TYPE_AIR_CONDITIONER = 3;

    private function DeviceTypeMapping()
    {
        return [
            self::$DEVICE_TYPE_HEAT_PUMP => [
                'caption' => 'Heat pump',
            ],
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
