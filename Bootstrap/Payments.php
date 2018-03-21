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
 * @subpackage Bootstrap
 * @author     FATCHIP GmbH <support@fatchip.de>
 * @copyright  2018 Computop
 * @license    <http://www.gnu.org/licenses/> GNU Lesser General Public License
 * @link       https://www.computop.com
 */

namespace Shopware\Plugins\FatchipCTPayment\Bootstrap;

use Fatchip\CTPayment\CTPaymentService;
use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Models\Country\Country;
use Shopware\Models\Payment\Payment;

/**
 * Class Payments.
 *
 * creates payment methods.
 */
class Payments
{
    /**
     * FatchipCTpayment Plugin Bootstrap Class
     * @var \Shopware_Plugins_Frontend_FatchipCTPayment_Bootstrap
     */
    private $plugin;

    /**
     * Payments constructor.
     */
    public function __construct()
    {
        $this->plugin = Shopware()->Plugins()->Frontend()->FatchipCTPayment();
    }

    /**
     * Create payment methods.
     *
     * @see CTPaymentService::getPaymentMethods()
     * @see \Shopware_Components_Plugin_Bootstrap::createPayment()
     *
     * @return void
     */
    public function createPayments()
    {
        /** @var CTPaymentService $service */
        $service = new CTPaymentService(null);
        $paymentMethods = $service->getPaymentMethods();

        foreach ($paymentMethods as $paymentMethod) {
            if ($this->plugin->Payments()->findOneBy(array('name' => $paymentMethod['name']))) {
                continue;
            }

            $payment = [
                'name' => $paymentMethod['name'],
                'description' => $paymentMethod['description'],
                'action' => $paymentMethod['action'],
                'active' => 0,
                'template' => $paymentMethod['template'],
                'additionalDescription' => $paymentMethod['additionalDescription'],
            ];

            $paymentObject = $this->plugin->createPayment($payment);

            if (!empty($paymentMethod['countries'])) {
                $this->restrictPaymentShippingCountries($paymentObject, $paymentMethod['countries']);
            }
        }
    }

    /**
     * Restrict payment method to countries.
     *
     *
     * @see \Shopware\Models\Payment\Payment::setCountries()
     *
     * @param Payment $paymentObject        payment method to restrict
     * @param ArrayCollection $countries    countries to restrict
     *
     * @return void
     */
    private function restrictPaymentShippingCountries($paymentObject, $countries)
    {
        $countryCollection = new ArrayCollection();
        foreach ($countries as $countryIso) {
            $country =
                Shopware()->Models()->getRepository(Country::class)->findOneBy(['iso' => $countryIso]);
            if ($country !== null) {
                $countryCollection->add($country);
            }
        }
        $paymentObject->setCountries($countryCollection);
    }
}
