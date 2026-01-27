<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use \App\Entity\User;

class UserFixtures extends Fixture
{
    public const USER_REFERENCE = 'user';

    /**
     * @var UserPasswordEncoderInterface
     */
    private $passwordEncoder;

    /**
     * UserFixtures constructor.
     * @param UserPasswordEncoderInterface $passwordEncoder
     */
    public function __construct(UserPasswordEncoderInterface $passwordEncoder)
    {
         $this->passwordEncoder = $passwordEncoder;
    }

    public function load(ObjectManager $manager)
    {
        $user = new User();
        $user->setEmail('me@nicolas-masse.fr');
        $user->setPassword($this->passwordEncoder->encodePassword($user, '123456'));
        $user->setFirstname('Nicolas');
        $user->setLastname('Masse');
        $user->setRoles(['ROLE_ADMIN']);
        $manager->persist($user);

        $user2 = new User();
        $user2->setEmail('antoine@edovel.com');
        $user2->setPassword($this->passwordEncoder->encodePassword($user, '123456'));
        $user2->setFirstname('Antoine');
        $user2->setLastname('C');
        $user2->setRoles(['ROLE_ADMIN']);
        $manager->persist($user2);

        $manager->flush();

        $this->addReference(self::USER_REFERENCE, $user);
    }
}
