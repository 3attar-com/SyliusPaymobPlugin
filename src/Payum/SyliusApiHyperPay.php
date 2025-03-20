<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;


final class SyliusApiHyperPay implements SyliusPayumInterface
{
    /** @var string */
    private $authToken;

    /** @var string */
    /** @var string */
    private $entityId;

    /** @var string */
    private $iframeUrl;

    /** @var string */
    private $domain;


    public function __construct(
        string $authToken,
        string $entityId,
        string $iframeUrl,
        string $domain
    )
    {
        $this->authToken = $authToken;
        $this->entityId = $entityId;
        $this->iframeUrl = $iframeUrl;
        $this->domain = $domain;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    /**
     * @return string
     */
    public function getEntityId(): string
    {
        return $this->entityId;
    }

    /**
     * @return string
     */
    public function getIframeUrl(): string
    {
        return $this->iframeUrl;
    }

    /**
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * @return string
     */

    public function doPayment($iframeURL)
    {
        header("location: {$iframeURL}");
        exit;
    }

}
