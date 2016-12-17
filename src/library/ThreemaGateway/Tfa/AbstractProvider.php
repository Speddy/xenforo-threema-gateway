<?php
/**
 * Two factor authentication abstract provider for Threema Gateway.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2015-2016 rugk
 * @license MIT
 */

/**
 * Threema Gateway for two step authentication (TFA/2FA).
 */
abstract class ThreemaGateway_Tfa_AbstractProvider extends XenForo_Tfa_AbstractProvider
{
    /**
     * Variable, which will be filled with object of the Gateway Permissions class.
     *
     * @var ThreemaGateway_Handler_Permissions
     */
    protected $gatewayPermissions;

    /**
     * Variable, which will be filled with object of Gateway Settings later.
     *
     * @var ThreemaGateway_Handler_Settings
     */
    protected $gatewaySettings;

    /**
     * Variable, which will be filled with object of Gateway Handler later.
     *
     * It is private as {@link getSdk()} should be used. This makes sure the SDK
     * is only initialized when it is really needed.
     *
     * @var ThreemaGateway_Handler_PhpSdk
     */
    private $gatewaySdk = null;

    /**
     * Variable, which will be filled with object of Gateway Handler for server actions later.
     *
     * @var ThreemaGateway_Handler_Action_gatewayServer
     */
    protected $gatewayServer;

    /**
     * Variable, which will be filled with object of Gateway Handler for sending actions later.
     *
     * @var ThreemaGateway_Handler_Action_Sender
     */
    protected $gatewaySender;

    /**
     * Create provider.
     *
     * @param string $id Provider id
     */
    public function __construct($id)
    {
        parent::__construct($id);
        $this->gatewayPermissions = ThreemaGateway_Handler_Permissions::getInstance();
        $this->gatewaySettings    = new ThreemaGateway_Handler_Settings;
        $this->gatewayServer      = new ThreemaGateway_Handler_Action_GatewayServer;
        $this->gatewaySender      = new ThreemaGateway_Handler_Action_Sender;
    }

    /**
     * Return the title of the 2FA methode.
     */
    public function getTitle()
    {
        return new XenForo_Phrase('tfa_' . $this->_providerId);
    }

    /**
     * Return a description of the 2FA methode.
     */
    public function getDescription()
    {
        return new XenForo_Phrase('tfa_' . $this->_providerId . '_desc');
    }

    /**
     * Called when activated. Returns inital data of 2FA method.
     *
     * @param  array $user
     * @param  array $setupData
     * @return array
     */
    public function generateInitialData(array $user, array $setupData)
    {
        $this->gatewayPermissions->setUserId($user);

        return $setupData;
    }

    /**
     * Called when trying to verify user. Sends Threema message.
     *
     * @param  string $context
     * @param  array  $user
     * @param  string $ip
     * @param  array  $providerData
     * @return array
     */
    public function triggerVerification($context, array $user, $ip, array &$providerData)
    {
        $this->gatewayPermissions->setUserId($user);

        if (!$providerData) {
            throw new XenForo_Exception(new XenForo_Phrase('threemagw_this_tfa_mode_is_not_setup'));
        }

        return [];
    }

    /**
     * Called when trying to verify user. Shows input for secret and such things.
     *
     * @param  XenForo_View $view
     * @param  string       $context
     * @param  array        $user
     * @param  array        $providerData
     * @param  array        $triggerData
     * @return string       HTML code
     */
    public function renderVerification(XenForo_View $view, $context, array $user,
                                        array $providerData, array $triggerData)
    {
        $this->gatewayPermissions->setUserId($user);
    }

    /**
     * Called when trying to verify user. Checks whether a given secret is valid.
     *
     * @param string $context
     * @param array  $input
     * @param array  $user
     * @param array  $providerData
     *
     * @return bool
     */
    public function verifyFromInput($context, XenForo_Input $input, array $user, array &$providerData)
    {
        $this->gatewayPermissions->setUserId($user);

        // if we returned nothing, the child methods would fail if they properly check the result
        return true;
    }

    /**
     * Verifies the Treema ID formally after it was entered/changed.
     *
     * @param XenForo_Input $input
     * @param array         $user
     * @param array         $error
     *
     * @return array
     */
    public function verifySetupFromInput(XenForo_Input $input, array $user, &$error)
    {
        $this->gatewayPermissions->setUserId($user);

        /** @var array $providerData */
        $providerData = [];
        /** @var string $threemaid Threema ID given as parameter */
        $threemaid = $input->filterSingle('threemaid', XenForo_Input::STRING);

        //check Threema ID
        /** @var string $verifyError */
        $verifyError = '';
        if (ThreemaGateway_Handler_Validation::checkThreemaId($threemaid, 'personal', $verifyError)) {
            // correct
            $providerData['threemaid'] = $threemaid;
        } else {
            // incorrect
            $error[] = $verifyError;
            return [];
        }

        return $providerData;
    }

