<?php

namespace App\Form;
use App\Entity\RevaluationHistory;
use App\Entity\Property;
use App\Entity\Warrant;
use App\Entity\Honoraire;
use App\Form\Type\DayType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8', 'fr_FR.UTF-8', 'fr.UTF-8');

class PropertycreateFormType extends AbstractType
{
    
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
       
            ->add('title', TextType::class)
            ->add('address', TextType::class, ['required' => false])
            ->add('postalCode', TextType::class)
            ->add('city', TextType::class)
            ->add('country', TextType::class)
            ->add('firstname1', TextType::class)
            ->add('lastname1', TextType::class);

            if($options['data']->getType()==2){
                $builder->add('buyerFirstname', TextType::class, ['required' => false])
                ->add('buyerLastname', TextType::class, ['required' => false])
                ->add('buyerAddress', TextType::class, ['required' => false])
                ->add('buyerPostalCode', TextType::class, ['required' => false])
                ->add('buyerCity', TextType::class, ['required' => false])
                ->add('buyerCountry', TextType::class, ['required' => false])
                ->add('buyerPhone1', TextType::class, ['required' => false])
                ->add('buyerPhone2', TextType::class, ['required' => false])
                ->add('buyerMail1', TextType::class, ['required' => false])
                ->add('buyerMail2', TextType::class, ['required' => false])
                ->add('buyer_bank_establishment_code', TextType::class, ['required' => false])
                ->add('buyer_bank_code_box', TextType::class, ['required' => false])
                ->add('buyer_bank_account_number', TextType::class, ['required' => false])
                ->add('buyer_bank_key', TextType::class, ['required' => false])
                ->add('buyer_bank_domiciliation', TextType::class, ['required' => false])
                ->add('buyer_bank_iban', TextType::class, ['required' => false])
                ->add('buyer_bank_bic', TextType::class, ['required' => false]);
            }
            $builder->add('dateofbirth1', BirthdayType::class, ['format' => 'dd-MMM-yyyy'])
            ->add('firstname2', TextType::class, ['required' => false])
            ->add('lastname2', TextType::class, ['required' => false])
            ->add('dateofbirth2', BirthdayType::class, ['required' => false, 'format' => 'dd-MMM-yyyy'])
            ->add('mail1', EmailType::class, ['required' => false])
            ->add('honorariesDisabled', CheckboxType::class, ['required' => false])
            ->add('revaluationIndex', TextType::class, ['required' => false])
            ->add('abandonmentIndex', TextType::class, ['required' => false])
            ->add('revaluationDate', DayType::class)
            ->add('initialAmount', TextType::class)
            ->add('valeur_indice_reference_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2 or rh.type LIKE :key3')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                        ->setParameter('key3', "OGI")
                        ->orderBy('rh.type', 'ASC')
                        ->addOrderBy('rh.date', 'DESC');
                    
                    
                },
                'choice_attr' => function($package) use ($options) {
                    if($options['data']->valeur_indice_reference_object){
                        $selected = false;
                        if($package == $options['data']->valeur_indice_reference_object) {
                            $selected = true;
                        }
                        return ['selected' => $selected];
                    }else{
                        return ['selected' => false];
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('condominiumFees', TextType::class, ['required' => false])
            ->add('garbageTax', TextType::class, ['required' => false])
            ->add('bank_establishment_code', TextType::class, ['required' => false])
            ->add('bank_code_box', TextType::class, ['required' => false])
            ->add('bank_account_number', TextType::class, ['required' => false])
            ->add('bank_key', TextType::class, ['required' => false])
            ->add('bank_domiciliation', TextType::class, ['required' => false])
            ->add('bank_iban', TextType::class, ['required' => false])
            ->add('bank_bic', TextType::class, ['required' => false])
            ->add('bank_ics', TextType::class, ['required' => false,'disabled' => 'true'],)
          
            ->add('comment', TextareaType::class, ['required' => false])
            ->add('hide_export_monthly', CheckboxType::class, ['required' => false])
            ->add('hide_export_otp', CheckboxType::class, ['required' => false])
            ->add('hide_export_quarterly', CheckboxType::class, ['required' => false])
            ->add('honorary_rates_object',  EntityType::class, [
                'class' => Honoraire::class,
                'query_builder' => function (EntityRepository $er) {
                    return $er->createQueryBuilder('h')
                        ->orderBy('h.id', 'DESC');
                },
                'choice_label' => function (Honoraire $h): string {
                    return 'Titre: '.$h->getNom().'- Taux:'.$h->getValeur().'%';
                },
                'choice_value' => 'id',
            ])
            ->add('initial_index_object', EntityType::class, [
                'required' => false,
                'class' => RevaluationHistory::class,
                'query_builder' => function (EntityRepository $er)  use($options){
                    if($options['data']->initial_index_object){
                        return $er->createQueryBuilder('rh')
                        ->where('rh.id = :id')
                        ->setParameter('id', $options['data']->initial_index_object)
                            ->orderBy('rh.id', 'DESC');
                    }else{
                        return $er->createQueryBuilder('rh')
                        ->where('rh.type LIKE :key1 or rh.type LIKE :key2')
                        ->setParameter('key1', "Urbains")
                        ->setParameter('key2', "Ménages")
                            ->orderBy('rh.id', 'DESC');
                    }
                    
                },
                'choice_label' => function (RevaluationHistory $rh): string {
                    return $rh->getValue().' '.$rh->getType().' mois de '.(strftime('%B %Y',$rh->getDate()->getTimestamp()));
                },
                'choice_value' => 'id',
            ])
            ->add('mail2', EmailType::class, ['required' => false])

            ->add('goodType', ChoiceType::class, ['choices' => array_flip(Property::LIFETIME_TYPES), 'choice_translation_domain' => false])
            ->add('propertyType', TextType::class, ['required' => false])
            //->add('date_signature_acte_authentique', BirthdayType::class, ['required' => false, 'format' => 'dd-MMM-yyyy'])
            //->add('coordonnees_syndic2', TextType::class, ['required' => false,'disabled' => 'true'])
            ->add('constructionYear', IntegerType::class, ['required' => false])
            ->add('livingSpace', TextType::class, ['required' => false])
            ->add('groundSurface', TextType::class, ['required' => false])
            ->add('heatingType', ChoiceType::class, ['choices' => array_flip(Property::HEATING_TYPES), 'choice_translation_domain' => false])
            ->add('garage', IntegerType::class, ['required' => false])
            ->add('parking', IntegerType::class, ['required' => false])
            ->add('cellar', IntegerType::class, ['required' => false])
            ->add('billingDisabled', CheckboxType::class, ['required' => false])

            //->add('revaluationIndex', TextType::class, ['required' => false])
            ->add('fireplace', CheckboxType::class, ['required' => false])

           
            ->add('dosAuthenticInstrument', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y'))])
            ->add('startDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 2)])
            ->add('endDateManagement', DateType::class, ['required' => false, 'format' => 'dd-MMM-yyyy', 'years' => range(2010, date('Y') + 25)]);
    
           

        /*$builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
            $property = $event->getData();
            $form = $event->getForm();
            $form->add('parking', IntegerType::class, ['required' => false]);
                    // check if the Product object is "new"
                    // If no data is passed to the form, the data is "null".
                    // This should be considered a new "Product"
                    if ($property->getIntitulesIndicesInitial()==1) {//urbain
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key1')
                                ->setParameter('key1', "Urbains")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }else if ($property->getIntitulesIndicesInitial()==2) {//ménages
                        $form->add('initialIndex', EntityType::class, [
                            'class' => RevaluationHistory::class,
                            'query_builder' => function (EntityRepository $er) {
                                return $er->createQueryBuilder('rh')
                                ->where('rh.type LIKE :key2 ')
                                
                                ->setParameter('key2', "Ménages")
                                    ->orderBy('rh.id', 'DESC');
                            },
                            'choice_label' => function (RevaluationHistory $rh): string {
                                return $rh->getValue().' '.$rh->getType().' mois de '.strftime('%B %Y',$rh->getDate()->getTimestamp());
                            },
                        ]);
                    }
        });*/
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
        ]);
    }
}
