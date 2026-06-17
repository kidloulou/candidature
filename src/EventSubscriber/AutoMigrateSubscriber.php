<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AutoMigrateSubscriber implements EventSubscriberInterface
{
    private static bool $checked = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly DependencyFactory $dependencyFactory,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 255]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || self::$checked) {
            return;
        }

        self::$checked = true;

        try {
            $this->connection->executeQuery('SELECT 1 FROM task LIMIT 1');
        } catch (\Throwable) {
            $newMigrations = $this->dependencyFactory
                ->getMigrationStatusCalculator()
                ->getNewMigrations()
                ->getItems();

            if ($newMigrations === []) {
                return;
            }

            $versions = array_map(static fn ($m) => $m->getVersion(), $newMigrations);

            $plan = $this->dependencyFactory
                ->getMigrationPlanCalculator()
                ->getPlanForVersions($versions, Direction::UP);

            $this->dependencyFactory
                ->getMigrator()
                ->migrate($plan, new MigratorConfiguration());
        }
    }
}
