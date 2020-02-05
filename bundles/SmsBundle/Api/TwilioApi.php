<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\SmsBundle\Api;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Monolog\Logger;

class TwilioApi extends AbstractSmsApi
{
    /**
     * @var \Services_Twilio
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $sendingPhoneNumber;

    public function __construct(TrackableModel $pageTrackableModel, IntegrationHelper $integrationHelper, Logger $logger)
    {
        $this->logger = $logger;

        $integration = $integrationHelper->getIntegrationObject('Twilio');

        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $this->sendingPhoneNumber = $integration->getIntegrationSettings()->getFeatureSettings()['sending_phone_number'];

            $keys = $integration->getDecryptedApiKeys();

            if (isset($keys['username']) && isset($keys['password'])) {
                $this->client = new \Services_Twilio($keys['username'], $keys['password']);
            }
        }

        parent::__construct($pageTrackableModel);
    }

    /**
     * @param $number
     *
     * @return string
     *
     * @throws NumberParseException
     */
    protected function sanitizeNumber($number)
    {
        $util   = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, 'US');

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @param string $content
     *
     * @return bool|string
     */
    public function sendSms(Lead $lead, $content)
    {
        $number = $lead->getLeadPhoneNumber();

        if (null === $number) {
            return false;
        }

        try {
            $this->client->account->messages->sendMessage(
                $this->sendingPhoneNumber,
                $this->sanitizeNumber($number),
                $content
            );

            return true;
        } catch (\Services_Twilio_RestException $e) {
            $this->logger->addWarning(
                $e->getMessage(),
                ['exception' => $e]
            );

            return $e->getMessage();
        } catch (NumberParseException $e) {
            $this->logger->addWarning(
                $e->getMessage(),
                ['exception' => $e]
            );

            return $e->getMessage();
        }
    }
}
