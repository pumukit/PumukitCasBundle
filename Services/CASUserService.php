<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\GroupService;
use Pumukit\SchemaBundle\Services\PermissionProfileService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

#[WithMonologChannel('cas')]
class CASUserService
{
    public function __construct(
        protected UserService $userService,
        protected PersonService $personService,
        protected CASService $casService,
        protected PermissionProfileService $permissionProfileService,
        protected GroupService $groupService,
        protected DocumentManager $dm,
        protected LoggerInterface $logger,
        private readonly string $casIdKey,
        private readonly string $casMailKey,
        private readonly string $casGivenNameKey,
        private readonly string $casSurnameKey,
        private readonly string $casGroupKey,
        private readonly string $casOriginKey,
        private readonly array $profileMapping,
        private readonly ?string $permissionProfilesAttribute,
        private readonly string $defaultPermissionProfile,
        private readonly bool $forceOverridePermissionProfile,
        private readonly bool $createUsers = true,
    ) {}

    public function loadUser(string $principal): User
    {
        $attributes = $this->casService->getAttributes();
        $username = $this->getCASUsername($principal, $attributes);

        $repository = $this->dm->getRepository(User::class);
        $user = $repository->findOneBy(['username' => $username]);
        if (!$user instanceof User && $username !== $principal) {
            $user = $repository->findOneBy(['username' => $principal]);
        }

        if (!$user instanceof User) {
            if (!$this->createUsers) {
                $this->logger->warning('CAS login refused: the user does not exist and create_users is false', ['user' => $username]);

                throw new CustomUserMessageAuthenticationException('Your account does not exist in PuMuKIT.');
            }

            return $this->createDefaultUser($principal, $attributes);
        }

        $this->updateUser($user, $attributes);

        return $user;
    }

    protected function createDefaultUser(string $principal, array $attributes): User
    {
        $user = new User();
        $user->setUsername($this->getCASUsername($principal, $attributes));
        $user->setEmail($this->getCASEmail($attributes));
        $user->setFullName($this->getCASFullName($attributes));
        $user->setPermissionProfile($this->getPermissionProfile($attributes));
        $user->setOrigin($this->casOriginKey);
        $user->setEnabled(true);

        $this->userService->create($user);
        $this->setCASGroups($attributes, $user);
        $this->personService->referencePersonIntoUser($user);

        $this->logger->info('CAS user created', ['user' => $user->getUsername(), 'permission_profile' => $user->getPermissionProfile()?->getName()]);

        return $user;
    }

    protected function updateUser(User $user, array $attributes): void
    {
        if ($this->casOriginKey !== $user->getOrigin()) {
            return;
        }

        $user->setFullName($this->getCASFullName($attributes));
        $email = $this->attribute($attributes, $this->casMailKey);
        if ($email && $email !== $user->getEmail()) {
            $user->setEmail($email);
        }
        $this->setCASGroups($attributes, $user);

        if ($this->forceOverridePermissionProfile) {
            $permissionProfile = $this->getPermissionProfile($attributes);
            if ($user->getPermissionProfile()?->getId() !== $permissionProfile->getId()) {
                $this->logger->info('CAS user permission profile changed', ['user' => $user->getUsername(), 'from' => $user->getPermissionProfile()?->getName(), 'to' => $permissionProfile->getName()]);
                $user->setPermissionProfile($permissionProfile);
            }
        }

        $this->userService->update($user, true, false);
    }

    protected function getPermissionProfile(array $attributes): PermissionProfile
    {
        $repository = $this->dm->getRepository(PermissionProfile::class);

        if (null !== $this->permissionProfilesAttribute) {
            $values = $this->values($attributes, $this->permissionProfilesAttribute);
            if (!$values) {
                $this->logger->warning('CAS attribute of the permission profile not found, using the default one', ['attribute' => $this->permissionProfilesAttribute]);
            }
            foreach ($values as $value) {
                $name = $this->profileMapping[$value] ?? null;
                $permissionProfile = $name ? $repository->findOneBy(['name' => $name]) : null;
                if ($permissionProfile instanceof PermissionProfile) {
                    return $permissionProfile;
                }
            }
            if ($values) {
                $this->logger->warning('CAS permission profile not mapped or not found, using the default one', ['values' => $values]);
            }
        }

        $permissionProfile = $repository->findOneBy(['name' => $this->defaultPermissionProfile]) ?? $this->permissionProfileService->getDefault();
        if (!$permissionProfile instanceof PermissionProfile) {
            throw new \RuntimeException('Unable to assign a Permission Profile to the CAS user: there is no default Permission Profile');
        }

        return $permissionProfile;
    }

    protected function getCASUsername(string $principal, array $attributes): string
    {
        return $this->attribute($attributes, $this->casIdKey) ?? $principal;
    }

    protected function getCASEmail(array $attributes): string
    {
        $email = $this->attribute($attributes, $this->casMailKey);
        if (!$email) {
            throw new CustomUserMessageAuthenticationException('Your CAS account has no email.');
        }

        return $email;
    }

    protected function getCASFullName(array $attributes): string
    {
        return trim(($this->attribute($attributes, $this->casGivenNameKey) ?? '').' '.($this->attribute($attributes, $this->casSurnameKey) ?? ''));
    }

    protected function setCASGroups(array $attributes, User $user): void
    {
        if (!array_key_exists($this->casGroupKey, $attributes)) {
            return;
        }

        $groups = array_map(fn (string $key) => $this->getGroup($key), $this->values($attributes, $this->casGroupKey));
        $keys = array_map(static fn (Group $group) => $group->getKey(), $groups);

        foreach ($user->getGroups() as $group) {
            if ($this->casOriginKey === $group->getOrigin() && !in_array($group->getKey(), $keys, true)) {
                $this->userService->deleteGroup($group, $user, true, false);
            }
        }
        foreach ($groups as $group) {
            if (!$user->containsGroup($group)) {
                $this->userService->addGroup($group, $user, true, false);
            }
        }
    }

    protected function getGroup(string $key): Group
    {
        $cleanKey = preg_replace('/\W/', '', $key);

        $group = $this->dm->getRepository(Group::class)->findOneBy(['key' => $cleanKey]);
        if ($group instanceof Group) {
            return $group;
        }

        $group = new Group();
        $group->setKey($cleanKey);
        $group->setName($key);
        $group->setOrigin($this->casOriginKey);
        $this->groupService->create($group);

        return $group;
    }

    private function attribute(array $attributes, string $key): ?string
    {
        return $this->values($attributes, $key)[0] ?? null;
    }

    private function values(array $attributes, string $key): array
    {
        $values = $attributes[$key] ?? [];

        return array_values(array_filter(array_map('strval', is_array($values) ? $values : [$values]), static fn (string $value) => '' !== $value));
    }
}