    /**
     * @return bool
     */
    public function canManage()
    {
        return true;
    }


    /**
     * States whether the setup is required.
     *
     * @return bool
     */
    public function requiresSetup()
    {
        return true;
    }

    /**
     * Called when setting up the provider before the setup page is shown.
     *
     * Currently this is not correctly implemented in XenForo.
     * See {@link https://xenforo.com/community/threads/1-5-documentation-for-two-step-authentication.102846/#post-1031047}
     *
     * @param XenForo_Input $input
     * @param array         $user
     * @param array         $error
     *
     * @return string HTML code
     */
    public function renderSetup(XenForo_View $view, array $user)
    {
        // redirected by ThreemaGateway_ControllerPublic_Account->actionTwoStepEnable
        // to handleManage.
    }

    /**
     * Handles settings of user.
     *
     * @param XenForo_Controller $controller
     * @param array              $user
     * @param array              $providerData
     *
     * @return null|ThreemaGateway_ViewPublic_TfaManage
     */
    final public function handleManage(XenForo_Controller $controller, array $user, array $providerData)
    {
        $this->gatewayPermissions->setUserId($user);

        /** @var XenForo_Input $input */
        $input   = $controller->getInput();
        /** @var Zend_Controller_Request_Http $request */
        $request = $controller->getRequest();
        /** @var XenForo_Session $session */
        $session = XenForo_Application::getSession();

        /** @var array|null $newProviderData */
        $newProviderData = null;
        /** @var array|null $newTriggerData */
        $newTriggerData  = null;
        /** @var bool $showSetup */
        $showSetup       = false;
        /** @var string $context */
        $context         = 'setup';
        /** @var string $threemaId */
        $threemaId       = '';

        /* Possible values of $context in order of usual appearance
        firstsetup      Input=Threema ID    User enables 2FA provider the first time.
        setupvalidation Input=2FA secret    Confirming 2FA in initial setup. (2FA input context: setup)

        setup           Input=Threema ID    UI to change settings of 2FA provider (shows when user clicks on "manage")
        update          Input=2FA secret    Confirming 2FA when settings changed. (2FA input context: setup)

        <not here>      Input=2FA c. only   Login page, where secret requested (2FA input context: login)

        The usual template is account_two_step_threemagw_conventional_manage, which includes
        account_two_step_threemagw_conventional every time when a 2FA secret is requested. If so
        this "subtemplate" always gets the context "setup".
        Only when logging in this template is included by itself and gets the context "login".
        */

        /* Ways this function can go: Input (filterSingle) --> action --> output ($context)
        Initial setup:
            no $providerData --> set default options & Threema ID --> firstsetup
            step = setup --> show page where user can enter 2FA secret --> setupvalidation
            <verification not done in method>

        Manage:
            ... (last else block) --> manage page: show setup --> setup
            manage --> show page where user can enter 2FA secret --> update
            confirm --> check 2FA secret & use settings if everything is right --> <null>

        Login:
            <not manmaged in this function>
        */

        if ($controller->isConfirmedPost()) {
            /** @var string $sessionKey the key for the temporary saved provider data. */
            $sessionKey = 'tfaData_' . $this->_providerId;

            //setup changed
            if ($input->filterSingle('manage', XenForo_Input::BOOLEAN)) {
                //provider data (settings) changed

                //read and verify options
                /** @var string $error */
                $error           = '';
                $newProviderData = $this->verifySetupFromInput($input, $user, $error);
                if (!$newProviderData) {
                    return $controller->responseError($error);
                }

                //check if there is a new ID, which would require revalidation
                if ($newProviderData['threemaid'] === $providerData['threemaid']) {
                    //the same Threema ID - use options instantly
                    $this->saveProviderOptions($user, $newProviderData);
                    return null;
                }

                //validation is required, revalidate this thing...
                $newTriggerData = $this->triggerVerification('setup', $user, $request->getClientIp(false), $newProviderData);

                $session->set($sessionKey, $newProviderData);
                $showSetup = true;
                $context   = 'update';
            } elseif ($input->filterSingle('confirm', XenForo_Input::BOOLEAN)) {
                //confirm setup validation

                //validate new provider data
                $newProviderData = $session->get($sessionKey);
                if (!is_array($newProviderData)) {
                    return null;
                }

                if (!$this->verifyFromInput('setup', $input, $user, $newProviderData)) {
                    return $controller->responseError(new XenForo_Phrase('two_step_verification_value_could_not_be_confirmed'));
                }

                //update provider as everything is okay
                $this->saveProviderOptions($user, $newProviderData);
                $session->remove($sessionKey);

                return null;
            } elseif ($input->filterSingle('step', XenForo_Input::BOOLEAN) == 'setup') {
                //show "real" setup (where you have to confirm validation)
                $context = 'setupvalidation';

                $newProviderData = $providerData;
                $session->set($sessionKey, $newProviderData);

                $newTriggerData = []; //is not used anyway...
                $showSetup      = true;

                $this->initiateSetupData($newProviderData, $newTriggerData);
            } else {
                throw new XenForo_Exception('Request invalid.');
            }
        } elseif (empty($providerData)) { //no previous settings
            //show first setup page (you can enter your Threema ID)
            $context = 'firstsetup';

            //set default values of options
            $providerData = $this->generateDefaultData();

            $threemaId = $this->getDefaultThreemaId($user);
        } else {
            //first manage page ($context = setup)
            $threemaId = $providerData['threemaid'];
        }

        /** @var array $viewParams parameters for XenForo_ControllerResponse_View */
        $viewParams = [
            'provider' => $this,
            'providerId' => $this->_providerId,
            'user' => $user,
            'providerData' => $providerData,
            'newProviderData' => $newProviderData,
            'newTriggerData' => $newTriggerData,
            'showSetup' => $showSetup,
            'context' => $context,
            'threemaId' => $threemaId
        ];
        $viewParams = $this->adjustViewParams($viewParams, $context, $user);

        return $controller->responseView(
            'ThreemaGateway_ViewPublic_TfaManage',
            'account_two_step_' . $this->_providerId . '_manage',
            $viewParams
        );
    }

