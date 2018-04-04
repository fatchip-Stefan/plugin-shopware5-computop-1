<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * The Computop Shopware Plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The Computop Shopware Plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with Computop Shopware Plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * PHP version 5.6, 7.0 , 7.1
 *
 * @category   Payment
 * @package    FatchipCTPayment
 * @subpackage Controllers/Frontend
 * @author     FATCHIP GmbH <support@fatchip.de>
 * @copyright  2018 Computop
 * @license    <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link       https://www.computop.com
 */

require_once 'FatchipCTPayment.php';

use Fatchip\CTPayment\CTEnums\CTEnumStatus;

/**
 * Class Shopware_Controllers_Frontend_FatchipCTCreditCard
 */
class Shopware_Controllers_Frontend_FatchipCTCreditCard extends Shopware_Controllers_Frontend_FatchipCTPayment
{

    /**
     * {@inheritdoc}
     */
    public $paymentClass = 'CreditCard';

    /**
     *  gatewaAction is overridden for Creditcard because:
     *  1. extra param URLBack
     *  2. forward to iframe controller instead of Computop Gateway, so the Computop IFrame is shown within Shop layout
     */
    public function gatewayAction()
    {
        //$payment = $this->getPaymentClassForGatewayAction();
        if ($this->config['creditCardMode'] !== 'SILENT') {
            $payment = $this->getPaymentClassForGatewayAction();
            //only Creditcard has URLBck
            $payment->setUrlBack($this->router->assemble(['controller' => 'FatchipCTCreditCard', 'action' => 'failure', 'forceSecure' => true]));

            $params = $payment->getRedirectUrlParams();
            $this->session->offsetSet('fatchipCTRedirectParams', $params);

            $this->forward('iframe', 'FatchipCTCreditCard', null, array('fatchipCTRedirectURL' => $payment->getHTTPGetURL($params)));
        } else {
            $payID = $this->session->offsetGet('FatchipCTCCPayID');
            $transID = $this->session->offsetGet('FatchipCTCCTransID');
            /** @var \Fatchip\CTPayment\CTPaymentMethodsIframe\CreditCard $payment */
            $payment = $this->getPaymentClassForGatewayAction();

            $params = $payment->getAuthorizeParams($payID, $transID, $payment->getAmount(), $payment->getCurrency(), $this->config['creditCardCaption'] );
            $response = $this->plugin->callComputopService($params, $payment, 'AUTH', $payment->getAuthorizeURL());
            $this->forward('success', null, null, ['response' => $response]);
        }
    }


    /**
     * action to show Computop Creditcard Iframe within shop layout
     */
    public function iframeAction()
    {
        $this->view->loadTemplate('frontend/fatchipCTCreditCard/index.tpl');
        $this->view->assign('fatchipCTPaymentConfig', $this->config);
        $requestParams = $this->Request()->getParams();
        $this->view->assign('fatchipCTIframeURL', $requestParams['fatchipCTRedirectURL']);
        $this->view->assign('fatchipCTURL', $requestParams['fatchipCTURL']);
        $this->view->assign('fatchipCTErrorMessage', $requestParams['CTError']['CTErrorMessage']);
        $this->view->assign('fatchipCTErrorCode', $requestParams['CTError']['CTErrorCode']);
    }

