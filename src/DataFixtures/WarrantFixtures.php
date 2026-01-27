<?php

namespace App\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use App\Entity\Warrant;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class WarrantFixtures extends Fixture implements DependentFixtureInterface
{
    public const WARRANT_REFERENCE = 'warrant';

    public function load(ObjectManager $manager)
    {
        $warrant_b = new Warrant();
        $warrant_b->setType(Warrant::TYPE_BUYERS);
        $warrant_b->setFirstname('Bob');
        $warrant_b->setLastname('Smith');
        $warrant_b->setMail1('me@nico-m.fr');
        $warrant_b->setAddress('11 rue des farfelus');
        $warrant_b->setPostalCode('53000');
        $warrant_b->setCity('Laval');
        $warrant_b->setCountry('France');
        $warrant_b->setCreationUser($this->getReference(UserFixtures::USER_REFERENCE));
        $warrant_b->setEditionUser($this->getReference(UserFixtures::USER_REFERENCE));
        $manager->persist($warrant_b);

        $warrant_s = new Warrant();
        $warrant_s->setType(Warrant::TYPE_SELLERS);
        $warrant_s->setFirstname('Jean');
        $warrant_s->setLastname('Bon');
        $warrant_s->setAddress('14 rue de Pierre Saucisson');
        $warrant_s->setPostalCode('44000');
        $warrant_s->setCity('Nantes');
        $warrant_s->setCreationUser($this->getReference(UserFixtures::USER_REFERENCE));
        $warrant_s->setEditionUser($this->getReference(UserFixtures::USER_REFERENCE));
        $manager->persist($warrant_s);

        $manager->flush();

        $this->addReference(self::WARRANT_REFERENCE, $warrant_b);
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class
        ];
    }
}