    /**
     * Called when trying to verify user. Checks whether a user meets the
     * requirements.
     *
     * @param array  $user
     * @param object $error
     *
     * @return bool
     */
    public function meetsRequirements(array $user, &$error)
    {
        return true;
    }

    /**
     * Called when verifying displaying the choose 2FA mode.
     *
     * @param array  $user
     * @param object $error
     *
     * @return bool
     */
    public function canEnable()
    {
        // check necessary permissions
        return $this->gatewaySettings->isReady() && $this->gatewayPermissions->hasPermission('tfa');
    }

    /**
     * Called before the setup verification is shown.
     *
     * @param array $providerData
     * @param array $triggerData
     *
     * @return bool
     */
    abstract protected function initiateSetupData(array &$providerData, array &$triggerData);

    /**
     * Generates the default provider options at setup time before it is
     * displayed to the user.
     *
     * @return array
     */
    abstract protected function generateDefaultData();

    /**
    * Adjust the view params for managing the 2FA mode, e.g. add special
    * params needed by your template.
     *
     * @param array  $viewParams
     * @param string $context
     * @param array  $user
     *
     * @return array
     */
    abstract protected function adjustViewParams(array $viewParams, $context, array $user);

    /**
     * Saves new provider options to database.
     *
     * @param array $user
     * @param array $options
     */
    protected function saveProviderOptions($user, array $options)
    {
        /** @var XenForo_Model_Tfa $tfaModel */
        $tfaModel = XenForo_Model::create('XenForo_Model_Tfa');
        $tfaModel->enableUserTfaProvider($user['user_id'], $this->_providerId, $options);
    }

    /**
     * Resets the provider options to make sure the current 2FA verification
     * does not affect the next one.
     *
     * Please expand this if you have more values, which need to be reset, but
     * please do not forget to call the parent.
     *
     * @param string $context
     * @param array $providerData
     */
    protected function resetProviderOptionsForTrigger($context, array &$providerData)
    {
        unset($providerData['secret']);
        unset($providerData['secretGenerated']);
    }

    /**
     * Sends a message to a user and chooses automatically whether E2E mode can
     * be used.
     *
     * @param array $receiverId The Threema ID to who
     * @param array $xenPhrase  The message as a phrase, which should be sent
     */
    final protected function sendMessage($receiverId, XenForo_Phrase $xenPhrase)
    {
        // parse message
        $messageText = $xenPhrase->render();
        $messageText = ThreemaGateway_Helper_Emoji::parseUnicode($messageText);

        // send message
        return $this->gatewaySender->sendAuto($receiverId, $messageText);
    }