    /**
     *  post data as formdata to $formParams['url'],
     *  the computop endpoint will reponse with a 302 redirect
     *  html string
     *
     * @param array $formParams
     *
     * @return mixed $output
     */
    private function postForm($formParams)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $formParams['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        curl_setopt($ch, CURLOPT_POST, true);

        $data = array(
            'MerchantID' => $formParams['MerchantID'],
            'Len' => $formParams['Len'],
            'Data' => $formParams['Data'],
        );

        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        // use for debugging and checkoing of http response codes etc.
        $info = curl_getinfo($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * action that handles creditcard form data in paynow silent mode
     */
    public function postFormAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();
        $params = $this->session->offsetGet('FatchipComputopPaymentData');
        $this->session->offsetUnset('FatchipComputopPaymentData');

        /** @var Fatchip\CTPayment\CTPaymentMethodsIframe\CreditCard $payment */
        $payment = $this->getPaymentClassForGatewayAction();
        $payment->setCCBrand($params['fatchip_computop_creditcard_brand']);
        $payment->setCCNr($params['fatchip_computop_creditcard_cardnumber']);
        $payment->setCCExpiry($params['fatchip_computop_creditcard_expirationdateyear'] . $params['fatchip_computop_creditcard_expirationdatemonth']);
        $payment->setCCCVC($params['fatchip_computop_creditcard_cvc']);
        $payment->setUrlSuccess($this->router->assemble(['controller' => 'FatchipCTCreditCard', 'action' => 'postFormSuccess', 'forceSecure' => true]));
        $payment->setCapture('MANUAL');
        $payment->setTxType('Order');

        $requestParams = $payment->getRedirectUrlParams();

        $prepareRequest = $payment->prepareSilentRequest($requestParams, $payment->getCTPayNowURL());

        $response = $this->postForm($prepareRequest);

        # postForm return a redirect html string
        # extract the success url from <a href> and redirect there
        $link = preg_match('/<a href="(.+)">/', $response, $match);
        $info = $match[1];
        // replace url encodes &amp
        $url = str_replace('&amp;', '&', $info);
        if (!empty($url)){
            $this->redirect($url);
        } else {
            $this->forward('failure');
        }
    }

    /**
     * success action method
     * Overridden because for Creditcards we forward to IFrameAction
     * @return void     *
     */
    public function postFormSuccessAction()
    {
        $requestParams = $this->Request()->getParams();

        /** @var \Fatchip\CTPayment\CTResponse $response */
        $response = $this->paymentService->getDecryptedResponse($requestParams);

        $this->plugin->logRedirectParams($this->session->offsetGet('fatchipCTRedirectParams'), $this->paymentClass, 'PREAUTH', $response);

        switch ($response->getStatus()) {
            case 'success':
                $this->session->offsetSet('FatchipCTCCPayID', $response->getPayID());
                $this->session->offsetSet('FatchipCTCCTransID', $response->getTransID());
                $this->forward('confirm', 'checkout');
                break;
            default:
                $this->forward('failure');
                break;
        }
    }

    /**
     * success action method
     * Overridden because for Creditcards we forward to IFrameAction
     * @return void     *
     */
    public function successAction()
    {
        $requestParams = $this->Request()->getParams();
        // used for paynow silent mode
        if (!empty($requestParams['response'])){
            $response = $requestParams['response'];
        } else {
            /** @var \Fatchip\CTPayment\CTResponse $response */
            $response = $this->paymentService->getDecryptedResponse($requestParams);
        }


        if (!$this->config['creditCardCaption'] === 'SILENT'){
            $this->plugin->logRedirectParams($this->session->offsetGet('fatchipCTRedirectParams'), $this->paymentClass, 'REDIRECT', $response);
        }

        switch ($response->getStatus()) {
            case CTEnumStatus::OK:
                $orderNumber = $this->saveOrder(
                    $response->getTransID(),
                    $response->getPayID(),
                    self::PAYMENTSTATUSRESERVED
                );
                $this->saveTransactionResult($response);

                $this->handleDelayedCapture($orderNumber);
                $this->updateRefNrWithComputopFromOrderNumber($orderNumber);

                $url = $this->Front()->Router()->assemble(['controller' => 'checkout', 'action' => 'finish']);
                $this->forward('iframe', 'FatchipCTCreditCard', null, array('fatchipCTURL' => $url));
                break;
            default:
                $this->forward('failure');
                break;
        }
    }

    /**
     * Cancel action method. Overridden cause for Creditcard we forward to iframe action
     * @return void
     */
    public function failureAction()
    {
        $requestParams = $this->Request()->getParams();
        $ctError = [];

        $response = $this->paymentService->getDecryptedResponse($requestParams);

        if (!$this->config['creditCardCaption'] === 'SILENT'){
            $this->plugin->logRedirectParams($this->session->offsetGet('fatchipCTRedirectParams'), $this->paymentClass, 'REDIRECT', $response);
        }

        $ctError['CTErrorMessage'] = self::ERRORMSG . $response->getDescription();
        $ctError['CTErrorCode'] = $response->getCode();
        $url = $this->Front()->Router()->assemble(['controller' => 'checkout', 'action' => 'shippingPayment']);
        if (!$this->config['creditCardCaption'] ==='SILENT'){
            $this->forward('iframe', 'FatchipCTCreditCard', null, ['fatchipCTURL' => $url, 'CTError' => $ctError]);
        } else {
            $this->forward('shippingPayment', 'checkout', null, ['CTError' => $ctError]);
        }
    }


}
