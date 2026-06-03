<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Notification\CriticalIncidentReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:dispatch-critical-reminders',
    description: 'Send Telegram reminders for open critical incidents',
)]
final class DispatchCriticalRemindersCommand extends Command
{
    public function __construct(
        private readonly CriticalIncidentReminderService $reminderService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sent = $this->reminderService->dispatchDueReminders();
        $output->writeln(sprintf('Sent %d critical reminder(s).', $sent));

        return Command::SUCCESS;
    }
}
