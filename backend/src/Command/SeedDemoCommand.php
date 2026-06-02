<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\NotificationChannel;
use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Check\CheckProvisioner;
use App\Service\Security\SiteKeyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-demo',
    description: 'Create demo organization, user and optional demo site for local development',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SiteKeyService $siteKeyService,
        private readonly CheckProvisioner $checkProvisioner,
        #[Autowire('%env(DEMO_USER_EMAIL)%')]
        private readonly string $demoEmail,
        #[Autowire('%env(DEMO_USER_PASSWORD)%')]
        private readonly string $demoPassword,
        #[Autowire('%env(DEMO_USER_NAME)%')]
        private readonly string $demoName,
        #[Autowire('%env(DEMO_ORGANIZATION_NAME)%')]
        private readonly string $demoOrganizationName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower($this->demoEmail);

        $existingUser = $this->userRepository->findOneByEmail($email);
        if ($existingUser !== null) {
            $io->success(sprintf('Demo user already exists: %s', $email));

            return Command::SUCCESS;
        }

        $user = new User($email, '', $this->demoName);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $this->demoPassword));
        $user->setApiToken(bin2hex(random_bytes(32)));

        $organization = new Organization($this->demoOrganizationName);
        $organizationUser = new OrganizationUser($organization, $user, OrganizationUser::ROLE_OWNER);

        $site = new Site($organization, 'demo.example.ru', 'https://demo.example.ru');
        $keyData = $this->siteKeyService->createKey($site);
        $this->checkProvisioner->provisionForSite($site);

        $emailChannel = new NotificationChannel(
            $organization,
            NotificationChannel::TYPE_EMAIL,
            'Demo Email',
            ['email' => $email],
        );

        $this->entityManager->persist($user);
        $this->entityManager->persist($organization);
        $this->entityManager->persist($organizationUser);
        $this->entityManager->persist($site);
        $this->entityManager->persist($emailChannel);
        $this->entityManager->flush();

        $io->title('Demo account created');
        $io->table(
            ['Field', 'Value'],
            [
                ['Email', $email],
                ['Password', $this->demoPassword],
                ['Organization', $this->demoOrganizationName],
                ['Frontend', 'http://localhost:13000'],
                ['API', 'http://localhost:18080'],
                ['Demo site domain', $site->getDomain()],
                ['Demo site ID', (string) $site->getId()],
                ['Demo site API secret', $keyData['secret']],
            ],
        );
        $io->note('Use these credentials only in local/dev environments.');

        return Command::SUCCESS;
    }
}