    /**
     * Generates a random numeric string consisting of digits.
     *
     * @param  int    $length The length of the string (default: 6)
     * @return string
     */
    final protected function generateRandomSecret($length = 6)
    {
        /** @var XenForo_Options $options */
        $options = XenForo_Application::getOptions();
        /** @var string $secret */
        $secret = '';

        try {
            //use own Sodium method
            $secret = ThreemaGateway_Helper_Random::getRandomNumeric($length);
        } catch (Exception $e) {
            // ignore errors
        }

        //use XenForo method as a fallback
        if (!$secret || !ctype_digit($secret)) {
            // ThreemaGateway_Helper_Random internally uses XenForo as a
            // fallback
            $random = ThreemaGateway_Helper_Random::getRandomBytes(4);

            // that's XenForo style
            $secret = (
                ((ord($random[0]) & 0x7f) << 24) |
                ((ord($random[1]) & 0xff) << 16) |
                ((ord($random[2]) & 0xff) << 8) |
                (ord($random[3]) & 0xff)
                    ) % pow(10, $length);
            $secret = str_pad($secret, $length, '0', STR_PAD_LEFT);
        }

        return $secret;
    }

    /**
     * Gets the default Threema ID using different sources.
     *
     * @param  array        $user
     * @return string|false
     */
    final protected function getDefaultThreemaId(array $user)
    {
        $options = XenForo_Application::getOptions();
        /** @var string $threemaId */
        $threemaId = '';

        if (array_key_exists('threemaid', $user['customFields']) &&
            $user['customFields']['threemaid'] != '') {

            //use custom user field
            $threemaId = $user['customFields']['threemaid'];
        }
        if ($threemaId == '' &&
            $options->threema_gateway_tfa_autolookupmail &&
            $user['user_state'] == 'valid') {

            //lookup mail
            try {
                $threemaId = $this->gatewaySdkServer->lookupMail($user['email']);
            } catch (Exception $e) {
                //ignore failures
            }
        }
        if ($threemaId == '' &&
            $options->threema_gateway_tfa_autolookupphone && //verify ACP permission
            $options->threema_gateway_tfa_autolookupphone['enabled'] &&
            $options->threema_gateway_tfa_autolookupphone['userfield'] && //verify ACP setup
            array_key_exists($options->threema_gateway_tfa_autolookupphone['userfield'], $user['customFields']) && //verify user field
            $user['customFields'][$options->threema_gateway_tfa_autolookupphone['userfield']] != '') {

            //lookup phone number
            try {
                $threemaId = $this->gatewaySdkServer->lookupPhone($user['customFields'][$options->threema_gateway_tfa_autolookupphone['userfield']]);
            } catch (Exception $e) {
                //ignore failure
            }
        }

        return $threemaId;
    }

    /**
     * Register a request for a new pending confirmation message.
     *
     * @param array      $providerData
     * @param int        $pendingType  What type of message request this is.
     *                                 You should use one of the PENDING_* constants
     *                                 in the Model (ThreemaGateway_Model_TfaPendingMessagesConfirmation).
     * @param array      $user
     * @param string|int $extraData    Any extra data you want to save in the database.
     *
     * @return bool
     */
    final protected function registerPendingConfirmationMessage(array $providerData, $pendingType, array $user, $extraData = null)
    {
        /** @var ThreemaGateway_Model_TfaPendingMessagesConfirmation $model */
        $model = XenForo_DataWriter::create('ThreemaGateway_Model_TfaPendingMessagesConfirmation');
        /** @var ThreemaGateway_DataWriter_TfaPendingMessagesConfirmation $dataWriter */
        $dataWriter = XenForo_DataWriter::create('ThreemaGateway_DataWriter_TfaPendingMessagesConfirmation');


        // check whether the same request is already issued, if so overwrite it
        if ($model->getPending($providerData['threemaid'], $this->_providerId, $pendingType)) {
            $dataWriter->setExistingData([
                ThreemaGateway_Model_TfaPendingMessagesConfirmation::DB_TABLE => [
                    'threema_id' => $providerData['threemaid'],
                    'provider_id' => $this->_providerId,
                    'pending_type' => $pendingType
                ]
            ]);
        }

        $dataWriter->set('threema_id', $providerData['threemaid']);
        $dataWriter->set('provider_id', $this->_providerId);
        $dataWriter->set('pending_type', $pendingType);

        $dataWriter->set('user_id', $user['user_id']);
        $dataWriter->set('session_id', XenForo_Application::getSession()->getSessionId());

        if ($extraData) {
            $dataWriter->set('extra_data', $extraData);
        }
        $dataWriter->set('expiry_date', $providerData['secretGenerated'] + $providerData['validationTime']);

        return $dataWriter->save();
    }

