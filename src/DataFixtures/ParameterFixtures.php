<?php

namespace App\DataFixtures;

use App\Entity\Parameter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

class ParameterFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $parameter = new Parameter();
        $parameter->setName('last_cron');
        $parameter->setValue(0);
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('last_cron_daily');
        $parameter->setValue(0);
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('tva');
        $parameter->setValue(20);
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_number');
        $parameter->setValue(0);
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_footer');
        $parameter->setValue('SIRET 123 456 789 10111 - TVA FR12345678910 - TVA acquittée sur les encaissements');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_address');
        $parameter->setValue('37 rue du chat');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_postalcode');
        $parameter->setValue('13000');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_city');
        $parameter->setValue('MARSEILLE');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_phone');
        $parameter->setValue('0412345678');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_mail');
        $parameter->setValue('contact@viag2e.fr');
        $manager->persist($parameter);

        $parameter = new Parameter();
        $parameter->setName('invoice_site');
        $parameter->setValue('www.viag2e.fr');
        $manager->persist($parameter);

        $manager->flush();
    }
}
