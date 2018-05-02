<?php

/*
 * @copyright   2018 Mautic Contributors. All rights reserved
 * @author      Mautic, Inc.
 *
 * @link        https://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace Mautic\CampaignBundle\Tests\Executioner\Dispatcher;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Entity\Event;
use Mautic\CampaignBundle\Entity\LeadEventLog;
use Mautic\CampaignBundle\Event\ConditionEvent;
use Mautic\CampaignBundle\Event\DecisionEvent;
use Mautic\CampaignBundle\Event\DecisionResultsEvent;
use Mautic\CampaignBundle\Event\ExecutedBatchEvent;
use Mautic\CampaignBundle\Event\ExecutedEvent;
use Mautic\CampaignBundle\Event\FailedEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\ActionAccessor;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\ConditionAccessor;
use Mautic\CampaignBundle\EventCollector\Accessor\Event\DecisionAccessor;
use Mautic\CampaignBundle\Executioner\Dispatcher\EventDispatcher;
use Mautic\CampaignBundle\Executioner\Dispatcher\Exception\LogNotProcessedException;
use Mautic\CampaignBundle\Executioner\Dispatcher\LegacyEventDispatcher;
use Mautic\CampaignBundle\Executioner\Result\EvaluatedContacts;
use Mautic\CampaignBundle\Executioner\Scheduler\EventScheduler;
use Mautic\LeadBundle\Entity\Lead;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventDispatcherTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockBuilder|EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @var \PHPUnit_Framework_MockObject_MockBuilder|EventScheduler
     */
    private $scheduler;

    /**
     * @var \PHPUnit_Framework_MockObject_MockBuilder|LegacyEventDispatcher
     */
    private $legacyDispatcher;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->scheduler = $this->getMockBuilder(EventScheduler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->legacyDispatcher = $this->getMockBuilder(LegacyEventDispatcher::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testActionBatchEventIsDispatchedWithSuccessAndFailedLogs()
    {
        $event = new Event();

        $lead1 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead1->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(1);

        $lead2 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead2->expects($this->exactly(2))
            ->method('getId')
            ->willReturn(2);

        $log1 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log1->expects($this->exactly(2))
            ->method('getLead')
            ->willReturn($lead1);
        $log1->method('setIsScheduled')
            ->willReturn($log1);
        $log1->method('getEvent')
            ->willReturn($event);

        $log2 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log2->expects($this->exactly(2))
            ->method('getLead')
            ->willReturn($lead2);
        $log2->method('getMetadata')
            ->willReturn([]);
        $log2->method('getEvent')
            ->willReturn($event);

        $logs = new ArrayCollection(
            [
                1 => $log1,
                2 => $log2,
            ]
        );

        $config = $this->getMockBuilder(ActionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getBatchEventName')
            ->willReturn('something');

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->willReturnCallback(
                function ($eventName, PendingEvent $pendingEvent) use ($logs) {
                    $pendingEvent->pass($logs->get(1));
                    $pendingEvent->fail($logs->get(2), 'just because');
                }
            );

        $this->dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_EXECUTED, $this->isInstanceOf(ExecutedEvent::class));

        $this->dispatcher->expects($this->at(2))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_EXECUTED_BATCH, $this->isInstanceOf(ExecutedBatchEvent::class));

        $this->dispatcher->expects($this->at(3))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_FAILED, $this->isInstanceOf(FailedEvent::class));

        $this->scheduler->expects($this->once())
            ->method('rescheduleFailure')
            ->with($logs->get(2));

        $this->legacyDispatcher->expects($this->once())
            ->method('dispatchExecutionEvents');

        $this->getEventDispatcher()->dispatchActionEvent($config, $event, $logs);
    }

    public function testActionLogNotProcessedExceptionIsThrownIfLogNotProcessedWithSuccess()
    {
        $this->expectException(LogNotProcessedException::class);

        $event = new Event();

        $lead1 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead1->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $lead2 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead2->expects($this->once())
            ->method('getId')
            ->willReturn(2);

        $log1 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log1->expects($this->once())
            ->method('getLead')
            ->willReturn($lead1);
        $log1->method('setIsScheduled')
            ->willReturn($log1);
        $log1->method('getEvent')
            ->willReturn($event);

        $log2 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log2->expects($this->once())
            ->method('getLead')
            ->willReturn($lead2);
        $log2->method('getMetadata')
            ->willReturn([]);
        $log2->method('getEvent')
            ->willReturn($event);

        $logs = new ArrayCollection(
            [
                1 => $log1,
                2 => $log2,
            ]
        );

        $config = $this->getMockBuilder(ActionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getBatchEventName')
            ->willReturn('something');

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->willReturnCallback(
                function ($eventName, PendingEvent $pendingEvent) use ($logs) {
                    $pendingEvent->pass($logs->get(1));

                    // One log is not processed so the exception should be thrown
                }
            );

        $this->getEventDispatcher()->dispatchActionEvent($config, $event, $logs);
    }

    public function testActionLogNotProcessedExceptionIsThrownIfLogNotProcessedWithFailed()
    {
        $this->expectException(LogNotProcessedException::class);

        $event = new Event();

        $lead1 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead1->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $lead2 = $this->getMockBuilder(Lead::class)
            ->getMock();
        $lead2->expects($this->once())
            ->method('getId')
            ->willReturn(2);

        $log1 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log1->expects($this->once())
            ->method('getLead')
            ->willReturn($lead1);
        $log1->method('setIsScheduled')
            ->willReturn($log1);
        $log1->method('getEvent')
            ->willReturn($event);

        $log2 = $this->getMockBuilder(LeadEventLog::class)
            ->getMock();
        $log2->expects($this->once())
            ->method('getLead')
            ->willReturn($lead2);
        $log2->method('getMetadata')
            ->willReturn([]);
        $log2->method('getEvent')
            ->willReturn($event);

        $logs = new ArrayCollection(
            [
                1 => $log1,
                2 => $log2,
            ]
        );

        $config = $this->getMockBuilder(ActionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getBatchEventName')
            ->willReturn('something');

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->willReturnCallback(
                function ($eventName, PendingEvent $pendingEvent) use ($logs) {
                    $pendingEvent->fail($logs->get(2), 'something');

                    // One log is not processed so the exception should be thrown
                }
            );

        $this->getEventDispatcher()->dispatchActionEvent($config, $event, $logs);
    }

    public function testActionBatchEventIsIgnoredWithLegacy()
    {
        $event = new Event();

        $config = $this->getMockBuilder(ActionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getBatchEventName')
            ->willReturn(null);

        $this->dispatcher->expects($this->never())
            ->method('dispatch');

        $this->legacyDispatcher->expects($this->once())
            ->method('dispatchCustomEvent');

        $this->getEventDispatcher()->dispatchActionEvent($config, $event, new ArrayCollection());
    }

    public function testDecisionEventIsDispatched()
    {
        $config = $this->getMockBuilder(DecisionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getEventName')
            ->willReturn('something');

        $this->legacyDispatcher->expects($this->once())
            ->method('dispatchDecisionEvent');

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->with('something', $this->isInstanceOf(DecisionEvent::class));

        $this->dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_DECISION_EVALUATION, $this->isInstanceOf(DecisionEvent::class));

        $this->getEventDispatcher()->dispatchDecisionEvent($config, new LeadEventLog(), null);
    }

    public function testDecisionResultsEventIsDispatched()
    {
        $config = $this->getMockBuilder(DecisionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_DECISION_EVALUATION_RESULTS, $this->isInstanceOf(DecisionResultsEvent::class));

        $this->getEventDispatcher()->dispatchDecisionResultsEvent($config, new ArrayCollection(), new EvaluatedContacts());
    }

    public function testConditionEventIsDispatched()
    {
        $config = $this->getMockBuilder(ConditionAccessor::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->once())
            ->method('getEventName')
            ->willReturn('something');

        $this->dispatcher->expects($this->at(0))
            ->method('dispatch')
            ->with('something', $this->isInstanceOf(ConditionEvent::class));

        $this->dispatcher->expects($this->at(1))
            ->method('dispatch')
            ->with(CampaignEvents::ON_EVENT_CONDITION_EVALUATION, $this->isInstanceOf(ConditionEvent::class));

        $this->getEventDispatcher()->dispatchConditionEvent($config, new LeadEventLog());
    }

    /**
     * @return EventDispatcher
     */
    private function getEventDispatcher()
    {
        return new EventDispatcher(
            $this->dispatcher,
            new NullLogger(),
            $this->scheduler,
            $this->legacyDispatcher
        );
    }
}
