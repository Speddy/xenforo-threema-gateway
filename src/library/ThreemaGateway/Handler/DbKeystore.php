<?php
/**
 * Splits keystore requests to model and DataWriter.
 *
 * @package ThreemaGateway
 * @author rugk
 * @copyright Copyright (c) 2015-2016 rugk
 * @license MIT
 */

/**
 * Initiates a real SDK keystore, but only redirects requests.
 */
class ThreemaGateway_Handler_DbKeystore extends Threema\MsgApi\PublicKeyStore
{
    /**
     * @var ThreemaGateway_Model_Keystore Model to keystore
     */
    private $model;

    /**
     * @var ThreemaGateway_DataWriter_Keystore DataWriter of keystore
     */
    private $dataWriter;

    /**
     * Initialises the key store.
     *
     * @return PhpFile
     */
    public function __construct()
    {
        $this->model = new ThreemaGateway_Model_Keystore();
        // $this->dataWriter = new ThreemaGateway_DataWriter_Keystore; //TODO: implement!
    }

    /**
     * Find public key. Returns null if the public key not found in the store.
     *
     * @param  string      $threemaId
     * @return null|string
     */
    public function findPublicKey($threemaId)
    {
        return $this->model->findPublicKey($threemaId);
    }

    /**
     * Save a public key.
     *
     * @param  string    $threemaId
     * @param  string    $publicKey
     * @throws Exception
     * @return bool
     */
    public function savePublicKey($threemaId, $publicKey)
    {
        return $this->model->savePublicKey($threemaId, $publicKey);
    }

    /**
     * Blocks requests to create method.
     *
     * @param  string  $path
     * @return PhpFile
     */
    public static function create($path)
    {
        return false;
    }
}
