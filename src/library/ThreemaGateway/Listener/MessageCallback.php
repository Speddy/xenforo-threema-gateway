<?php
/**
 * Threema message callback.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2015-2016 rugk
 * @license MIT
 */

/**
 * Listeners for custom activity when.
 */
class ThreemaGateway_Listener_MessageCallback
{
    /**
     * Receives text messages.
     *
     * You should set the "event hint" to "1" to only pass text messages to the
     * listener. Otherwise errors may happen.
     *
     * @param ThreemaGateway_Handler_Action_Callback $handler
     * @param ReceiveMessageResult                   $receiveResult
     * @param ThreemaMessage                         $threemaMsg
     * @param array                                  $output        [$logType, $debugLog, $publicLog]
     * @param bool                                   $saveMessage
     * @param bool                                   $debugMode
     *
     * @throws XenForo_Exception
     * @return array $hashes
     */
    public static function testCallbackPreSave(ThreemaGateway_Handler_Action_Callback $handler,
                                        Threema\MsgApi\Helpers\ReceiveMessageResult $receiveResult,
                                        Threema\MsgApi\Messages\ThreemaMessage $threemaMsg,
                                        &$output,
                                        &$saveMessage,
                                        $debugMode)
    {
        // for performance reasons you should check first, whether the message
        // meets your requirements respectively needs to be handled by the
        // listener.
        if ($threemaMsg->getText() != 'test message') {
            // IMPORTANT: Do not return false as this would cause all other
            // registered listeners to stop! You may not want to do that.
            return;
        }

        /** @var ThreemaGateway_Handler_Action_Receiver $receiver */
        $receiver = new ThreemaGateway_Handler_Action_Receiver;

        // first check whether message has already been saved to prevent replay attacks
        if ($receiver->messageIsReceived($receiveResult->getMessageId())) {
            throw new XenForo_Exception('Message already received!');
        }

        $handler->addLog($output, 'Message will not be saved to database!');

        // here you can do something with your text messages
        $saveMessage = false; // e.g. prevent saving!
    }

    /**
     * Receives text messages after saving them.
     *
     * @param ThreemaGateway_Handler_Action_Callback $handler
     * @param ReceiveMessageResult                   $receiveResult
     * @param ThreemaMessage                         $threemaMsg
     * @param array                                  $output        [$logType, $debugLog, $publicLog]
     * @param bool                                   $saveMessage
     * @param bool                                   $debugMode
     *
     * @return array $hashes
     */
    public static function testCallbackPostSave(ThreemaGateway_Handler_Action_Callback $handler,
                                        Threema\MsgApi\Helpers\ReceiveMessageResult $receiveResult,
                                        Threema\MsgApi\Messages\ThreemaMessage $threemaMsg,
                                        &$output,
                                        $messageSaved,
                                        $debugMode)
    {
        if (!$messageSaved) {
            $handler->addLog($output, 'This is the message, which has not been saved!');
        }
    }
}
