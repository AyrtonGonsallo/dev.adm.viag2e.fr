<?php

namespace App\DataFixtures;

use App\Entity\Property;
use App\Entity\Warrant;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class PropertyFixtures extends Fixture implements DependentFixtureInterface
{
    public const PROPERTY_REFERENCE = 'property';

    public function load(ObjectManager $manager)
    {
        $property = new Property();
        $property->setType(Warrant::TYPE_BUYERS);
        $property->setTitle('Joli titre');
        $property->setAddress('11 rue des farfelus');
        $property->setPostalCode('53000');
        $property->setCity('Laval');
        $property->setFirstname1('Bob');
        $property->setLastname1('Smith');
        $property->setDateofbirth1(new \DateTime('1940-12-22 00:00:00'));
        $property->setLivingSpace(120.0);
        $property->setGroundSurface(257.0);
        $property->setFireplace(true);
        $property->setInitialAmount(1000.0);
        $property->setInitialIndex(0.12);
        $property->setHonoraryRates(0.05);
        $property->setWarrant($this->getReference(WarrantFixtures::WARRANT_REFERENCE));
        $property->setCreationUser($this->getReference(UserFixtures::USER_REFERENCE));
        $property->setEditionUser($this->getReference(UserFixtures::USER_REFERENCE));
        $manager->persist($property);
        $manager->flush();

        $this->addReference(self::PROPERTY_REFERENCE, $property);
    }

    public function getDependencies()
    {
        return [
            UserFixtures::class,
            WarrantFixtures::class
        ];
    }
}
