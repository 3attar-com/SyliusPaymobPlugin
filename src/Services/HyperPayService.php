<?php

namespace Ahmedkhd\SyliusPaymobPlugin\Services;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use Exception;
use Monolog\Logger;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Client;
use Sylius\Component\Core\Model\PaymentMethod as BasePaymentMethod;
use Payum\Core\ApiAwareInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfig as BaseGatewayConfig;

final class HyperPayService extends AbstractService implements PaymobServiceInterface
{
    public const TRANSACTION_TYPE = "TRANSACTION";

    public function getTransactionStatus($resource)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $paymentRepo = $em->getRepository(BaseGatewayConfig::class);
        $pay = $paymentRepo->findoneby([
            'gatewayName' => $resource['method']
        ]);
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $pay->getConfig()['authToken'],
                'Accept' => 'application/json',
            ];
            $request = new GuzzleRequest('GET', $pay->getConfig()['domain'] . $resource['resourcePath'] . '?entityId=' . $pay->getConfig()['entityId'], $headers);
            $client = new Client();
            $res = ($client->send($request));
            return (json_decode($res->getBody()->getContents(), true));
        } catch (Exception $ex) {
            dd($ex->getMessage());
        }
    }

    public function getHyperPayBaseUrl($method)
    {
        $em = $this->container->get('doctrine.orm.default_entity_manager');
        $paymentRepo = $em->getRepository(BaseGatewayConfig::class);
        $pay = $paymentRepo->findoneby([
            'gatewayName' => $method
        ]);
        return $pay->getConfig()['domain'];
    }

    public function getIframeData($request)
    {
        $method = $request->query->all()['method'];
        $payment_token = $request->query->get('payment_token');
        $script_url = $this->getHyperPayBaseUrl($method) . "/v1/paymentWidgets.js?checkoutId={$payment_token}";
        $data = [
            'payment_id' => $request->query->all()['payment_token'],
            'url' => $request->getSchemeAndHttpHost() . "/payment/hyperpay/capture?method={$method}",
            'script_url' => $script_url,
        ];
        return $data;
    }

    private function webhookDecryption($request)
    {
        $http_body = file_get_contents('php://input');
        $key_from_configration = $this->parameterBag->get("HYPERPAY_SECRET_KEY");
        $headers = getallheaders();
        $iv_from_http_header = $headers['X-Initialization-Vector'];
        $auth_tag_from_http_header = $headers['X-Authentication-Tag'];
        $http = json_decode($http_body);
        $body = $http_body;
        $key = hex2bin($key_from_configration);
        $iv = hex2bin($iv_from_http_header);
        $auth_tag = hex2bin($auth_tag_from_http_header);
        $cipher_text = hex2bin($body);
        $result = openssl_decrypt($cipher_text, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $auth_tag);
        $result = json_decode($result, true);
        return $result;
    }

    public function handelWebhook($request)
    {
        $result = $this->webhookDecryption($request);
        $logData = $result['payload'];
        unset($logData['risk']);
        unset($logData['redirect']);
        unset($logData['authentication']);
        unset($logData['shortId']);
        $this->log->info('Request details', [
            'result' => $logData
        ]);

        try {
            if ($result['type'] === 'PAYMENT' && $result['payload']['result']['code'] === '000.000.000') {
                $this->competeOrder($result['payload']['ndc']);
                return new Response('success', 200, [
                    'Content-Type' => 'text/xml'
                ]);
            }
            return new Response('failed', 400, [
                'Content-Type' => 'text/xml'
            ]);
        } catch (\Exception $ex) {
            $this->log->emergency($ex);
        }
    }
}
