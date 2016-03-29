<?php

namespace Drupal\sagepay_payment\Sagepay;

/**
 * The Sage Pay Server integration method API
 */
final class ServerApi extends AbstractApi
{

    /**
     * The Server URL for integration methods
     *
     * @var string
     */
    private $_vpsServerUrl;

    /**
     * Integration method
     *
     * @var string
     */
    protected $integrationMethod = SAGEPAY_SERVER;

    /**
     * Constructor for ServerApi
     *
     * @param Settings $config
     */
    public function __construct(Settings $config)
    {
        parent::__construct($config);
        $this->_vpsServerUrl = $config->getPurchaseUrl('server');
        $this->mandatory = array(
            'VPSProtocol',
            'TxType',
            'Vendor',
            'VendorTxCode',
            'Amount',
            'Currency',
            'Description',
            'NotificationURL',
            'BillingSurname',
            'BillingFirstnames',
            'BillingAddress1',
            'BillingCity',
            'BillingPostCode',
            'BillingCountry',
            'DeliverySurname',
            'DeliveryFirstnames',
            'DeliveryAddress1',
            'DeliveryCity',
            'DeliveryPostCode',
            'DeliveryCountry',
            'StoreToken'
        );
    }

    /**
     * Generate values for payment.
     * Ensure that post data is setted to request with AbstractApi::setData()
     *
     * @see AbstractApi::createRequest()
     * @return array The response from Sage Pay
     */
    public function createRequest()
    {
        $this->data = Common::encryptedOrder($this);
        $this->addConfiguredValues();
        $this->checkMandatoryFields();

        $ttl = $this->config->getRequestTimeout();
        $caCert = $this->config->getCaCertPath();
        return Common::requestPost($this->_vpsServerUrl, $this->data, $ttl, $caCert);
    }

    /**
     * @see AbstractApi::getQueryData()
     * @return null
     */
    public function getQueryData()
    {
        return null;
    }

    /**
     * Get vpsServerUrl
     *
     * @return type
     */
    public function getVpsServerUrl()
    {
        return $this->_vpsServerUrl;
    }

    /**
     * Set vpsServerUrl
     *
     * @uses Valid::url Validate URL field
     * @param type $vpsServerUrl
     */
    public function setVpsServerUrl($vpsServerUrl)
    {
        if (Valid::url($vpsServerUrl))
        {
            $this->_vpsServerUrl = $vpsServerUrl;
        }
    }

}

