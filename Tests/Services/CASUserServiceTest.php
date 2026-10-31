<?php

declare(strict_types=1);

namespace Pumukit\CasBundle\Tests\Services;

use Psr\Log\NullLogger;
use Pumukit\CasBundle\Services\CASService;
use Pumukit\CasBundle\Services\CASUserService;
use Pumukit\CoreBundle\Tests\PumukitTestCase;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Services\GroupService;
use Pumukit\SchemaBundle\Services\PermissionProfileService;
use Pumukit\SchemaBundle\Services\PersonService;
use Pumukit\SchemaBundle\Services\UserService;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * @internal
 *
 * @coversNothing
 */
class CASUserServiceTest extends PumukitTestCase
{
    private array $attributes = [];

    public function setUp(): void
    {
        parent::setUp();

        foreach (['Viewer' => false, 'Teacher' => false, 'Fallback' => true] as $name => $default) {
            $profile = new PermissionProfile();
            $profile->setName($name);
            $profile->setDefault($default);
            $profile->setScope(PermissionProfile::SCOPE_PERSONAL);
            $this->dm->persist($profile);
        }
        $this->dm->flush();
    }

    public function testANewUserIsCreatedFromTheCasAttributes(): void
    {
        $this->attributes = ['UID' => 'jdoe', 'MAIL' => ['jdoe@example.org', 'other@example.org'], 'GIVENNAME' => 'John', 'SURNAME' => 'Doe', 'role' => ['unknown', 'teacher', 'student'], 'GROUP' => ['math-dept', 'physics']];

        $user = $this->service()->loadUser('principal-123');

        $this->assertSame('jdoe', $user->getUsername());
        $this->assertSame('jdoe@example.org', $user->getEmail());
        $this->assertSame('John Doe', $user->getFullName());
        $this->assertSame('cas', $user->getOrigin());
        $this->assertSame('Teacher', $user->getPermissionProfile()->getName());
        $this->assertSame(['mathdept', 'physics'], $this->groupKeys($user));
    }

    public function testTheNextLoginFindsTheUserCreatedWithTheIdAttribute(): void
    {
        $this->attributes = ['UID' => 'jdoe', 'MAIL' => 'jdoe@example.org', 'role' => 'teacher'];
        $first = $this->service()->loadUser('principal-123');

        $second = $this->service()->loadUser('principal-123');

        $this->assertSame($first->getId(), $second->getId());
        $this->assertCount(1, $this->dm->getRepository(User::class)->findBy(['username' => 'jdoe']));
    }

    public function testAnUnmappedOrMissingRoleGetsTheConfiguredDefaultProfile(): void
    {
        $this->attributes = ['UID' => 'a', 'MAIL' => 'a@example.org', 'role' => 'unknown'];
        $this->assertSame('Viewer', $this->service()->loadUser('a')->getPermissionProfile()->getName());

        $this->attributes = ['UID' => 'b', 'MAIL' => 'b@example.org'];
        $this->assertSame('Viewer', $this->service()->loadUser('b')->getPermissionProfile()->getName());
    }

    public function testTheProfileServiceDefaultIsTheLastResort(): void
    {
        $this->attributes = ['UID' => 'c', 'MAIL' => 'c@example.org'];

        $this->assertSame('Fallback', $this->service(['defaultPermissionProfile' => 'Missing'])->loadUser('c')->getPermissionProfile()->getName());
    }

    public function testLoginUpdatesTheCasGroupsAndKeepsTheOthers(): void
    {
        $this->attributes = ['UID' => 'jdoe', 'MAIL' => 'jdoe@example.org', 'GROUP' => ['math', 'physics']];
        $user = $this->service()->loadUser('jdoe');
        $local = new Group();
        $local->setKey('local');
        $local->setName('Local');
        $local->setOrigin('local');
        self::getContainer()->get(GroupService::class)->create($local);
        self::getContainer()->get(UserService::class)->addGroup($local, $user, true, false);

        $this->attributes['GROUP'] = ['physics', 'chemistry'];
        $user = $this->service()->loadUser('jdoe');

        $this->assertSame(['chemistry', 'local', 'physics'], $this->groupKeys($user));
    }

    public function testTheProfileIsNotOverriddenWhenTheOptionIsOff(): void
    {
        $this->attributes = ['UID' => 'jdoe', 'MAIL' => 'jdoe@example.org', 'role' => 'teacher'];
        $this->service()->loadUser('jdoe');

        $this->attributes['role'] = 'student';
        $user = $this->service(['forceOverridePermissionProfile' => false])->loadUser('jdoe');

        $this->assertSame('Teacher', $user->getPermissionProfile()->getName());
    }

    public function testUsersFromAnotherOriginAreNotModified(): void
    {
        $user = new User();
        $user->setUsername('admin');
        $user->setEmail('admin@example.org');
        $user->setFullName('Local Admin');
        $user->setOrigin('local');
        $this->dm->persist($user);
        $this->dm->flush();
        $this->attributes = ['UID' => 'admin', 'MAIL' => 'cas@example.org', 'GIVENNAME' => 'Other'];

        $loaded = $this->service()->loadUser('admin');

        $this->assertSame('Local Admin', $loaded->getFullName());
        $this->assertSame('admin@example.org', $loaded->getEmail());
    }

    public function testUnknownUsersAreRefusedWhenUserCreationIsOff(): void
    {
        $this->attributes = ['UID' => 'jdoe', 'MAIL' => 'jdoe@example.org'];

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->service(['createUsers' => false])->loadUser('jdoe');
    }

    public function testANewUserNeedsAnEmail(): void
    {
        $this->attributes = ['UID' => 'jdoe'];

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->service()->loadUser('jdoe');
    }

    private function service(array $options = []): CASUserService
    {
        $casService = $this->createStub(CASService::class);
        $casService->method('getAttributes')->willReturnCallback(fn () => $this->attributes);
        $container = self::getContainer();
        $options += ['defaultPermissionProfile' => 'Viewer', 'forceOverridePermissionProfile' => true, 'createUsers' => true];

        return new CASUserService(
            $container->get(UserService::class),
            $container->get(PersonService::class),
            $casService,
            $container->get(PermissionProfileService::class),
            $container->get(GroupService::class),
            $this->dm,
            new NullLogger(),
            'UID',
            'MAIL',
            'GIVENNAME',
            'SURNAME',
            'GROUP',
            'cas',
            ['teacher' => 'Teacher', 'student' => 'Viewer'],
            'role',
            $options['defaultPermissionProfile'],
            $options['forceOverridePermissionProfile'],
            $options['createUsers'],
        );
    }

    private function groupKeys(User $user): array
    {
        $keys = array_map(static fn (Group $group) => $group->getKey(), iterator_to_array($user->getGroups(), false));
        sort($keys);

        return $keys;
    }
}
