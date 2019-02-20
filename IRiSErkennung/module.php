<?

class IRiSErkennung extends IPSModule
{

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString('InstanceList', '[]');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $values = [];

        foreach (json_decode($this->ReadPropertyString('InstanceList'), true) as $object) {
            $newValue = [
                'id' => $object['id'],
                'expanded' => true
            ];

            if (IPS_InstanceExists($object['objectID'])) {
                $newValue['objectName'] = IPS_GetLocation($object['objectID']);
            }
            else if (IPS_VariableExists($object['objectID'])) {
                $newValue['objectName'] = IPS_GetName($object['objectID']);
            }
            else {
                $newValue['objectName'] = sprintf($this->Translate('Object #%u does not exist'), $object['objectID']);
            }
            $values[] = $newValue;
        }

        $form = [
            "elements" => [
                [
                    "type" => "Tree",
                    "name" => "InstanceList",
                    "caption" => "Detected Devices",
                    "columns" => [
                        [
                            "name" => "objectID",
                            "caption" => "Object ID",
                            "width" => "200px",
                            "save" => true,
                            "visible" => false
                        ], [
                            "name" => "objectName",
                            "caption" => "Instance/Variable",
                            "width" => "auto"
                        ], [
                            "name" => "detectedType",
                            "caption" => "Detected Type",
                            "width" => "200px",
                            "save" => true
                        ], [
                            "name" => "correct",
                            "caption" => "Correct?",
                            "width" => "70px",
                            "edit" => [
                                "type" => "CheckBox"
                            ]
                        ], [
                            "name" => "realType",
                            "caption" => "Real Type",
                            "width" => "200px",
                            "edit" => [
                                "type" => "Select",
                                "options" => [
                                    ["caption" => "-", "value" => 0],
                                    ["caption" => "Light (Actor)", "value" => 1],
                                    ["caption" => "Smoke Detector", "value" => 2],
                                    ["caption" => "Not relevant for IRiS", "value" => 3]
                                ]
                            ]
                        ], [
                            "name" => "remark",
                            "caption" => "Remark",
                            "width" => "300px",
                            "edit" => [
                                "type" => "ValidationTextBox"
                            ]
                        ]],
                    "values" => $values
                ]
            ],
            "actions" => [
                [
                    "type" => "RowLayout",
                    "items" => [
                        [
                            "type" => "Button",
                            "caption" => "Detect devices",
                            "onClick" => 'IE_Detect($id);',
                            "confirm" => "This operation will reset all current configuration and reset the list. Are you sure?"
                        ], [
                            "type" => "Button",
                            "caption" => "Send data to Symcon"
                        ]
                    ]
                ]
            ]
        ];

        return json_encode($form);
    }

    public function Detect() {
        $listValues = [];
        $rulesFile = json_decode(file_get_contents(__DIR__ . '/rules.json'), true);

        foreach (IPS_GetInstanceList() as $instanceID) {
            if (IPS_GetInstance($instanceID)['ModuleInfo']['ModuleType'] !== 3) {
                continue;
            }

            $instanceType = '';

            $instanceValues = [[
                'id' => $instanceID,
                'objectID' => $instanceID,
                'detectedType' => $this->Translate('Light (Actor)'),
                'correct' => true,
                'realType' => 0,
                'remark' => ''
            ]];

            foreach (IPS_GetChildrenIDs($instanceID) as $childID) {
                if (IPS_VariableExists($childID) && (IPS_GetObject($childID)['ObjectIdent'] !== '')) {
                    $this->SendDebug('Check Variable', IPS_GetLocation($childID), 0);
                    $type = '';
                    foreach ($rulesFile as $potentialType => $rulesList) {
                        $this->SendDebug('Check Rules List', json_encode($rulesList), 0);
                        foreach ($rulesList as $rules) {
                            $this->SendDebug('Check Rules', json_encode($rules), 0);
                            $checkNextRuleset = false;

                            // Check required rules
                            foreach ($rules['required'] as $requiredRule) {
                                $this->SendDebug('Check Required Rule', json_encode($requiredRule), 0);
                                if (!$this->CheckRule($childID, $requiredRule)) {
                                    $this->SendDebug('Check Required Rule', 'rule not fulfilled', 0);
                                    $checkNextRuleset = true;
                                    break;
                                }
                            }

                            if ($checkNextRuleset) {
                                continue;
                            }

                            // Check sufficient rules
                            foreach ($rules['sufficient'] as $sufficientRule) {
                                $this->SendDebug('Check Sufficient Rule', json_encode($sufficientRule), 0);
                                if ($this->CheckRule($childID, $sufficientRule)) {
                                    $this->SendDebug('Check Required Rule', 'rule fulfilled', 0);
                                    $type = $potentialType;
                                    if ($instanceType == '') {
                                        $instanceType = $type;
                                    }
                                    else if ($instanceType != $type) {
                                        $instanceType = 'Multiple types';
                                    }
                                    break;
                                }
                            }

                            if ($type != '') {
                                break;
                            }
                        }

                        if ($type != '') {
                            break;
                        }
                    }

                    if ($type == '') {
                        $type = 'Not relevant for IRiS';
                    }

                    $instanceValues[] = [
                        'id' => $childID,
                        'parent' => $instanceID,
                        'objectID' => $childID,
                        'detectedType' => $this->Translate($type),
                        'correct' => true,
                        'realType' => 0,
                        'remark' => ''
                    ];
                }
            }

            if ($instanceType == '') {
                $instanceType = 'Not relevant for IRiS';
            }

            $instanceValues[0]['detectedType'] = $this->Translate($instanceType);

            // Instance requires at least one status variable
            if (sizeof($instanceValues) > 1) {
                $listValues = array_merge($listValues, $instanceValues);
            }
        }

        IPS_SetProperty($this->InstanceID, 'InstanceList', json_encode($listValues));
        IPS_ApplyChanges($this->InstanceID);
    }

    private function CheckRule($variableID, $rule) {
        switch ($rule['type']) {
            case 'Switchable': {
                $variable = IPS_GetVariable($variableID);
                $switchable = ($variable['VariableCustomAction'] > 9999) || (($variable['VariableAction'] > 9999) && ($variable['VariableCustomAction'] == 0));
                return ($switchable == $rule['parameter']);
            }

            case 'VariableType': {
                $variable = IPS_GetVariable($variableID);
                return ($variable['VariableType'] == $rule['parameter']);
            }

            case 'NameContains': {
                $name = strtolower(IPS_GetName($variableID));
                return (strpos(strtolower($rule['parameter']), $name) !== false);
            }

            case 'InstanceNameContains': {
                $name = strtolower(IPS_GetName(IPS_GetParent($variableID)));
                return (strpos(strtolower($rule['parameter']), $name) !== false);
            }

            case 'HasProfile': {
                $variable = IPS_GetVariable($variableID);
                return ($variable['VariableProfile'] == $rule['parameter']) || ($variable['VariableCustomProfile'] == $rule['parameter']);
            }

            default:
                return false;
        }
    }


}

?>