<?php

declare(strict_types=1);

namespace Ahmedkhd\SyliusPaymobPlugin\Payum;


final class MadfuApi implements SyliusPayumInterface
{

    private $authrization;
    private $appCode;
    private $apiKey;
    private $domain;
    private $userName;
    private $password;

    public function __construct(
        string $domain,
        string $apiKey,
        string $appCode,
        string $password,
        string $userName,
        string $authrization

    )
    {
        $this->apiKey = $apiKey;
        $this->domain = $domain;
        $this->userName = $userName;
        $this->password = $password;
        $this->authrization = $authrization;
        $this->appCode = $appCode;
    }

    /**
     * @return string
     */
    public function getAppCode(): string
    {
        return $this->appCode;
    }

    /**
     * @return string
     */
    public function getAuthrization(): string
    {
        return $this->authrization;
    }

    /**
     * @return string
     */
    public function getApiKey(): string
    {
        return $this->apiKey;
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
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
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
