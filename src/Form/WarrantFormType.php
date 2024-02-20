<?php

namespace App\Form;

use App\Entity\Warrant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WarrantFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstname', TextType::class)
            ->add('lastname', TextType::class)
            ->add('address', TextType::class)
            ->add('postal_code', TextType::class)
            ->add('city', TextType::class)
            ->add('country', TextType::class)
            ->add('clientId', TextType::class, ['required' => false])
            ->add('phone1', TelType::class, ['required' => false])
            ->add('phone2', TelType::class, ['required' => false])
            ->add('mail1', EmailType::class, ['required' => false])
            ->add('mail2', EmailType::class, ['required' => false])
            ->add('comment', TextareaType::class, ['required' => false])
            ->add('fact_address', TextType::class, ['required' => false])
            ->add('fact_postal_code', TextType::class, ['required' => false])
            ->add('fact_city', TextType::class, ['required' => false])
            ->add('fact_country', TextType::class, ['required' => false])
            ->add('bank_establishment_code', TextType::class, ['required' => false])
            ->add('bank_code_box', TextType::class, ['required' => false])
            ->add('bank_account_number', TextType::class, ['required' => false])
            ->add('bank_key', TextType::class, ['required' => false])
            ->add('bank_domiciliation', TextType::class, ['required' => false])
            ->add('bank_iban', TextType::class, ['required' => false])
            ->add('bank_bic', TextType::class, ['required' => false])
            ->add('bank_ics', TextType::class, ['required' => false,'disabled' => 'true',])
            ->add('rum', TextType::class, ['required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Warrant::class,
        ]);
    }
}
