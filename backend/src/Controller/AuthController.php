<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\OrganizationUser;
use App\Entity\User;
use App\Repository\OrganizationUserRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly OrganizationUserRepository $organizationUserRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/auth/register', name: 'auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        $organizationName = trim((string) ($data['organizationName'] ?? 'My Organization'));

        if ($email === '' || $password === '' || $name === '') {
            return $this->error('validation_failed', 'email, password and name are required.', Response::HTTP_BAD_REQUEST);
        }

        if ($this->userRepository->findOneByEmail($email) !== null) {
            return $this->error('email_exists', 'User with this email already exists.', Response::HTTP_CONFLICT);
        }

        $user = new User($email, '', $name);
        $user->setApiToken(bin2hex(random_bytes(32)));
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));

        $organization = new Organization($organizationName);
        $organizationUser = new OrganizationUser($organization, $user, OrganizationUser::ROLE_OWNER);

        $this->entityManager->persist($user);
        $this->entityManager->persist($organization);
        $this->entityManager->persist($organizationUser);
        $this->entityManager->flush();

        return $this->json([
            'token' => $user->getApiToken(),
            'user' => $this->serializeUser($user),
            'organization' => $this->serializeOrganization($organizationUser),
        ], Response::HTTP_CREATED);
    }

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->error('invalid_json', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        $email = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        $user = $this->userRepository->findOneByEmail($email);
        if ($user === null || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return $this->error('invalid_credentials', 'Invalid email or password.', Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getApiToken() === null) {
            $user->setApiToken(bin2hex(random_bytes(32)));
            $this->entityManager->flush();
        }

        $organizationUser = $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId());

        return $this->json([
            'token' => $user->getApiToken(),
            'user' => $this->serializeUser($user),
            'organization' => $this->serializeOrganization($organizationUser),
        ]);
    }

    #[Route('/auth/me', name: 'auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->error('unauthorized', 'Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

        $organizationUser = $this->organizationUserRepository->findPrimaryOrganizationForUser((string) $user->getId());

        return $this->json([
            'user' => $this->serializeUser($user),
            'organization' => $this->serializeOrganization($organizationUser),
        ]);
    }

    private function serializeOrganization(?OrganizationUser $organizationUser): ?array
    {
        if ($organizationUser === null) {
            return null;
        }

        $organization = $organizationUser->getOrganization();

        return [
            'id' => (string) $organization->getId(),
            'name' => $organization->getName(),
            'planCode' => $organization->getPlanCode(),
            'role' => $organizationUser->getRole(),
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'isPlatformAdmin' => $user->isPlatformAdmin(),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return $this->json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'requestId' => bin2hex(random_bytes(8)),
        ], $status);
    }
}
