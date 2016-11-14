<?php

namespace Oro\Bundle\TestFrameworkBundle\Behat\Isolation;

use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterFinishTestsEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\AfterIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeIsolatedTestEvent;
use Oro\Bundle\TestFrameworkBundle\Behat\Isolation\Event\BeforeStartTestsEvent;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class UnixPgsqlIsolator extends OsRelatedIsolator implements IsolatorInterface
{
    use AbstractDbIsolator;

    /** {@inheritdoc} */
    public function start(BeforeStartTestsEvent $event)
    {
        $this->runProcess(sprintf(
            'PGPASSWORD="%s" pg_dump -h %s -U %s %s > %s/%4$s.sql',
            $this->dbPass,
            $this->dbHost,
            $this->dbUser,
            $this->dbName,
            $this->cacheDir
        ));
    }

    /** {@inheritdoc} */
    public function beforeTest(BeforeIsolatedTestEvent $event)
    {}

    /** {@inheritdoc} */
    public function afterTest(AfterIsolatedTestEvent $event)
    {
        $this->runProcess(sprintf(
            'PGPASSWORD="%s" psql -c "drop database %s;" -h %s -U %s',
            $this->dbPass,
            $this->dbName,
            $this->dbHost,
            $this->dbUser
        ));
        $this->runProcess(sprintf(
            'PGPASSWORD="%s" psql -c "create database %s;" -h %s -U %s ',
            $this->dbPass,
            $this->dbName,
            $this->dbHost,
            $this->dbUser
        ));
        $this->runProcess(sprintf(
            'PGPASSWORD="%s" psql -h %s -U %s %s < %s/%4$s.sql',
            $this->dbPass,
            $this->dbHost,
            $this->dbUser,
            $this->dbName,
            $this->cacheDir
        ));
    }

    /** {@inheritdoc} */
    public function terminate(AfterFinishTestsEvent $event)
    {}

    /** {@inheritdoc} */
    public function isApplicable(ContainerInterface $container)
    {
        return
            self::isApplicableOS()
            && 'pdo_pgsql' === $container->getParameter('database_driver');
    }

    /**
     * {@inheritdoc}
     */
    public function isOutdatedState()
    {
        return false;
    }

    /** {@inheritdoc} */
    protected function getApplicableOs()
    {
        return [
            OsRelatedIsolator::LINUX_OS,
            OsRelatedIsolator::MAC_OS,
        ];
    }

    /**
     * Restore initial state
     * @return void
     */
    public function restoreState()
    {
    }
}
