<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\LeadBundle\EventListener;

use Mautic\WebhookBundle\WebhookEvents;
use Mautic\WebhookBundle\Event\WebhookBuilderEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;

/**
 * Class WebhookSubscriber
 *
 * @package Mautic\LeadBundle\EventListener
 */
class WebhookSubscriber extends CommonSubscriber
{
    /**
     * @return array
     */
    static public function getSubscribedEvents()
    {
        return array(
            WebhookEvents::WEBHOOK_ON_BUILD => array('onWebhookBuild', 0)
        );
    }

    /**
     * Add event triggers and actions
     *
     * @param WebhookBuilderEvent $event
     */
    public function onWebhookBuild(WebhookBuilderEvent $event)
    {
        $eventType = array(
            'label'       => 'mautic.lead.webhook.event.lead.new',
            'description' => 'mautic.lead.webhook.event.lead.new_desc',
        );

        $event->addEvent('webhook.lead.new', $eventType);
    }
}