    /**
     * Register a request for a new pending confirmation message.
     *
     * @param array $providerData
     * @param int   $pendingType  What type of message request this is.
     *                            You should use one of the PENDING_* constants
     *                            in the Model (ThreemaGateway_Model_TfaPendingMessagesConfirmation).
     *
     * @return bool
     */
    final protected function unregisterPendingConfirmationMessage(array $providerData, $pendingType)
    {
        /** @var ThreemaGateway_DataWriter_TfaPendingMessagesConfirmation $dataWriter */
        $dataWriter = XenForo_DataWriter::create('ThreemaGateway_DataWriter_TfaPendingMessagesConfirmation');

        $dataWriter->setExistingData([
            ThreemaGateway_Model_TfaPendingMessagesConfirmation::DB_TABLE => [
                'threema_id' => $providerData['threemaid'],
                $this->_providerId,
                'pending_type' => $pendingType
            ]
        ]);

        return $dataWriter->delete();
    }

    /**
     * Verifies whether the new secret is valid considering timing information
     * about the current secret.
     *
     * @param  array $providerData
     * @return bool
     */
    final protected function verifySecretIsInTime(array $providerData)
    {
        if (empty($providerData['secret']) || empty($providerData['secretGenerated'])) {
            return false;
        }

        if ((XenForo_Application::$time - $providerData['secretGenerated']) > $providerData['validationTime']) {
            return false;
        }

        return true;
    }

    /**
     * Verifies whether the new secret is valid by comparing it with the previous
     * secret.
     *
     * @param  array  $providerData
     * @param  string $newSecret      the new secret, which is currently checked/verified
     * @return bool
     */
    final protected function verifyNoReplayAttack(array $providerData, $newSecret)
    {
        if (!empty($providerData['lastSecret']) && $this->stringCompare($providerData['lastSecret'], $newSecret)) {
            // prevent replay attacks: once the secret has been used, don't allow it to be used in the slice again
            if (!empty($providerData['lastSecretTime']) && (XenForo_Application::$time - $providerData['lastSecretTime']) < $providerData['validationTime']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Updates the data used by {@see verifyNoReplayAttack()} to prevent replay attacks.
     *
     * @param  array      $providerData
     * @param  string|int $secret         The currently processed (& verified) secret
     * @return bool
     */
    final protected function updateReplayCheckData(array &$providerData, $secret)
    {
        // save current secret for later replay attack checks
        $providerData['lastSecret']     = $secret;
        $providerData['lastSecretTime'] = XenForo_Application::$time;
        unset($providerData['secret']);
        unset($providerData['secretGenerated']);

        return true;
    }

    /**
     * Parse a given number of seconds to a human-readble format.
     *
     * @param  int    $seconds
     * @return string
     */
    final protected function parseTime($seconds)
    {
        /** @var string $displayTime output/result */
        $displayTime = '';
        /** @var int $minutes */
        $minutes = floor($seconds / 60);
        /** @var int $hours */
        $hours = floor($minutes / 60);

        if ($minutes <= 1) {
            $displayTime = new XenForo_Phrase('threemagw_one_minute');
        } elseif ($minutes < 60) {
            $displayTime = $minutes . ' ' . new XenForo_Phrase('threemagw_minutes');
        // hours below (more than 60 minutes)
        } elseif ($minutes <= 61) {
            $displayTime = new XenForo_Phrase('threemagw_one_hour');
        } elseif ($hours <= 1) {
            $displayTime = $hours . ' ' . new XenForo_Phrase('threemagw_hours');
        // days below (more than 1 hour)
        } elseif ($hours <= 24) {
            $displayTime = new XenForo_Phrase('threemagw_one_day');
        } else {
            $displayTime = floor($hours / 24) . ' ' . new XenForo_Phrase('threemagw_days');
        }

        return (string)$displayTime;
    }

    /**
     * Checks whether a string is the same (returns true) or not (returns false).
     *
     * This should be used for security-sensitive things as it checks the
     * strings constant-time.
     *
     * @param  string $string1
     * @param  string $string2
     * @return bool
     */
    final protected function stringCompare($string1, $string2)
    {
        return $this->getSdk()->getCryptTool()->stringCompare($string1, $string2);
    }

    /**
     * Returns the PHP SDK object.
     *
     * @param ThreemaGateway_Handler_PhpSdk
     */
    final protected function getSdk()
    {
        if ($this->gatewaySdk === null) {
            $this->gatewaySdk = ThreemaGateway_Handler_PhpSdk::getInstance($this->gatewaySettings);
        }

        return $this->gatewaySdk;
    }
}
