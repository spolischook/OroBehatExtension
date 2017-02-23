<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Isolation;

use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterFinishTestsEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeStartTestsEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\RestoreStateEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class InitalMessageQueueIsolator implements IsolatorInterface
{
    /**
     * @var MessageQueueIsolatorInterface
     */
    protected $messageQueueIsolator;

    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(MessageQueueIsolatorInterface $messageQueueIsolator, KernelInterface $kernel)
    {
        $this->messageQueueIsolator = $messageQueueIsolator;
        $this->kernel = $kernel;
    }

    /** {@inheritdoc} */
    public function start(BeforeStartTestsEvent $event)
    {
        $this->kernel->boot();
        $event->writeln('<info>Process messages before make db dump</info>');
        $this->messageQueueIsolator->beforeTest(new BeforeIsolatedTestEvent(null));
        $this->messageQueueIsolator->waitWhileProcessingMessages();
        $this->messageQueueIsolator->afterTest(new AfterIsolatedTestEvent());
        $this->kernel->shutdown();
    }

    /** {@inheritdoc} */
    public function beforeTest(BeforeIsolatedTestEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function afterTest(AfterIsolatedTestEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function terminate(AfterFinishTestsEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function isApplicable(ContainerInterface $container)
    {
        return true;
    }

    /** {@inheritdoc} */
    public function restoreState(RestoreStateEvent $event)
    {
    }

    /** {@inheritdoc} */
    public function isOutdatedState()
    {
        return false;
    }

    /** {@inheritdoc} */
    public function getName()
    {
        return 'Inital Message Queue Isolator';
    }

    /**
     * {@inheritdoc}
     */
    public function getTag()
    {
        return 'inital_message_queue';
    }
}
