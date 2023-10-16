<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\GroupService;
use Pumukit\SchemaBundle\Services\PermissionProfileService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CASUserService
{
    protected $userService;
    protected $personService;
    protected $casService;
    protected $permissionProfileService;
    protected $groupService;
    protected $dm;

    private $casIdKey;
    private $casMailKey;
    private $casGivenNameKey;
    private $casSurnameKey;
    private $casGroupKey;
    private $casOriginKey;
    private $profileMapping;
    private $permissionProfilesAttribute;
    private $defaultPermissionProfile;
    private $forceOverridePermissionProfile;

    public function __construct(
        UserService $userService,
        PersonService $personService,
        CASService $casService,
        PermissionProfileService $permissionProfileService,
        GroupService $groupService,
        DocumentManager $documentManager,
        $casIdKey,
        $casMailKey,
        $casGivenNameKey,
        $casSurnameKey,
        $casGroupKey,
        $casOriginKey,
        $profileMapping,
        $permissionProfilesAttribute,
        $defaultPermissionProfile,
        $forceOverridePermissionProfile
    ) {
        $this->userService = $userService;
        $this->personService = $personService;
        $this->casService = $casService;
        $this->permissionProfileService = $permissionProfileService;
        $this->groupService = $groupService;
        $this->dm = $documentManager;

        $this->casIdKey = $casIdKey;
        $this->casMailKey = $casMailKey;
        $this->casGivenNameKey = $casGivenNameKey;
        $this->casSurnameKey = $casSurnameKey;
        $this->casGroupKey = $casGroupKey;
        $this->casOriginKey = $casOriginKey;

        $this->profileMapping = $profileMapping;
        $this->permissionProfilesAttribute = $permissionProfilesAttribute;
        $this->defaultPermissionProfile = $defaultPermissionProfile;
        $this->forceOverridePermissionProfile = $forceOverridePermissionProfile;
    }

    public function createDefaultUser(string $userName): User
    {
        $attributes = $this->getCASAttributes();

        $user = new User();

        $casUserName = $this->getCASUsername($userName, $attributes);
        $user->setUsername($casUserName);

        $casEmail = $this->getCASEmail($attributes);
        if ($casEmail) {
            $user->setEmail($casEmail);
        }

        $casFullName = $this->getCASFullName($attributes);
        $user->setFullname($casFullName);

        $defaultPermissionProfile = $this->getPermissionProfile();
        $user->setPermissionProfile($defaultPermissionProfile);

        $user->setOrigin($this->casOriginKey);
        $user->setEnabled(true);

        $this->userService->create($user);

        $this->setCASGroup($attributes, $user);

        $this->personService->referencePersonIntoUser($user);

        return $user;
    }

    public function updateUser(User $user): void
    {
        if ($this->casOriginKey === $user->getOrigin()) {
            $attributes = $this->getCASAttributes();

            $casFullName = $this->getCASFullName($attributes);
            $user->setFullname($casFullName);

            $this->setCASGroup($attributes, $user);

            if ((isset($attributes[$this->casMailKey])) && ($attributes[$this->casMailKey] !== $user->getEmail())) {
                $user->setEmail($attributes[$this->casMailKey]);
            }

            $user = $this->checkAndSetPermissionProfile($attributes, $user);

            $this->dm->persist($user);

            $this->userService->update($user, true, false);
        }
    }

    protected function checkAndSetPermissionProfile($attributes, User $user): User
    {
        if (!$this->forceOverridePermissionProfile) {
            return $user;
        }

        if (null === $this->permissionProfilesAttribute) {
            $defaultProfile = $this->dm->getRepository(PermissionProfile::class)->findOneBy(['name' => $this->defaultPermissionProfile]);
            if ($user->getPermissionProfile()->getId() !== $defaultProfile->getId()) {
                $user->setPermissionProfile($defaultProfile);
                $this->userService->update($user, true, false);
            }

            return $user;
        }

        if (!array_key_exists($this->permissionProfilesAttribute, $attributes)) {
            throw new \Exception(self::class.'Profile attribute key not defined');
        }

        $permissionProfileString = $attributes[$this->permissionProfilesAttribute];
        $permissionProfileString = $this->profileMapping[$permissionProfileString];

        $permissionProfile = $this->dm->getRepository(PermissionProfile::class)->findOneBy(['name' => $permissionProfileString]);
        if (!$permissionProfile instanceof PermissionProfile) {
            $user->setPermissionProfile($this->getPermissionProfile());
        } elseif ($user->getPermissionProfile()->getId() !== $permissionProfile->getId()) {
            $user->setPermissionProfile($permissionProfile);
        }

        $this->userService->update($user, true, false);

        return $user;
    }

    protected function getCASAttributes()
    {
        $this->casService->forceAuthentication();

        return $this->casService->getAttributes();
    }

    protected function getCASUsername(string $userName, array $attributes)
    {
        return $attributes[$this->casIdKey] ?? $userName;
    }

    protected function getCASEmail(array $attributes)
    {
        $mail = $attributes[$this->casMailKey] ?? null;
        if (!$mail) {
            throw new AuthenticationException("Mail can't be null");
        }

        return $mail;
    }

    protected function getCASFullName(array $attributes): string
    {
        $givenName = $attributes[$this->casGivenNameKey] ?? '';
        $surName = $attributes[$this->casSurnameKey] ?? '';

        return $givenName.' '.$surName;
    }

    protected function getPermissionProfile(): ?PermissionProfile
    {
        $defaultPermissionProfile = $this->permissionProfileService->getDefault();
        if (null === $defaultPermissionProfile) {
            throw new \Exception('Unable to assign a Permission Profile to the new User. There is no default Permission Profile');
        }

        return $defaultPermissionProfile;
    }

    protected function setCASGroup(array $attributes, User $user): void
    {
        if (isset($attributes[$this->casGroupKey])) {
            $groupCAS = $this->getGroup($attributes[$this->casGroupKey]);
            foreach ($user->getGroups() as $group) {
                if ($this->casOriginKey === $group->getOrigin()) {
                    $this->userService->deleteGroup($group, $user, true, false);
                }
            }
            $this->userService->addGroup($groupCAS, $user, true, false);
        }
    }

    protected function getGroup(string $key): Group
    {
        $cleanKey = preg_replace('/\W/', '', $key);

        $group = $this->dm->getRepository(Group::class)->findOneBy(
            ['key' => $cleanKey]
        );

        if ($group) {
            return $group;
        }

        $group = new Group();
        $group->setKey($cleanKey);
        $group->setName($key);
        $group->setOrigin($this->casOriginKey);
        $this->groupService->create($group);

        return $group;
    }
}
