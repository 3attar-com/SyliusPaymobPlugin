<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;


final class PaymobApi implements SyliusPayumInterface
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $hamcSecurity;

    /** @var string */
    private $merchantId;

    /** @var string */
    private $iframe;

    /** @var string */
    private $integrationId;

    private $domain;
    private $notificationUrl;
    private $redirectionUrl;

    public function __construct(
        string $apiKey,
        string $hamcSecurity,
        string $merchantId,
        string $iframe,
        string $integrationId,
        string $domain,
        string $notificationUrl,
        string $redirectionUrl
    )
    {
        $this->apiKey = $apiKey;
        $this->hamcSecurity = $hamcSecurity;
        $this->merchantId = $merchantId;
        $this->iframe = $iframe;
        $this->integrationId = $integrationId;
        $this->domain = $domain;
        $this->notificationUrl = $notificationUrl;
        $this->redirectionUrl = $redirectionUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getNotificationUrl(): string
    {
        return $this->notificationUrl;
    }

    public function getRedirectionUrl(): string
    {
        return $this->redirectionUrl;
    }

    /**
     * @return string
     */
    public function getHamcSecurity(): string
    {
        return $this->hamcSecurity;
    }

    /**
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * @return string
     */
    public function getIframe(): string
    {
        return $this->iframe;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @return string
     */
    public function getIntegrationId(): string
    {
        return $this->integrationId;
    }

    public function doPayment($iframeURL)
    {
        header("location: {$iframeURL}");
        exit;
    }

}
