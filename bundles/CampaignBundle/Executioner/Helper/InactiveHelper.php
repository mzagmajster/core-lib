<?php

/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Executioner\Helper;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\EventRepository;
use Mautic\CampaignBundle\Entity\LeadEventLogRepository;
use Mautic\CampaignBundle\Executioner\ContactFinder\InactiveContacts;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use Psr\Log\LoggerInterface;

class InactiveHelper
{
    /**
     * @var EventScheduler
     */
    private $scheduler;

    /**
     * @var InactiveContacts
     */
    private $inactiveContacts;

    /**
     * @var LeadEventLogRepository
     */
    private $eventLogRepository;

    /**
     * @var EventRepository
     */
    private $eventRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * InactiveHelper constructor.
     *
     * @param EventScheduler         $scheduler
     * @param InactiveContacts       $inactiveContacts
     * @param LeadEventLogRepository $eventLogRepository
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EventScheduler $scheduler,
        InactiveContacts $inactiveContacts,
        LeadEventLogRepository $eventLogRepository,
        EventRepository $eventRepository,
        LoggerInterface $logger
    ) {
        $this->scheduler          = $scheduler;
        $this->inactiveContacts   = $inactiveContacts;
        $this->eventLogRepository = $eventLogRepository;
        $this->eventRepository    = $eventRepository;
        $this->logger             = $logger;
    }

    /**
     * @param ArrayCollection|Event[] $decisions
     */
    public function removeDecisionsWithoutNegativeChildren(ArrayCollection $decisions)
    {
        /**
         * @var int
         * @var Event $decision
         */
        foreach ($decisions as $key => $decision) {
            $negativeChildren = $decision->getNegativeChildren();
            if (!$negativeChildren->count()) {
                $decisions->remove($key);
            }
        }
    }

    /**
     * @param \DateTime       $now
     * @param ArrayCollection $contacts
     * @param                 $eventId
     * @param ArrayCollection $negativeChildren
     *
     * @return \DateTime
     *
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function removeContactsThatAreNotApplicable(\DateTime $now, ArrayCollection $contacts, $eventId, ArrayCollection $negativeChildren)
    {
        $contactIds = $contacts->getKeys();

        // If there is a parent ID, get last active dates based on when that event was executed for the given contact
        // Otherwise, use when the contact was added to the campaign for comparison
        if ($eventId) {
            $lastActiveDates = $this->eventLogRepository->getDatesExecuted($eventId, $contactIds);
        } else {
            $lastActiveDates = $this->inactiveContacts->getDatesAdded();
        }

        $earliestInactiveDate = $now;

        /* @var Event $event */
        foreach ($contactIds as $contactId) {
            if (!isset($lastActiveDates[$contactId])) {
                // This contact does not have a last active date so likely the event is scheduled
                $contacts->remove($contactId);

                $this->logger->debug('CAMPAIGN: Contact ID# '.$contactId.' does not have a last active date');

                continue;
            }

            $earliestContactInactiveDate = $this->getEarliestInactiveDate($negativeChildren, $lastActiveDates[$contactId]);
            $this->logger->debug(
                'CAMPAIGN: Earliest date for inactivity for contact ID# '.$contactId.' is '.
                $earliestContactInactiveDate->format('Y-m-d H:i:s T').' based on last active date of '.
                $lastActiveDates[$contactId]->format('Y-m-d H:i:s T')
            );

            if ($earliestInactiveDate < $earliestContactInactiveDate) {
                $earliestInactiveDate = $earliestContactInactiveDate;
            }

            // If any are found to be inactive, we process or schedule all the events associated with the inactive path of a decision
            if ($earliestContactInactiveDate > $now) {
                $contacts->remove($contactId);
                $this->logger->debug('CAMPAIGN: Contact ID# '.$contactId.' has been active and thus not applicable');

                continue;
            }

            $this->logger->debug('CAMPAIGN: Contact ID# '.$contactId.' has not been active');
        }

        return $earliestInactiveDate;
    }

    /**
     * @param $decisionId
     *
     * @return ArrayCollection
     */
    public function getCollectionByDecisionId($decisionId)
    {
        $collection = new ArrayCollection();

        /** @var Event $decision */
        if ($decision = $this->eventRepository->find($decisionId)) {
            $collection->set($decision->getId(), $decision);
        }

        return $collection;
    }

    /**
     * @param ArrayCollection $negativeChildren
     * @param \DateTime       $lastActiveDate
     *
     * @return \DateTime|null
     *
     * @throws \Mautic\CampaignBundle\Executioner\Scheduler\Exception\NotSchedulableException
     */
    public function getEarliestInactiveDate(ArrayCollection $negativeChildren, \DateTime $lastActiveDate)
    {
        $earliestDate = null;
        foreach ($negativeChildren as $event) {
            $executionDate = $this->scheduler->getExecutionDateTime($event, $lastActiveDate);
            if (!$earliestDate || $executionDate < $earliestDate) {
                $earliestDate = $executionDate;
            }
        }

        return $earliestDate;
    }
}
