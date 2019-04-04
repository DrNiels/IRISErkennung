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

        $rulesFile = json_decode(file_get_contents(__DIR__ . '/rules.json'), true);
        $typeOptions = [["caption" => "-", "value" => "-"]];
        foreach($rulesFile as $type => $rules) {
            $typeOptions[] = ["caption" => $type, "value" => $type];
        }

        $typeOptions[] = ["caption" => "No type", "value" => "No type"];
        $typeOptionsNoDash = $typeOptions;
        array_shift($typeOptionsNoDash);

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

            if ((($object['detectedType'] === 'No type') || ($object['detectedType'] === $this->Translate('No type'))) && ($object['correct'])) {
                $newValue['rowColor'] = '#DFDFDF';
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
                                "options" => $typeOptions
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
                            "confirm" => "This operation will reset all current configuration and recreate the list. Are you sure?"
                        ], [
                            "type" => "Button",
                            "caption" => "Send data to Symcon",
                            "onClick" => 'IE_SendData($id);',
                            "confirm" => "This operation will send the device list with its configuration and your annotations to Symcon to verify and improve the automatical identification of instances within the research project IRiS. Continue?"
                        ], [
                            "type" => "Button",
                            "caption" => "Open IRiS homepage",
                            "onClick" => 'echo "https://www.symcon.de/forschung/iris";'
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
                'detectedType' => '',
                'correct' => true,
                'realType' => '-',
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
                        $type = 'No type';
                    }

                    $instanceValues[] = [
                        'id' => $childID,
                        'parent' => $instanceID,
                        'objectID' => $childID,
                        'detectedType' => $this->Translate($type),
                        'correct' => true,
                        'realType' => '-',
                        'remark' => ''
                    ];
                }
            }

            if ($instanceType == '') {
                $instanceType = 'No type';
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

    public function SendData() {
        $library = json_decode(file_get_contents(__DIR__ . '/../library.json'), true);
        $data = base64_encode(gzcompress($this->GenerateEvaluationData()));

        $response = @file_get_contents('https://tzuhj5rqvd.execute-api.eu-west-1.amazonaws.com/dev/?version=' . $library['version'], false, stream_context_create([
            'http' => [
                'method'           => 'POST',
                'header'           => "Content-type: application/json\r\nConnection: close\r\nContent-length: " . strlen($data) . "\r\n",
                'content'          => $data,
                'ignore_errors'    => true
            ],
        ]));

        if ($response === false) {
            echo "Failed: \n" . print_r(error_get_last(), true);
        } elseif (json_decode($response, true) !== "OK") {
            $this->SendDebug('Request Sync Failed', $response, 0);
            echo "Failed: \n" . $response;
        } else {
            echo $this->Translate('Done');
        }
    }

    public function GenerateEvaluationFile() {
        $data = $this->GenerateEvaluationData();
        file_put_contents(__DIR__ . '/../evaluation.json', $data);

        echo $this->Translate('Done');
    }

    private function GenerateEvaluationData() {
        $result = [
            'numberOfInstances' => sizeof(IPS_GetInstanceList()),
            'instances' => []
        ];

        foreach(json_decode($this->ReadPropertyString('InstanceList'), true) as $entry) {
            // Entry describes an instance
            if (!isset($entry['parent']) || $entry['parent'] == 0) {
                // Skip deleted objects
                if (!IPS_InstanceExists($entry['objectID'])) {
                    continue;
                }

                // If the instance has no type, skip it
                if (($entry['detectedType'] == 'No type') && ($entry['correct'])) {
                    continue;
                }

                $configuration = @IPS_GetConfiguration($entry['objectID']);

                if (is_string($configuration)) {
                    $configuration = json_decode($configuration, true);
                }

                $result['instances'][$entry['id']] = [
                    'object' => IPS_GetObject($entry['objectID']),
                    'instance' => IPS_GetInstance($entry['objectID']),
                    'configuration' => $configuration,
                    'detectedType' => $entry['detectedType'],
                    'correct' => $entry['correct'],
                    'realType' => $entry['realType'],
                    'remark' => $entry['remark'],
                    'variables' => []
                ];
            }
            // Others are variables
            else {
                // Skip deleted objects
                if (!IPS_VariableExists($entry['objectID'])) {
                    continue;
                }

                // Skip variable if there is no parent. This is possible if the parent had no type
                if (!isset($result['instances'][$entry['parent']]['variables'])) {
                    continue;
                }

                $result['instances'][$entry['parent']]['variables'][$entry['id']] = [
                    'object' => IPS_GetObject($entry['objectID']),
                    'variable' => IPS_GetVariable($entry['objectID']),
                    'profile' => $this->GetProfile($entry['objectID']),
                    'detectedType' => $entry['detectedType'],
                    'correct' => $entry['correct'],
                    'realType' => $entry['realType'],
                    'remark' => $entry['remark']
                ];
            }
        }

        return json_encode($result);
    }

    private function GetProfile($variableID) {
        $variable = IPS_GetVariable($variableID);
        $profileName = $variable['VariableCustomProfile'];
        if ($profileName == '') {
            $profileName = $variable['VariableProfile'];
        }

        if (IPS_VariableProfileExists($profileName)) {
            return IPS_GetVariableProfile($profileName);
        }
        else {
            return null;
        }
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
                if (is_array($rule['parameter'])) {
                    foreach ($rule['parameter'] as $possibleType) {
                        if ($variable['VariableType'] == $possibleType) {
                            return true;
                        }
                    }
                    return false;
                }
                else {
                    return ($variable['VariableType'] == $rule['parameter']);
                }
            }

            case 'NameContains': {
                $name = strtolower(IPS_GetName($variableID));
                if (is_array($rule['parameter'])) {
                    foreach ($rule['parameter'] as $possibleName) {
                        if (strpos($name, strtolower($possibleName)) !== false) {
                            return true;
                        }
                    }
                    return false;
                }
                else {
                    return (strpos($name, strtolower($rule['parameter'])) !== false);
                }
            }

            case 'InstanceNameContains': {
                $name = strtolower(IPS_GetName(IPS_GetParent($variableID)));
                if (is_array($rule['parameter'])) {
                    foreach ($rule['parameter'] as $possibleName) {
                        if (strpos($name, strtolower($possibleName)) !== false) {
                            return true;
                        }
                    }
                    return false;
                }
                else {
                    return (strpos($name, strtolower($rule['parameter'])) !== false);
                }
            }

            case 'HasProfile': {
                $variable = IPS_GetVariable($variableID);
                if (is_array($rule['parameter'])) {
                    foreach ($rule['parameter'] as $possibleProfile) {
                        if (($variable['VariableProfile'] == $rule['parameter']) || ($variable['VariableCustomProfile'] == $rule['parameter'])) {
                            return true;
                        }
                    }
                    return false;
                }
                else {
                    return ($variable['VariableProfile'] == $rule['parameter']) || ($variable['VariableCustomProfile'] == $rule['parameter']);
                }
            }

            case 'HasProfileSuffix': {
                $profile = $this->GetProfile($variableID);
                if (!isset($profile['Suffix'])) {
                    return false;
                }
                $suffix = $profile['Suffix'];
                if (isset($rule['trim']) && $rule['trim']) {
                    $suffix = trim($suffix);
                }
                return ($suffix == $rule['parameter']);
            }

            default:
                return false;
        }
    }


}

?>