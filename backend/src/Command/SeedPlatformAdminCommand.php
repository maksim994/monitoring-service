<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-platform-admin',
    description: 'Ensure platform admin user exists for the operator panel',
)]
final class SeedPlatformAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire('%env(PLATFORM_ADMIN_EMAIL)%')]
        private readonly string $adminEmail,
        #[Autowire('%env(PLATFORM_ADMIN_PASSWORD)%')]
        private readonly string $adminPassword,
        #[Autowire('%env(PLATFORM_ADMIN_NAME)%')]
        private readonly string $adminName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower(trim($this->adminEmail));

        $user = $this->userRepository->findOneByEmail($email);
        if ($user === null) {
            $user = new User($email, '', $this->adminName);
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $this->adminPassword));
            $user->setApiToken(bin2hex(random_bytes(32)));
            $user->setPlatformAdmin(true);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success(sprintf('Platform admin created: %s', $email));
        } elseif (!$user->isPlatformAdmin()) {
            $user->setPlatformAdmin(true);
            $this->entityManager->flush();
            $io->success(sprintf('Platform admin flag enabled for: %s', $email));
        } else {
            $io->success(sprintf('Platform admin already exists: %s', $email));
        }

        $io->table(
            ['Field', 'Value'],
            [
                ['Email', $email],
                ['Password', $this->adminPassword],
                ['Admin panel', 'http://localhost:13000/admin'],
                ['API', 'http://localhost:18080'],
            ],
        );
        $io->note('Use platform admin credentials only in local/dev environments.');

        return Command::SUCCESS;
    }
}
